<?php

namespace Tests\Feature;

use App\Editorial\Citations;
use App\Editorial\Documents\PublishSheets;
use App\Editorial\Documents\SheetGenerator;
use App\Editorial\WordPress\Payload;
use App\Editorial\WordPress\PublishBook;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Document;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/**
 * Frente E do Conexão DOI: fichas científicas em PDF (dompdf + QR), hash e
 * regeneração, envio ao WordPress (Http::fake) e citações iguais às do plugin.
 */
class DocumentsTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    private const SECRET = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2';

    private const UPLOAD_URL = 'conexaoeditora.test/wp-json/conexao-doi/v1/documentos';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hub.wordpress.url' => 'https://conexaoeditora.test/', 'hub.wordpress.secret' => self::SECRET]);
        Http::preventStrayRequests();
    }

    /** Resposta do WP ao upload: attachment_id sequencial e URL com o nome do arquivo. */
    private function fakeUploads(): void
    {
        $n = 100;
        Http::fake([
            self::UPLOAD_URL => function (Request $request) use (&$n) {
                $sent = json_decode($request->body(), true);
                $n++;

                return Http::response([
                    'type' => $sent['type'],
                    'attachment_id' => $n,
                    'url' => 'https://conexaoeditora.com.br/wp-content/uploads/2026/10/'.$sent['filename'],
                    'version' => $sent['version'],
                    'generated_at' => null,
                    'wp_post_id' => 123,
                ]);
            },
        ]);
    }

    // ------------------------------------------------------------ PDF

    public function test_gera_pdf_valido_do_livro_e_do_capitulo(): void
    {
        // Sem Storage::fake de propósito: deixa um exemplar em storage/app/private/documents/ para o integrador abrir.
        $book = $this->tratado();
        $book->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/']);
        Chapter::find(10)->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/01/']);

        $generator = app(SheetGenerator::class);

        $doc = $generator->forBook($book->fresh());
        $this->assertSame('book_sheet', $doc->type);
        $this->assertSame('book', $doc->documentable_type);
        $this->assertSame(SheetGenerator::TEMPLATE_VERSION, $doc->template_version);
        $this->assertSame('documents/livro-1/ficha-livro-tratado-de-doencas-raras.pdf', $doc->path);
        $this->assertTrue($doc->stale, 'nunca enviada ao site');
        $this->assertNotNull($doc->generated_at);
        $this->assertSame(64, strlen($doc->hash));
        $this->assertTrue($doc->fileExists());

        $pdf = $doc->contents();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(10 * 1024, strlen($pdf), 'PDF do livro muito pequeno: '.strlen($pdf).' bytes');
        $this->assertStringContainsString('/Type /Page', $pdf);
        $pages = preg_match_all('~/Type\s*/Page[^s]~', $pdf);
        $this->assertGreaterThanOrEqual(2, $pages, 'a ficha do livro tem capa e conteúdo');
        $this->assertLessThanOrEqual(4, $pages);

        $chapter = Chapter::find(10);
        $cdoc = $generator->forChapter($chapter);
        $this->assertSame('chapter_sheet', $cdoc->type);
        $this->assertSame('chapter', $cdoc->documentable_type);
        $this->assertSame(10, $cdoc->documentable_id);
        $this->assertSame('documents/livro-1/ficha-capitulo-01-uma-breve-historia-das-doencas-raras.pdf', $cdoc->path);

        $cpdf = $cdoc->contents();
        $this->assertStringStartsWith('%PDF', $cpdf);
        $this->assertGreaterThan(10 * 1024, strlen($cpdf));
        $this->assertLessThanOrEqual(2, preg_match_all('~/Type\s*/Page[^s]~', $cpdf), 'ficha do capítulo em 1–2 páginas');

        $this->assertSame(2, SyncLog::where('action', 'document.generated')->count());
        $this->assertSame($doc->id, $book->fresh()->sheet->id);
        $this->assertSame($cdoc->id, $chapter->fresh()->sheet->id);

        // O HTML por trás do PDF traz tudo que a ficha promete (o PDF comprime o texto).
        $payload = Payload::forBook($book->fresh());
        $html = view('documents.book', $generator->bookData($book->fresh(), $payload) + [
            'qr' => $generator->qrDataUri('https://x'), 'generated_at' => now(), 'template_version' => '1.0',
        ])->render();
        foreach (['Tratado de Doenças Raras', 'Diagnóstico, manejo e políticas públicas', 'Maria Exemplo da Silva', '978-65-00000-00-1',
            'https://doi.org/10.00000/conexao.2026.001', 'Obra organizada que reúne 74 capítulos', 'Políticas públicas', 'Seção 1 — Fundamentos',
            'Uma breve história das doenças raras', '21–34', 'SILVA, Maria Exemplo da; PEREIRA, João Exemplo (org.)', 'Silva, M. E., &amp; Pereira, J. E. (Orgs.)',
            'Silva ME, Pereira JE, editores', 'data:image/svg+xml;base64,', 'não substitui a obra', 'template v1.0',
            'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/'] as $needle) {
            $this->assertStringContainsString($needle, $html, "ficha do livro sem: {$needle}");
        }

        $chtml = view('documents.chapter', $generator->chapterData($chapter->fresh(), $payload) + [
            'qr' => $generator->qrDataUri('https://x'), 'generated_at' => now(), 'template_version' => '1.0',
        ])->render();
        foreach (['Uma breve história das doenças raras', 'A brief history of rare diseases', 'Ana Exemplo Rocha', 'Universidade Federal de Goiás, Goiânia/GO',
            'https://orcid.org/0000-0001-5109-3700', '978-65-00000-00-1', '10.00000/conexao.2026.001.01', '21–34',
            'Este capítulo apresenta a evolução', 'História da medicina', 'ROCHA, Ana Exemplo; SILVA, Maria Exemplo da.',
            'Comprar o livro', 'capitulo/01/'] as $needle) {
            $this->assertStringContainsString($needle, $chtml, "ficha do capítulo sem: {$needle}");
        }
    }

    public function test_hash_estavel_e_regeneracao_so_quando_os_dados_mudam(): void
    {
        Storage::fake('local');
        $book = $this->tratado();
        $generator = app(SheetGenerator::class);

        $first = $generator->forBook($book)->fresh();
        $second = $generator->forBook(Book::find(1));

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->hash, $second->hash);
        $this->assertTrue($first->generated_at->equalTo($second->generated_at), 'não regenerou');
        $this->assertSame(1, SyncLog::where('action', 'document.generated')->count());
        $this->assertSame(1, Document::count());

        // Situação e ids do WP não entram no hash: publicar não regenera a ficha.
        $book->forceFill(['status' => 'published', 'wp_post_id' => 999, 'published_at' => now()])->save();
        $this->assertSame($first->hash, $generator->forBook($book->fresh())->hash);

        // Arquivo sumiu: regenera com o mesmo hash.
        Storage::disk('local')->delete($first->path);
        $again = $generator->forBook($book->fresh());
        $this->assertSame($first->hash, $again->hash);
        $this->assertTrue($again->fileExists());

        // Dado público mudou: hash novo, ficha nova, `stale` para o site.
        $again->forceFill(['stale' => false, 'wp_attachment_id' => 77, 'public_url' => 'https://conexaoeditora.com.br/x.pdf'])->save();
        $this->travel(1)->minutes();
        $book->fresh()->update(['subtitle' => 'Edição revista']);
        $third = $generator->forBook(Book::find(1));

        $this->assertNotSame($first->hash, $third->hash);
        $this->assertTrue($third->generated_at->greaterThan($first->generated_at));
        $this->assertTrue($third->stale);
        $this->assertSame(77, $third->wp_attachment_id, 'o anexo antigo continua até o reenvio');
        $this->assertSame(1, Document::count());

        // Forçado: regenera sem mudança.
        $this->travel(1)->minutes();
        $forced = $generator->forBook(Book::find(1), force: true);
        $this->assertSame($third->hash, $forced->hash);
        $this->assertTrue($forced->generated_at->greaterThan($third->generated_at));

        // Capítulo: mudar o capítulo muda a ficha dele e a do livro (sumário).
        $c = $generator->forChapter(Chapter::find(10));
        Chapter::find(10)->update(['last_page' => 40]);
        $this->assertNotSame($c->hash, $generator->forChapter(Chapter::find(10))->hash);
        $this->assertNotSame($forced->hash, $generator->forBook(Book::find(1))->hash);
    }

    public function test_observer_marca_as_fichas_publicadas_como_desatualizadas(): void
    {
        Storage::fake('local');
        $book = $this->tratado();
        $chapter = Chapter::find(10);

        $bookDoc = app(SheetGenerator::class)->forBook($book);
        $chapterDoc = app(SheetGenerator::class)->forChapter($chapter);
        Document::query()->update(['stale' => false, 'wp_attachment_id' => 1, 'public_url' => 'https://conexaoeditora.com.br/x.pdf']);

        // Campo interno: nada muda.
        $chapter->fresh()->update(['ai_evidence' => ['title' => ['page' => 3]]]);
        $book->fresh()->update(['cover_path' => 'capas/1.jpg']);
        $this->assertFalse($bookDoc->fresh()->stale);
        $this->assertFalse($chapterDoc->fresh()->stale);

        // Campo público do capítulo: a ficha dele e a do livro (sumário) ficam stale.
        $chapter->fresh()->update(['title' => 'História das doenças raras']);
        $this->assertTrue($chapterDoc->fresh()->stale);
        $this->assertTrue($bookDoc->fresh()->stale);

        Document::query()->update(['stale' => false]);
        $book->fresh()->update(['abstract' => 'Resumo revisto.']);
        $this->assertTrue($bookDoc->fresh()->stale);
        $this->assertFalse($chapterDoc->fresh()->stale, 'o resumo do livro não entra na ficha do capítulo');

        // Capítulo removido leva a ficha junto.
        Chapter::find(11)->delete();
        app(SheetGenerator::class)->forChapter(Chapter::find(10));
        Chapter::find(10)->delete();
        $this->assertSame(0, Document::where('documentable_type', 'chapter')->count());
    }

    // ------------------------------------------------------------ WordPress

    public function test_job_envia_as_fichas_ao_site_e_grava_anexo_e_url(): void
    {
        Storage::fake('local');
        $book = $this->tratado();
        $book->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/']);
        $this->fakeUploads();

        (new PublishSheets($book->id))->handle(app(SheetGenerator::class));

        Http::assertSentCount(3); // livro + 2 capítulos aprovados
        Http::assertSent(function (Request $request) {
            $sent = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->header('X-Conexao-Signature')[0] === hash_hmac('sha256', $request->header('X-Conexao-Timestamp')[0]."\n".$request->body(), self::SECRET)
                && $sent['hub_book_id'] === 1 && $sent['type'] === 'book_sheet' && $sent['hub_chapter_id'] === null
                && $sent['filename'] === 'ficha-livro-tratado-de-doencas-raras.pdf'
                && $sent['version'] === SheetGenerator::TEMPLATE_VERSION
                && str_starts_with(base64_decode($sent['content_base64']), '%PDF');
        });
        Http::assertSent(fn (Request $r) => ($s = json_decode($r->body(), true))['type'] === 'chapter_sheet' && $s['hub_chapter_id'] === 10
            && $s['filename'] === 'ficha-capitulo-01-uma-breve-historia-das-doencas-raras.pdf');
        Http::assertSent(fn (Request $r) => json_decode($r->body(), true)['hub_chapter_id'] === 11);

        $this->assertSame(3, Document::count());
        $this->assertSame(0, Document::where('stale', true)->count());

        $sheet = Book::find(1)->sheet;
        $this->assertSame(101, $sheet->wp_attachment_id);
        $this->assertSame('https://conexaoeditora.com.br/wp-content/uploads/2026/10/ficha-livro-tratado-de-doencas-raras.pdf', $sheet->public_url);
        $this->assertTrue($sheet->isPublished());
        $this->assertSame('published', $sheet->state());
        $this->assertNotNull(Chapter::find(11)->sheet->public_url);

        $log = SyncLog::where('action', 'document.published')->firstOrFail();
        $this->assertSame(3, $log->context['sent']);

        // Payload passa a levar as fichas reais (o WP substitui a lista a cada PUT).
        $p = Payload::forBook(Book::find(1));
        $this->assertSame([['type' => 'book_sheet', 'attachment_id' => 101, 'url' => $sheet->public_url, 'version' => '1.0', 'generated_at' => $sheet->generated_at->toIso8601String()]], $p['book']['documents']);
        $this->assertSame('chapter_sheet', $p['chapters'][0]['documents'][0]['type']);
        $this->assertSame(10, Chapter::find(10)->sheet->documentable_id);
        $this->assertNotNull($p['chapters'][1]['documents'][0]['url']);

        // Segunda rodada sem mudança: nada sobe.
        (new PublishSheets($book->id))->handle(app(SheetGenerator::class));
        Http::assertSentCount(3);
        $this->assertSame('3 ficha(s) enviada(s) ao site', SyncLog::where('action', 'document.published')->orderBy('id')->first()->message);
        $this->assertStringContainsString('3 já atualizada(s)', SyncLog::where('action', 'document.published')->latest('id')->first()->message);

        // Mudou o capítulo: só as fichas desatualizadas (capítulo e livro) sobem de novo.
        Chapter::find(10)->update(['title' => 'Outra história']);
        (new PublishSheets($book->id))->handle(app(SheetGenerator::class));
        Http::assertSentCount(5);
        $this->assertSame(0, Document::where('stale', true)->count());

        // Forçado: tudo de novo.
        (new PublishSheets($book->id, force: true))->handle(app(SheetGenerator::class));
        Http::assertSentCount(8);
    }

    public function test_falha_no_upload_fica_no_log_e_vai_para_a_fila_de_novo(): void
    {
        Storage::fake('local');
        $book = $this->tratado();
        Http::fake([self::UPLOAD_URL => Http::response('Só PDF é aceito.', 415)]);

        try {
            (new PublishSheets($book->id))->handle(app(SheetGenerator::class));
            $this->fail('erro HTTP não propagou');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 415', $e->getMessage());
        }

        $this->assertSame(3, Document::count(), 'os PDFs ficam gerados mesmo sem subir');
        $this->assertSame(3, Document::where('stale', true)->count());
        $log = SyncLog::where('action', 'document.failed')->firstOrFail();
        $this->assertSame('error', $log->level);
        $this->assertStringContainsString('3 ficha(s) não enviada(s)', $log->message);

        // Sem site configurado: avisa e não gera nada.
        config(['hub.wordpress.url' => null]);
        Document::query()->delete();
        (new PublishSheets($book->id))->handle(app(SheetGenerator::class));
        $this->assertSame(0, Document::count());
        $this->assertSame('warning', SyncLog::where('action', 'document.failed')->latest('id')->first()->level);
    }

    public function test_publicar_o_livro_encadeia_as_fichas_depois_do_put(): void
    {
        Storage::fake('local');
        config(['hub.documents.auto_publish' => true]);
        $book = $this->tratado();
        $book->update(['status' => 'approved']);

        $n = 200;
        Http::fake([
            'conexaoeditora.test/wp-json/conexao-doi/v1/publicacoes/1' => Http::response([
                'book' => ['wp_post_id' => 123, 'url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/'],
                'chapters' => [
                    ['hub_chapter_id' => 10, 'wp_post_id' => 124, 'url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/01/'],
                    ['hub_chapter_id' => 11, 'wp_post_id' => 125, 'url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/02/'],
                ],
                'people' => [],
            ]),
            self::UPLOAD_URL => function () use (&$n) {
                $n++;

                return Http::response(['attachment_id' => $n, 'url' => "https://conexaoeditora.com.br/wp-content/uploads/{$n}.pdf"]);
            },
        ]);

        PublishBook::dispatch($book->id, 7);

        Http::assertSentCount(4);
        Http::assertSentInOrder([
            fn (Request $r) => $r->method() === 'PUT',
            fn (Request $r) => str_ends_with($r->url(), '/documentos'),
            fn (Request $r) => str_ends_with($r->url(), '/documentos'),
            fn (Request $r) => str_ends_with($r->url(), '/documentos'),
        ]);

        $book->refresh();
        $this->assertSame('published', $book->status);
        $this->assertSame(201, $book->sheet->wp_attachment_id);
        $this->assertFalse($book->sheet->stale);

        // A ficha foi gerada já com a URL permanente devolvida pelo PUT (vai no QR e no texto).
        $html = view('documents.book', app(SheetGenerator::class)->bookData($book, Payload::forBook($book)) + [
            'qr' => '', 'generated_at' => now(), 'template_version' => '1.0',
        ])->render();
        $this->assertStringContainsString('https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/', $html);
        $this->assertSame(7, SyncLog::where('action', 'document.published')->firstOrFail()->context['user_id']);

        // Com a chave desligada (como no phpunit.xml), o PublishBook não encadeia nada.
        config(['hub.documents.auto_publish' => false]);
        PublishBook::dispatch($book->id, 7);
        Http::assertSentCount(5);
    }

    public function test_comando_hub_fichas_gera_em_lote_e_publica(): void
    {
        Storage::fake('local');
        $book = $this->tratado();
        $book->chapters()->create(['sequence' => 3, 'title' => 'Rascunho', 'first_page' => 53, 'last_page' => 60, 'status' => 'draft']);

        $this->artisan('hub:fichas', ['book' => 'tratado-de-doencas-raras'])
            ->expectsOutputToContain('Livro 1 — Tratado de Doenças Raras')
            ->expectsOutputToContain('capítulo 2:')
            ->assertSuccessful();

        $this->assertSame(3, Document::count(), 'livro + 2 aprovados; o rascunho fica de fora');
        $this->assertSame(1, Document::where('documentable_type', 'book')->count());

        $this->artisan('hub:fichas', ['book' => '999'])->assertFailed();

        $this->fakeUploads();
        $this->artisan('hub:fichas', ['book' => '1', '--publicar' => true])
            ->expectsOutputToContain('fichas enviadas ao site')
            ->assertSuccessful();
        Http::assertSentCount(3);
        $this->assertSame(0, Document::where('stale', true)->count());
    }

    // ------------------------------------------------------------ Citações

    public function test_citacoes_do_hub_sao_iguais_as_do_plugin_do_site(): void
    {
        $expected = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/citacoes-tratado.json'), true, 512, JSON_THROW_ON_ERROR);
        $payload = $this->examplePayload();

        $book = Citations::forBook($payload['book']);
        $this->assertSame(['abnt', 'apa', 'vancouver', 'bibtex', 'ris'], array_keys($book));
        foreach ($expected['book'] as $format => $text) {
            $this->assertSame($text, $book[$format]['texto'], "livro/{$format}");
        }
        $this->assertSame('ABNT', $book['abnt']['rotulo']);
        $this->assertFalse($book['abnt']['bloco']);
        $this->assertTrue($book['ris']['bloco']);
        $this->assertStringContainsString('<b>Tratado de Doenças Raras: Diagnóstico, manejo e políticas públicas</b>', $book['abnt']['html']);
        $this->assertStringContainsString('<i>Tratado de Doenças Raras', $book['apa']['html']);

        foreach ($payload['chapters'] as $chapter) {
            $got = Citations::forChapter($chapter, $payload['book']);
            foreach ($expected['chapters'][$chapter['hub_id']] as $format => $text) {
                $this->assertSame($text, $got[$format]['texto'], "capítulo {$chapter['hub_id']}/{$format}");
            }
        }

        // Variações: 2ª edição, monografia (sem organizadores), 7 autores, página única, data própria do capítulo.
        $b2 = $payload['book'];
        $b2['edition_number'] = 2;
        $b2['contributors'] = [['name' => 'Ana Rocha', 'sort_name' => 'ROCHA, Ana', 'role' => 'author', 'sequence' => 1]];
        $c2 = $payload['chapters'][0];
        $c2['contributors'] = [];
        for ($i = 1; $i <= 7; $i++) {
            $c2['contributors'][] = ['name' => "Autor {$i}", 'sort_name' => "AUTOR{$i}, Nome Do", 'role' => 'author', 'sequence' => $i];
        }
        $c2['first_page'] = 5;
        $c2['last_page'] = 5;
        $c2['publication_date'] = '2025-01-02';

        foreach (Citations::forBook($b2) as $format => $c) {
            $this->assertSame($expected['book_v2'][$format], $c['texto'], "livro v2/{$format}");
        }
        foreach (Citations::forChapter($c2, $b2) as $format => $c) {
            $this->assertSame($expected['chapter_v2'][$format], $c['texto'], "capítulo v2/{$format}");
        }

        // As mesmas citações saem do payload montado pelo Hub (e não só do JSON de exemplo).
        $hub = Payload::forBook($this->tratado());
        $this->assertSame($expected['book']['abnt'], Citations::forBook($hub['book'])['abnt']['texto']);
        $this->assertSame($expected['chapters'][10]['apa'], Citations::forChapter($hub['chapters'][0], $hub['book'])['apa']['texto']);
    }
}
