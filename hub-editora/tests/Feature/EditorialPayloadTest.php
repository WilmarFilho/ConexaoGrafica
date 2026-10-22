<?php

namespace Tests\Feature;

use App\Editorial\EditorialException;
use App\Editorial\WordPress\Client;
use App\Editorial\WordPress\Payload;
use App\Editorial\WordPress\PublishBook;
use App\Editorial\WordPress\UnpublishBook;
use App\Models\Chapter;
use App\Models\Person;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/** Payload Hub → WordPress e jobs de publicação (contrato, seção 4). */
class EditorialPayloadTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    private const SECRET = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hub.wordpress.url' => 'https://conexaoeditora.test/', 'hub.wordpress.secret' => self::SECRET]);
    }

    public function test_payload_is_identical_to_the_example_json(): void
    {
        $book = $this->tratado();

        // O exemplo é anterior à decisão da frente B de incluir `doi_status` (livro e capítulos).
        $expected = $this->examplePayload();
        $expected['book']['doi_status'] = 'none';
        foreach ($expected['chapters'] as &$chapter) {
            $chapter['doi_status'] = 'none';
        }
        unset($chapter);

        $actual = Payload::forBook($book);

        // Mesmas chaves, na mesma ordem, com os mesmos tipos (=== em arrays).
        $this->assertSame($expected, $actual);

        // JSON serializado também bate (datas ISO, nulos, booleanos).
        $this->assertJsonStringEqualsJsonString(json_encode($expected), json_encode($actual));
    }

    public function test_payload_inherits_language_and_date_in_chapters_and_groups_the_toc_by_section(): void
    {
        $book = $this->tratado();
        $book->update(['series_title' => 'Série Atualidades', 'series_issn' => '1234-5678']);
        $book->chapters()->create(['sequence' => 3, 'title' => 'Avulso', 'first_page' => 53, 'last_page' => 60, 'language' => 'en', 'publication_date' => '2026-04-01']);

        $p = Payload::forBook($book->fresh());

        $this->assertSame(['title' => 'Série Atualidades', 'issn' => '1234-5678'], $p['book']['series']);
        $this->assertSame([
            ['section' => 'Seção 1 — Fundamentos', 'chapters' => [10, 11]],
            ['section' => null, 'chapters' => [Chapter::where('sequence', 3)->value('id')]],
        ], $p['book']['toc']);

        $avulso = $p['chapters'][2];
        $this->assertSame('en', $avulso['language']);
        $this->assertSame('2026-04-01', $avulso['publication_date']);
        $this->assertSame('10.00000/conexao.2026.001.03', $avulso['doi']);
        $this->assertNull($avulso['abstract'], 'só o resumo aprovado é publicado');
        $this->assertSame('draft', $avulso['status']);
        $this->assertSame('none', $avulso['doi_status']);

        $book->update(['doi_status' => 'sent']);
        $this->assertSame('sent', Payload::forBook($book->fresh())['book']['doi_status'], 'o WP trava o slug a partir de sent');
    }

    public function test_document_upload_goes_as_signed_json_with_base64_content(): void
    {
        Http::fake(['conexaoeditora.test/wp-json/conexao-doi/v1/documentos' => Http::response(['attachment_id' => 77, 'url' => 'https://conexaoeditora.com.br/wp-content/uploads/ficha.pdf'])]);

        $r = Client::fromConfig()->uploadDocument(1, 'book_sheet', 'ficha-livro-1.pdf', '%PDF-1.4 conteudo', 'v1');

        $this->assertSame(77, $r['attachment_id']);
        Http::assertSent(function (Request $request) {
            $sent = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->header('Content-Type')[0] === 'application/json'
                && $request->header('X-Conexao-Signature')[0] === hash_hmac('sha256', $request->header('X-Conexao-Timestamp')[0]."\n".$request->body(), self::SECRET)
                && $sent === ['hub_book_id' => 1, 'hub_chapter_id' => null, 'type' => 'book_sheet', 'filename' => 'ficha-livro-1.pdf', 'version' => 'v1', 'content_base64' => base64_encode('%PDF-1.4 conteudo')];
        });

        $this->expectException(\RuntimeException::class);
        Client::fromConfig()->uploadDocument(1, 'capa', 'x.pdf', '');
    }

    public function test_commercial_url_must_be_https_on_the_publisher_domain(): void
    {
        $book = $this->tratado();

        $ok = $book->commercialLinks()->create(['modality' => 'print', 'url' => 'https://loja.conexaoeditora.com.br/produto/tratado', 'sort_order' => 4]);
        $this->assertSame('Comprar livro impresso', $ok->label, 'rótulo padrão da modalidade');

        foreach (['http://conexaoeditora.com.br/x', 'https://outra-loja.com.br/x', 'https://conexaoeditora.com.br.evil.com/x'] as $bad) {
            try {
                $book->commercialLinks()->create(['modality' => 'ebook', 'url' => $bad, 'sort_order' => 5]);
                $this->fail("aceitou {$bad}");
            } catch (EditorialException $e) {
                $this->assertStringContainsString('conexaoeditora.com.br', $e->getMessage());
            }
        }
    }

    public function test_publish_job_signs_the_request_and_stores_the_wordpress_ids(): void
    {
        $book = $this->tratado();
        $book->update(['status' => 'approved']);

        Http::fake([
            'conexaoeditora.test/wp-json/conexao-doi/v1/publicacoes/1' => Http::response([
                'book' => ['wp_post_id' => 123, 'url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/'],
                'chapters' => [
                    ['hub_chapter_id' => 10, 'wp_post_id' => 124, 'url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/01/'],
                    ['hub_chapter_id' => 11, 'wp_post_id' => 125, 'url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/02/'],
                ],
                'people' => [['hub_person_id' => 3, 'wp_term_id' => 55]],
            ]),
        ]);

        PublishBook::dispatch($book->id, 7);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $timestamp = $request->header('X-Conexao-Timestamp')[0] ?? null;
            $signature = $request->header('X-Conexao-Signature')[0] ?? null;
            $body = $request->body();

            $this->assertSame('PUT', $request->method());
            $this->assertSame('https://conexaoeditora.test/wp-json/conexao-doi/v1/publicacoes/1', $request->url());
            $this->assertSame('application/json', $request->header('Content-Type')[0]);
            $this->assertEqualsWithDelta(time(), (int) $timestamp, 5);
            $this->assertSame(hash_hmac('sha256', $timestamp."\n".$body, self::SECRET), $signature);
            $this->assertSame(Client::sign($timestamp, $body, self::SECRET), $signature);

            $sent = json_decode($body, true);
            $this->assertSame('Tratado de Doenças Raras', $sent['book']['title']);
            $this->assertSame('published', $sent['book']['status']);
            $this->assertStringContainsString('Doenças', $body, 'UTF-8 sem escapar');

            return true;
        });

        $book->refresh();
        $this->assertSame('published', $book->status);
        $this->assertSame(123, $book->wp_post_id);
        $this->assertSame('https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/', $book->landing_url);
        $this->assertNotNull($book->published_at);
        $this->assertTrue($book->canBeDeposited());

        $c = Chapter::find(10);
        $this->assertSame(124, $c->wp_post_id);
        $this->assertSame('https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/01/', $c->landing_url);
        $this->assertSame(55, Person::find(3)->wp_term_id);

        $log = SyncLog::where('action', 'publish.sent')->firstOrFail();
        $this->assertSame(SyncLog::OUT, $log->direction);
        $this->assertSame('info', $log->level);
        $this->assertSame(123, $log->context['wp_post_id']);
        $this->assertSame(7, $log->context['user_id']);
    }

    public function test_publish_failure_is_logged_as_error_and_rethrown_for_the_queue(): void
    {
        $book = $this->tratado();
        $book->update(['status' => 'approved']);

        Http::fake(['*' => Http::response('Assinatura inválida', 401)]);

        try {
            (new PublishBook($book->id))->handle();
            $this->fail('erro HTTP não propagou');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
        }

        $this->assertSame('approved', $book->fresh()->status);
        $log = SyncLog::where('action', 'publish.failed')->firstOrFail();
        $this->assertSame('error', $log->level);
        $this->assertStringContainsString('Assinatura inválida', $log->message);
    }

    public function test_publish_job_refuses_books_that_are_not_approved(): void
    {
        $book = $this->tratado();
        $book->update(['status' => 'review']);
        Http::fake();

        (new PublishBook($book->id))->handle();

        Http::assertNothingSent();
        $this->assertSame('warning', SyncLog::where('action', 'publish.failed')->firstOrFail()->level);
    }

    public function test_unpublish_job_calls_despublicar_and_keeps_the_page(): void
    {
        $book = $this->tratado();
        $book->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/', 'wp_post_id' => 123]);

        Http::fake(['conexaoeditora.test/wp-json/conexao-doi/v1/publicacoes/1/despublicar' => Http::response(['ok' => true])]);

        UnpublishBook::dispatch($book->id);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/publicacoes/1/despublicar')
            && $r->header('X-Conexao-Signature')[0] === hash_hmac('sha256', $r->header('X-Conexao-Timestamp')[0]."\n".$r->body(), self::SECRET));

        $book->refresh();
        $this->assertSame('unpublished', $book->status);
        $this->assertSame('https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/', $book->landing_url, 'a página continua');
        $this->assertTrue($book->canBePublished(), 'pode voltar ao catálogo republicando');
        $this->assertSame(1, SyncLog::where('action', 'publish.unpublished')->count());
    }

    public function test_client_refuses_to_run_without_configuration(): void
    {
        config(['hub.wordpress.url' => null, 'hub.wordpress.secret' => null]);
        $this->assertFalse(Client::isConfigured());

        $this->expectException(\RuntimeException::class);
        Client::fromConfig()->unpublish(1);
    }
}
