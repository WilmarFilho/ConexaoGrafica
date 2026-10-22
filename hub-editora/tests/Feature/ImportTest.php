<?php

namespace Tests\Feature;

use App\Editorial\Import\Ai\AiException;
use App\Editorial\Import\Ai\AiGateway;
use App\Editorial\Import\Ai\AnthropicGateway;
use App\Editorial\Import\Ai\OpenAiGateway;
use App\Editorial\Import\Analyzer;
use App\Editorial\Import\PdfText;
use App\Editorial\Import\Segmenter;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Resources\Responses;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Testing\ClientFake;
use OpenAI\Testing\Enums\OverrideStrategy;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Tests\Support\FakeAiGateway;
use Tests\Support\PdfFixture;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private FakeAiGateway $ai;

    private string $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['hub.ai.key' => 'sk-proj-teste-nunca-usada', 'hub.ai.model' => 'gpt-6-astra', 'hub.ai.gs_bin' => null]);
        $this->ai = new FakeAiGateway;
        $this->app->instance(AiGateway::class, $this->ai);
        $this->pdf = PdfFixture::write();
    }

    protected function tearDown(): void
    {
        @unlink($this->pdf);
        parent::tearDown();
    }

    // ------------------------------------------------------------ extração

    public function test_extracts_text_per_page_and_reads_printed_page_numbers(): void
    {
        $pages = (new PdfText)->extract($this->pdf);

        $this->assertCount(6, $pages);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($pages, 'pdf_page'));
        $this->assertSame([null, null, null, 9, 10, 11], array_column($pages, 'printed_page'), 'capa, ficha e sumário sem número; capítulos com');
        $this->assertStringContainsString(PdfFixture::TITLE, $pages[0]['text']);
        $this->assertStringContainsString('ISBN 978-65-00000-00-9', $pages[1]['text']);
        $this->assertStringContainsString(PdfFixture::CH2, $pages[5]['text']);
        $this->assertSame(6, PdfText::pagesWithText($pages));
    }

    public function test_printed_page_heuristics(): void
    {
        $t = new PdfText;
        $this->assertSame(23, $t->printedPage("Titulo do capitulo\ntexto longo do corpo\n23"));
        $this->assertSame(23, $t->printedPage("— 23 —\nTexto"));
        $this->assertSame(45, $t->printedPage("45 | Genetica nas doencas raras\nTexto do corpo"));
        $this->assertSame(45, $t->printedPage("Genetica nas doencas raras • 45\nTexto"));
        $this->assertNull($t->printedPage("Em 2014 a portaria foi publicada.\nNo total, 199 pacientes foram incluidos no estudo."), 'número dentro de frase não é página');
        $this->assertNull($t->printedPage(''));
    }

    // --------------------------------------------------------- segmentação

    public function test_segments_chapters_by_toc_and_headings(): void
    {
        $pages = (new PdfText)->extract($this->pdf);
        $toc = [
            ['sequence' => 1, 'number' => '1', 'title' => PdfFixture::CH1, 'section' => 'Secao 1', 'printed_page' => 9],
            ['sequence' => 2, 'number' => '2', 'title' => PdfFixture::CH2, 'section' => 'Secao 1', 'printed_page' => 11],
        ];

        $seg = (new Segmenter)->segment($pages, $toc, 13);

        $this->assertSame(-5, $seg['offset'], 'PDF 4 = impressa 9');
        $this->assertSame(3, $seg['anchors']);
        [$c1, $c2] = $seg['chapters'];
        $this->assertSame([4, 5, 9, 10], [$c1['pdf_first_page'], $c1['pdf_last_page'], $c1['first_page'], $c1['last_page']]);
        $this->assertSame([6, 6, 11, 11], [$c2['pdf_first_page'], $c2['pdf_last_page'], $c2['first_page'], $c2['last_page']]);
        $this->assertGreaterThanOrEqual(0.9, $c1['confidence']);
        $this->assertFalse($c1['needs_review']);
        $this->assertSame(4, $c1['evidence']['pdf_page']);
        $this->assertStringContainsString(PdfFixture::CH1, $c1['evidence']['excerpt']);
    }

    public function test_segmenter_falls_back_to_title_search_and_flags_unknown_chapters(): void
    {
        $pages = (new PdfText)->extract($this->pdf);
        // Sumário sem páginas impressas e com um capítulo que não existe no texto.
        $toc = [
            ['sequence' => 1, 'number' => '1', 'title' => PdfFixture::CH1, 'section' => null, 'printed_page' => null],
            ['sequence' => 2, 'number' => '2', 'title' => PdfFixture::CH2, 'section' => null, 'printed_page' => null],
            ['sequence' => 3, 'number' => '3', 'title' => 'Capitulo inexistente sobre outra coisa', 'section' => null, 'printed_page' => null],
        ];

        $seg = (new Segmenter)->segment($pages, $toc);

        $this->assertSame(4, $seg['chapters'][0]['pdf_first_page'], 'achado pelo título, pulando a página de sumário');
        $this->assertSame(6, $seg['chapters'][1]['pdf_first_page']);
        $this->assertSame(0.7, $seg['chapters'][0]['confidence']);
        $this->assertNull($seg['chapters'][2]['pdf_first_page']);
        $this->assertTrue($seg['chapters'][2]['needs_review']);
        $this->assertSame(6, $seg['chapters'][1]['pdf_last_page'], 'último localizado vai até a última página com texto');
    }

    // ------------------------------------------------------------------ job

    public function test_job_walks_all_states_and_builds_the_proposal(): void
    {
        $statuses = [];
        Import::saved(function (Import $i) use (&$statuses) {
            if (end($statuses) !== $i->status) {
                $statuses[] = $i->status;
            }
        });

        $import = Import::receive($this->pdf, 'tratado.pdf');
        $this->assertSame(Import::RECEIVED, $import->status);
        Storage::disk('local')->assertExists($import->file_path);
        $this->assertStringStartsWith('imports/', $import->file_path);

        ProcessImport::dispatchSync($import->id);
        $import->refresh();

        $this->assertSame([Import::RECEIVED, Import::EXTRACTING, Import::SEGMENTING, Import::SUGGESTED], $statuses);
        $this->assertNull($import->error);
        $this->assertSame(6, $import->pages);
        $this->assertSame('gpt-6-astra', $import->model);
        $this->assertSame(Analyzer::PROMPT_VERSION, $import->prompt_version);

        // Chamadas: 1 obra + 2 capítulos; a obra foi como PDF inteiro (6 páginas, sem Ghostscript).
        $this->assertSame(['front_matter', 'chapter', 'chapter'], array_column($this->ai->calls, 'stage'));
        $doc = $this->ai->calls[0]['content'][0];
        $this->assertSame('input_file', $doc['type']);
        $this->assertStringStartsWith('data:application/pdf;base64,', $doc['file_data']);
        $this->assertStringStartsWith('%PDF-', base64_decode(substr($doc['file_data'], strlen('data:application/pdf;base64,'))));
        $this->assertSame('input_text', $this->ai->calls[0]['content'][1]['type']);
        $this->assertStringContainsString('[[p. 9 | PDF 4]]', $this->ai->calls[1]['content'][0]['text'], 'texto do capítulo com marcador de página');
        $this->assertStringNotContainsString(PdfFixture::CH2, $this->ai->calls[1]['content'][0]['text'], 'capítulo 1 não leva o texto do 2');
        $this->assertStringContainsString('Maria Exemplo da Silva — Universidade Federal de Goias', $this->ai->calls[1]['instructions']);

        $r = $import->result;
        $this->assertSame(Analyzer::FORMAT, $r['format']);
        $this->assertSame(['pages' => 6, 'mode' => 'whole_pdf'], $r['source']['front_matter']);
        $this->assertSame(-5, $r['source']['page_offset']);

        // Livro: estrutura do contrato + evidência por campo + campos a revisar.
        $book = $r['book'];
        $this->assertSame(PdfFixture::TITLE, $book['title']);
        $this->assertSame('edited_book', $book['type']);
        $this->assertSame('978-65-00000-00-9', $book['isbn_print']);
        $this->assertSame('978-65-00000-01-6', $book['isbn_ebook']);
        $this->assertSame(2026, $book['publication_year']);
        $this->assertSame(['pdf_page' => 2, 'excerpt' => 'ISBN 978-65-00000-00-9 (impresso)', 'confidence' => 0.95], $book['evidence']['isbn_print']);
        $this->assertSame([['section' => 'Secao 1 - Fundamentos', 'chapters' => [1, 2]]], $book['toc']);
        $this->assertContains('abstract', $book['needs_review'], 'sem resumo impresso: revisor escreve');
        $this->assertNotContains('title', $book['needs_review']);

        $editors = array_values(array_filter($book['contributors'], fn ($c) => $c['role'] === 'editor'));
        $this->assertSame(['SILVA, Maria Exemplo da', 'PEREIRA, Joao Exemplo'], array_column($editors, 'sort_name'));
        $this->assertSame('0000-0002-1825-0097', $editors[0]['orcid']);
        $this->assertSame(['name' => 'Universidade Federal de Goias', 'city' => 'Goiania', 'state' => 'GO', 'country' => 'BR'], $editors[0]['affiliation']);
        $this->assertSame(['affiliation'], $editors[1]['needs_review'], 'sem ORCID nem instituição não pode entrar numa contribuição');

        // Capítulos: páginas impressas, autores ligados à obra, resumo original preservado × sugerido.
        $this->assertCount(2, $r['chapters']);
        [$c1, $c2] = $r['chapters'];
        $this->assertSame('suggested', $c1['status']);
        $this->assertSame([1, '1', 9, 10, 4, 5], [$c1['sequence'], $c1['number'], $c1['first_page'], $c1['last_page'], $c1['pdf_first_page'], $c1['pdf_last_page']]);
        $this->assertSame('pdf', $c1['abstract_source']);
        $this->assertStringStartsWith('Este capitulo apresenta', $c1['abstract_original']);
        $this->assertNull($c1['abstract_ai']);
        $this->assertSame('pdf', $c1['keywords_source']);
        $this->assertSame(['doencas raras', 'historia da medicina', 'legislacao'], $c1['keywords']);
        $this->assertSame('pdf', $c1['evidence']['abstract']['source']);
        $this->assertSame(4, $c1['evidence']['title']['pdf_page']);
        $this->assertSame('segmenter', $c1['evidence']['pages']['source']);
        $this->assertSame([0, 2], [$c1['contributors'][1]['book_contributor_index'], $c1['contributors'][0]['book_contributor_index']], 'Maria é a pessoa 0 da obra, Ana a 2');
        $this->assertSame('Universidade Federal de Goias', $c1['contributors'][0]['affiliation_text'], 'afiliação herdada da lista da obra');
        $this->assertSame([], $c1['needs_review']);
        $this->assertCount(1, $c1['references']);

        $this->assertSame('ai', $c2['abstract_source']);
        $this->assertNull($c2['abstract_original']);
        $this->assertNotNull($c2['abstract_ai']);
        $this->assertSame('ai', $c2['evidence']['abstract']['source']);
        $this->assertSame('ai', $c2['keywords_source']);
        $this->assertSame(3, $c2['contributors'][0]['book_contributor_index'], '"Carlos E. Lima" liga a "Carlos Exemplo Lima"');
        $this->assertSame([11, 11], [$c2['first_page'], $c2['last_page']]);
        $this->assertSame(['sequence' => 2, 'pdf_source' => 0, 'ai' => 1], ['sequence' => $c2['sequence'], 'pdf_source' => (int) ($c2['abstract_source'] === 'pdf'), 'ai' => (int) ($c2['abstract_source'] === 'ai')]);

        // Pessoas únicas para a etapa "Autores" da revisão.
        $this->assertSame(['Maria Exemplo da Silva', 'Joao Exemplo Pereira', 'Ana Exemplo Rocha', 'Carlos Exemplo Lima'], array_column($r['people'], 'name'));
        $this->assertSame(['editor', 'author'], $r['people'][0]['roles']);
        $this->assertSame([1], $r['people'][0]['chapters']);

        $this->assertSame(['chapters' => 2, 'analyzed' => 2, 'failed' => 0, 'needs_review' => 0, 'abstracts_from_pdf' => 1, 'abstracts_from_ai' => 1], $r['summary']);

        // Uso e custo (gpt-6-astra): 3 chamadas × (1.000 × US$10 + 200 × US$50) / 1M = US$ 0,06.
        $u = $import->usage;
        $this->assertSame([3, 3000, 600], [$u['calls'], $u['input_tokens'], $u['output_tokens']]);
        $this->assertEqualsWithDelta(0.06, $u['cost_usd'], 0.0001);
        $this->assertTrue($u['price_known']);
        $this->assertSame(1, $u['by_stage']['front_matter']['calls']);
        $this->assertSame(2, $u['by_stage']['chapter']['calls']);
        $this->assertEqualsWithDelta(0.02, $c1['usage']['cost_usd'], 0.0001);

        // Auditoria de cada etapa, com o import como sujeito. Nada em books/chapters (não existem nesta frente).
        $actions = SyncLog::where('subject_type', (new Import)->getMorphClass())->where('subject_id', $import->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame(['import.extracted', 'import.front_matter', 'import.segmented', 'import.suggested'], $actions);
        $this->assertStringContainsString('US$ 0,06', SyncLog::where('action', 'import.suggested')->first()->message);
    }

    public function test_api_failure_marks_error_and_the_import_can_be_reprocessed(): void
    {
        $this->ai->failures[1] = new \RuntimeException('cURL error 28: connection timed out');
        $import = Import::receive($this->pdf);

        ProcessImport::dispatchSync($import->id);
        $import->refresh();

        $this->assertSame(Import::ERROR, $import->status);
        $this->assertStringContainsString('connection timed out', $import->error);
        $this->assertSame(6, $import->pages, 'a extração já tinha acontecido');
        $this->assertNull($import->result);
        $this->assertSame(1, SyncLog::where('action', 'import.failed')->where('level', 'error')->count());
        $this->assertTrue($import->canReprocess());

        // Reprocessar: volta a received e o job roda de novo (a API agora responde).
        $this->ai->failures = [];
        $import->reprocess();
        $this->assertSame(Import::RECEIVED, $import->fresh()->status);
        ProcessImport::dispatchSync($import->id);

        $this->assertSame(Import::SUGGESTED, $import->fresh()->status);
        $this->assertNull($import->fresh()->error);
    }

    public function test_pdf_without_text_layer_fails_with_a_clear_message(): void
    {
        $blank = sys_get_temp_dir().'/hub-blank-'.uniqid().'.pdf';
        file_put_contents($blank, PdfFixture::build([['number' => null, 'lines' => []], ['number' => null, 'lines' => []]]));
        $import = Import::receive($blank);
        @unlink($blank);

        ProcessImport::dispatchSync($import->id);

        $this->assertSame(Import::ERROR, $import->fresh()->status);
        $this->assertStringContainsString('camada de texto', $import->fresh()->error);
        $this->assertSame([], $this->ai->calls, 'nenhuma chamada à IA sem texto');
    }

    public function test_chapter_refusal_or_bad_json_is_kept_for_review_without_failing_the_import(): void
    {
        $this->ai->failures[3] = new AiException(AiException::REFUSAL, 'O modelo recusou a solicitação (general_harms).');
        $import = Import::receive($this->pdf);

        ProcessImport::dispatchSync($import->id);
        $r = $import->fresh()->result;

        $this->assertSame(Import::SUGGESTED, $import->fresh()->status);
        $this->assertSame('suggested', $r['chapters'][0]['status']);
        $this->assertSame('failed', $r['chapters'][1]['status']);
        $this->assertStringContainsString('recusou', $r['chapters'][1]['error']);
        $this->assertSame([11, 11], [$r['chapters'][1]['first_page'], $r['chapters'][1]['last_page']], 'páginas da segmentação ficam');
        $this->assertEqualsCanonicalizing(['title', 'authors', 'abstract', 'keywords'], $r['chapters'][1]['needs_review']);
        $this->assertSame(1, $r['summary']['failed']);
        $this->assertSame(1, SyncLog::where('action', 'import.chapter')->where('level', 'warning')->count());
    }

    public function test_long_chapters_are_split_into_parts_and_consolidated(): void
    {
        config(['hub.ai.max_chapter_tokens' => 2000]); // mínimo: ~6.400 caracteres por parte
        $pages = [];
        for ($p = 1; $p <= 6; $p++) {
            $pages[] = ['pdf_page' => $p, 'printed_page' => 20 + $p, 'text' => ($p === 1 ? PdfFixture::CH2."\n" : '').str_repeat("Texto da pagina {$p} do capitulo longo. ", 90)];
        }
        $seg = ['sequence' => 1, 'number' => '1', 'section' => null, 'title' => PdfFixture::CH2, 'pdf_first_page' => 1, 'pdf_last_page' => 6,
            'first_page' => 21, 'last_page' => 26, 'confidence' => 0.9, 'needs_review' => false, 'evidence' => null];

        $analyzer = new Analyzer($this->ai);
        $chapter = $analyzer->chapter($pages, $seg, [], 'Livro');

        $stages = array_column($this->ai->calls, 'stage');
        $this->assertGreaterThanOrEqual(3, count($stages));
        $this->assertSame('chapter', end($stages), 'última chamada consolida');
        $this->assertSame(count($stages) - 1, count(array_filter($stages, fn ($s) => $s === 'chapter_part')));
        $this->assertStringContainsString('parte 1 de', $this->ai->calls[0]['instructions']);
        $this->assertStringContainsString('key_points', $this->ai->calls[count($stages) - 1]['content'][0]['text'], 'consolidação recebe as extrações parciais');
        $this->assertSame('suggested', $chapter['status']);
        $this->assertSame(count($stages), $chapter['usage']['calls']);
        $this->assertSame(['chapter_part', 'chapter_consolidate'], array_keys($analyzer->usage->toArray()['by_stage']));
    }

    public function test_front_matter_slice_uses_ghostscript_when_available(): void
    {
        config(['hub.ai.gs_bin' => 'gs', 'hub.ai.front_matter_pages' => 2]);
        Process::fake(); // "roda" mas não gera arquivo: o código cai no PDF inteiro
        $import = Import::receive($this->pdf);

        ProcessImport::dispatchSync($import->id);

        Process::assertRan(fn ($p) => is_array($p->command) && $p->command[0] === 'gs' && in_array('-dLastPage=2', $p->command, true) && in_array('-dSAFER', $p->command, true));
        $this->assertSame('whole_pdf', $import->fresh()->result['source']['front_matter']['mode']);
        $this->assertSame(Import::SUGGESTED, $import->fresh()->status);
    }

    // -------------------------------------------------------------- comando

    public function test_command_without_key_only_warns(): void
    {
        config(['hub.ai.key' => null]);

        $this->artisan('hub:importar-pdf', ['arquivo' => $this->pdf])->expectsOutputToContain('não configurado')->assertSuccessful();

        $this->assertSame(0, Import::count());
        $this->assertSame([], $this->ai->calls);
    }

    public function test_command_runs_end_to_end_and_prints_the_summary(): void
    {
        $this->artisan('hub:importar-pdf', ['arquivo' => $this->pdf, '--sincrono' => true, '--livro' => 7])
            ->expectsOutputToContain(PdfFixture::TITLE)
            ->expectsOutputToContain('Custo estimado')
            ->assertSuccessful();

        $import = Import::first();
        $this->assertSame(Import::SUGGESTED, $import->status);
        $this->assertSame(7, $import->book_id);
        $this->assertSame(1, SyncLog::where('action', 'import.received')->count());

        $this->artisan('hub:importar-pdf', ['arquivo' => '/nao/existe.pdf'])->assertFailed();
    }

    // ------------------------------------------- gateway do SDK (ClientFake)

    public function test_sdk_gateway_sends_structured_request_and_reads_usage(): void
    {
        $fake = new ClientFake([
            CreateResponse::fake([
                'status' => 'completed', 'incomplete_details' => null, 'model' => 'gpt-6-astra',
                'output' => [[
                    'id' => 'msg_1', 'type' => 'message', 'status' => 'completed', 'role' => 'assistant', 'phase' => null,
                    'content' => [['type' => 'output_text', 'text' => '{"title": "Tratado de Doencas Raras", "pages": 993}', 'annotations' => []]],
                ]],
                'usage' => ['input_tokens' => 1200, 'input_tokens_details' => ['cached_tokens' => 300], 'output_tokens' => 57, 'output_tokens_details' => ['reasoning_tokens' => 20], 'total_tokens' => 1257],
            ], null, OverrideStrategy::Replace),
            CreateResponse::fake([
                'status' => 'completed', 'incomplete_details' => null,
                'output' => [[
                    'id' => 'msg_2', 'type' => 'message', 'status' => 'completed', 'role' => 'assistant', 'phase' => null,
                    'content' => [['type' => 'refusal', 'refusal' => 'Não posso ajudar com isso.']],
                ]],
            ], null, OverrideStrategy::Replace),
            CreateResponse::fake([
                'status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'],
                'output' => [],
            ], null, OverrideStrategy::Replace),
        ]);
        $gateway = new OpenAiGateway($fake);
        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'pages' => ['type' => 'integer']], 'required' => ['title', 'pages'], 'additionalProperties' => false];
        $pdf = 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 fake');

        $result = $gateway->structured('Instrucao fixa', [
            ['type' => 'input_file', 'filename' => 'inicio.pdf', 'file_data' => $pdf],
            ['type' => 'input_text', 'text' => 'Extraia.'],
        ], 'front_matter', $schema, 4096);

        $this->assertSame(['title' => 'Tratado de Doencas Raras', 'pages' => 993], $result->data);
        $this->assertSame(['input_tokens' => 1200, 'output_tokens' => 57, 'cached_input_tokens' => 300, 'reasoning_tokens' => 20], $result->usage);
        $this->assertSame('completed', $result->status);
        $this->assertSame('gpt-6-astra', $result->model);

        // O que foi para a API: Responses API com schema strict, instruções, PDF inline, sem armazenar.
        $fake->assertSent(Responses::class, function (string $method, array $p) use ($schema, $pdf): bool {
            return $method === 'create'
                && $p['model'] === 'gpt-6-astra'
                && $p['instructions'] === 'Instrucao fixa'
                && $p['max_output_tokens'] === 4096
                && $p['reasoning'] === ['effort' => 'medium']
                && $p['text']['format'] === ['type' => 'json_schema', 'name' => 'front_matter', 'schema' => $schema, 'strict' => true]
                && $p['store'] === false
                && $p['input'][0]['role'] === 'user'
                && $p['input'][0]['content'][0] === ['type' => 'input_file', 'filename' => 'inicio.pdf', 'file_data' => $pdf]
                && $p['input'][0]['content'][1] === ['type' => 'input_text', 'text' => 'Extraia.'];
        });

        try {
            $gateway->structured('x', [['type' => 'input_text', 'text' => 'y']], 'chapter', $schema, 1024);
            $this->fail('recusa deveria virar AiException');
        } catch (AiException $e) {
            $this->assertSame(AiException::REFUSAL, $e->reason);
            $this->assertStringContainsString('recusou', $e->getMessage());
        }

        try {
            $gateway->structured('x', [['type' => 'input_text', 'text' => 'y']], 'chapter', $schema, 1024);
            $this->fail('resposta cortada deveria virar AiException');
        } catch (AiException $e) {
            $this->assertSame(AiException::TRUNCATED, $e->reason);
        }
        $fake->assertSent(Responses::class, 3);
    }

    public function test_sdk_gateway_waits_and_retries_when_the_account_is_rate_limited(): void
    {
        Sleep::fake();
        $ok = CreateResponse::fake([
            'status' => 'completed', 'incomplete_details' => null, 'model' => 'gpt-6-astra',
            'output' => [[
                'id' => 'msg_1', 'type' => 'message', 'status' => 'completed', 'role' => 'assistant', 'phase' => null,
                'content' => [['type' => 'output_text', 'text' => '{"title": "Ok", "pages": 1}', 'annotations' => []]],
            ]],
        ], null, OverrideStrategy::Replace);
        $fake = new ClientFake([
            new RateLimitException(new PsrResponse(429, ['retry-after' => '7'])),
            new RateLimitException(new PsrResponse(429, ['x-ratelimit-reset-tokens' => '1m30s'])),
            new RateLimitException(new PsrResponse(429)),
            $ok,
        ]);
        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'pages' => ['type' => 'integer']], 'required' => ['title', 'pages'], 'additionalProperties' => false];

        $result = (new OpenAiGateway($fake))->structured('x', [['type' => 'input_text', 'text' => 'y']], 'chapter', $schema, 1024);

        $this->assertSame('Ok', $result->data['title']);
        $fake->assertSent(Responses::class, 4);
        // 8 s (retry-after 7 + 1), 91 s (reset de 1m30s + 1), 60 s (terceira tentativa sem dica: 15·2²)
        Sleep::assertSequence([Sleep::for(8)->seconds(), Sleep::for(91)->seconds(), Sleep::for(60)->seconds()]);
    }

    public function test_sdk_gateway_gives_up_after_the_retry_limit(): void
    {
        Sleep::fake();
        $fake = new ClientFake(array_fill(0, OpenAiGateway::MAX_ATTEMPTS, new RateLimitException(new PsrResponse(429))));
        $schema = ['type' => 'object', 'properties' => ['t' => ['type' => 'string']], 'required' => ['t'], 'additionalProperties' => false];

        try {
            (new OpenAiGateway($fake))->structured('x', [['type' => 'input_text', 'text' => 'y']], 'chapter', $schema, 1024);
            $this->fail('deveria desistir');
        } catch (RateLimitException) {
            $fake->assertSent(Responses::class, OpenAiGateway::MAX_ATTEMPTS);
            Sleep::assertSleptTimes(OpenAiGateway::MAX_ATTEMPTS - 1);
        }
        $this->assertSame(0, OpenAiGateway::durationToSeconds(''));
        $this->assertSame(1, OpenAiGateway::durationToSeconds('250ms'));
        $this->assertSame(360, OpenAiGateway::durationToSeconds('6m0s'));
        $this->assertSame(2, OpenAiGateway::durationToSeconds('1.5s'));
    }

    public function test_failed_import_resumes_without_paying_again_for_what_was_done(): void
    {
        // Chamada 1 = obra, 2 = capítulo 1, 3 = capítulo 2 (falha no meio, como o limite da conta).
        $this->ai->failures[3] = new \RuntimeException('Request rate limit has been exceeded.');
        $import = Import::receive($this->pdf);

        ProcessImport::dispatchSync($import->id);
        $import->refresh();

        $this->assertSame(Import::ERROR, $import->status);
        $this->assertTrue($import->canResume(), 'leitura da obra e segmentação ficaram guardadas');
        $this->assertSame('suggested', $import->result['chapters'][0]['status']);
        $this->assertSame(2, $import->usage['calls']);
        $callsBefore = count($this->ai->calls);

        $this->ai->failures = [];
        $import->retry();
        $this->assertSame(Import::RECEIVED, $import->fresh()->status);
        $this->assertNotNull($import->fresh()->checkpoint, 'retomar não apaga o ponto de retomada');
        ProcessImport::dispatchSync($import->id);
        $import->refresh();

        $this->assertSame(Import::SUGGESTED, $import->status);
        $newStages = array_column(array_slice($this->ai->calls, $callsBefore), 'stage');
        $this->assertNotContains('front_matter', $newStages, 'a obra não é lida de novo');
        $this->assertCount(1, $newStages, 'só o capítulo que faltava');
        $this->assertSame(['suggested', 'suggested'], array_column($import->result['chapters'], 'status'));
        $this->assertSame(3, $import->usage['calls'], 'o custo soma as duas execuções');
        $this->assertSame(1, SyncLog::where('action', 'import.resumed')->count());

        // Reprocessar do zero apaga o ponto de retomada e lê a obra de novo.
        $import->reprocess();
        $this->assertNull($import->fresh()->checkpoint);
    }

    // ------------------------------------------- gateway da Anthropic (SDK com HTTP simulado)

    private function anthropicMessage(string $text, string $stop = 'end_turn'): PsrResponse
    {
        return new PsrResponse(200, ['content-type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => $stop, 'stop_sequence' => null, 'stop_details' => null, 'container' => null,
            'usage' => ['input_tokens' => 900, 'output_tokens' => 120, 'cache_read_input_tokens' => 300, 'cache_creation_input_tokens' => 0],
        ]));
    }

    public function test_anthropic_gateway_sends_pdf_and_schema_and_reads_usage(): void
    {
        config(['hub.ai.provider' => 'anthropic', 'hub.ai.key' => 'sk-ant-teste', 'hub.ai.model' => 'claude-opus-5-5', 'hub.ai.effort' => 'medium']);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new PsrResponse(429, ['retry-after' => '0']),
            $this->anthropicMessage('{"title": "Tratado de Doencas Raras", "pages": 991}'),
            $this->anthropicMessage('', 'refusal'),
            $this->anthropicMessage('{"title": "x"', 'max_tokens'),
        ]));
        $stack->push(Middleware::history($history));
        $gateway = new AnthropicGateway(null, new Guzzle(['handler' => $stack]), ['initialRetryDelay' => 0.0, 'maxRetryDelay' => 0.0]);
        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'pages' => ['type' => 'integer']], 'required' => ['title', 'pages'], 'additionalProperties' => false];
        $pdf = 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 fake');

        $result = $gateway->structured('Instrucao fixa', [
            ['type' => 'input_file', 'filename' => 'inicio.pdf', 'file_data' => $pdf],
            ['type' => 'input_text', 'text' => 'Extraia.'],
        ], 'front_matter', $schema, 4096);

        $this->assertSame(['title' => 'Tratado de Doencas Raras', 'pages' => 991], $result->data);
        $this->assertSame(['input_tokens' => 1200, 'output_tokens' => 120, 'cached_input_tokens' => 300, 'reasoning_tokens' => 0], $result->usage);
        $this->assertCount(2, $history, 'o 429 foi repetido pelo SDK');

        $body = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame(4096, $body['max_tokens']);
        $this->assertStringStartsWith('Instrucao fixa', $body['system'][0]['text']);
        $this->assertSame(['type' => 'ephemeral'], $body['system'][0]['cache_control']);
        $this->assertSame(['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $schema]], $body['output_config']);
        $this->assertSame(['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode('%PDF-1.4 fake')], $body['messages'][0]['content'][0]['source']);
        $this->assertSame('document', $body['messages'][0]['content'][0]['type']);
        $this->assertSame(['type' => 'text', 'text' => 'Extraia.'], $body['messages'][0]['content'][1]);
        $this->assertSame('Bearer sk-ant-teste', $history[1]['request']->getHeaderLine('authorization'), 'o SDK v1 envia a chave como Bearer');

        try {
            $gateway->structured('x', [['type' => 'input_text', 'text' => 'y']], 'chapter', $schema, 1024);
            $this->fail('recusa deveria virar AiException');
        } catch (AiException $e) {
            $this->assertSame(AiException::REFUSAL, $e->reason);
        }
        try {
            $gateway->structured('x', [['type' => 'input_text', 'text' => 'y']], 'chapter', $schema, 1024);
            $this->fail('resposta cortada deveria virar AiException');
        } catch (AiException $e) {
            $this->assertSame(AiException::TRUNCATED, $e->reason);
        }
    }

    public function test_anthropic_gateway_sends_schemas_without_unions_and_restores_nulls(): void
    {
        $schema = \App\Editorial\Import\Schemas::frontMatter();
        $strict = AnthropicGateway::withoutNulls($schema);

        $unions = 0;
        $walk = function ($s) use (&$walk, &$unions) {
            if (! is_array($s)) {
                return;
            }
            if (is_array($s['type'] ?? null)) {
                $unions++;
            }
            foreach ($s['properties'] ?? [] as $p) {
                $walk($p);
            }
            $walk($s['items'] ?? null);
        };
        $walk($strict);
        $this->assertSame(0, $unions, 'nenhuma união/nulo vai para a Anthropic (limite de 16)');
        $this->assertSame($schema['required'], $strict['required'], 'continua tudo obrigatório (limite de 24 opcionais)');

        $data = AnthropicGateway::restoreNulls([
            'title' => 'Atualidades em Ultrassonografia', 'subtitle' => '', 'edition_number' => 0, 'pages' => 156,
            'contributors' => [['name' => 'Leonardo de Souza Piber', 'orcid' => '', 'role' => 'author']],
            'toc' => [['section' => '', 'chapters' => [['number' => '1', 'title' => 'Fígado', 'authors' => [], 'printed_page' => 0]]]],
        ], $schema);

        $this->assertNull($data['subtitle']);
        $this->assertNull($data['edition_number']);
        $this->assertSame(156, $data['pages']);
        $this->assertSame('Atualidades em Ultrassonografia', $data['title']);
        $this->assertNull($data['toc'][0]['section']);
        $this->assertNull($data['toc'][0]['chapters'][0]['printed_page']);
        $this->assertSame('1', $data['toc'][0]['chapters'][0]['number']);
        $this->assertSame([], $data['toc'][0]['chapters'][0]['authors'], 'lista vazia continua lista');
    }

    public function test_provider_switch_picks_the_gateway_and_the_right_key(): void
    {
        config(['hub.ai.provider' => 'anthropic']);
        $this->app->forgetInstance(AiGateway::class);
        $this->app->offsetUnset(AiGateway::class);
        (new \App\Providers\AppServiceProvider($this->app))->register();
        $this->assertInstanceOf(AnthropicGateway::class, $this->app->make(AiGateway::class));

        config(['hub.ai.provider' => 'openai']);
        $this->assertInstanceOf(OpenAiGateway::class, $this->app->make(AiGateway::class));
    }

    public function test_sdk_gateway_is_not_contacted_without_a_key(): void
    {
        config(['hub.ai.key' => null]);
        $this->expectExceptionMessage('OPENAI_API_KEY não configurada');

        (new OpenAiGateway)->structured('x', [['type' => 'input_text', 'text' => 'y']], 'chapter', ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false], 1024);
    }

    // ---------------------------------------------------------------- apoio

    public function test_sort_name_isbn_and_orcid_helpers(): void
    {
        $a = new Analyzer($this->ai);
        $this->assertSame('SILVA, Maria Exemplo da', Analyzer::sortName('Maria Exemplo da Silva'));
        $this->assertSame('FERREIRA FILHO, Wilmar José Alves', Analyzer::sortName('Wilmar José Alves Ferreira Filho'));
        $this->assertSame('ROCHA, Ana', Analyzer::sortName('Ana Rocha'));
        $this->assertSame('MADONNA', Analyzer::sortName('Madonna'));

        $this->assertSame('978-65-00000-00-9', $a->isbn('978-65-00000-00-9'));
        $this->assertSame('9788533302273', $a->isbn('9788533302273'));
        $this->assertNull($a->isbn('978-65-00000-00-2'), 'dígito verificador errado');
        $this->assertNull($a->isbn('85-333-0227-X'), 'ISBN-10 não entra');

        $this->assertSame('0000-0002-1825-0097', $a->orcid('https://orcid.org/0000-0002-1825-0097'));
        $this->assertSame('0000-0001-5109-3700', $a->orcid('0000000151093700'));
        $this->assertNull($a->orcid('0000-0002-1825-0098'), 'checksum inválido');
        $this->assertNull($a->orcid(null));
    }
}
