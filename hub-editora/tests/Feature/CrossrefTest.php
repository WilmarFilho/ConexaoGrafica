<?php

namespace Tests\Feature;

use App\Crossref\CrossrefException;
use App\Crossref\DepositService;
use App\Crossref\Validator;
use App\Crossref\XmlBuilder;
use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Frente D do Conexão DOI: XML 5.4.0, validação no XSD local, depósito e
 * leitura de resultado na Crossref, tudo com Http::fake (nenhum depósito real).
 */
class CrossrefTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS_HTML = '<html><head><title>SUCCESS</title></head><body><h2>SUCCESS</h2><p>Your batch submission was successfully received.</p></body></html>';

    private const FAILURE_HTML = '<html><head><title>FAILURE</title></head><body><h2>FAILURE</h2><p>Login ID and/or password are incorrect, or you are not authorized to deposit for this prefix.</p></body></html>';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hub.crossref.env' => 'test',
            'hub.crossref.login' => 'doi@conexaoeditora.com.br/bcfg', // formato e-mail/role da Crossref
            'hub.crossref.password' => 'segredo-de-teste',
            'hub.crossref.prefix' => '10.00000',
            'hub.crossref.depositor_name' => 'Conexão Editora',
            'hub.crossref.depositor_email' => 'doi@conexaoeditora.com.br',
            'hub.crossref.registrant' => 'Conexão Editora',
            'hub.crossref.landing_base' => 'https://conexaoeditora.com.br',
        ]);

        Storage::fake('local');
        Sleep::fake(); // os retries do cliente não esperam de verdade
        Http::preventStrayRequests(); // nenhuma chamada real à Crossref nos testes
    }

    // ------------------------------------------------------------ XML e XSD

    public function test_xml_do_exemplo_passa_no_xsd_local(): void
    {
        $xml = XmlBuilder::fromConfig()->build($this->payload(), 'conexao-1-teste');

        $this->assertSame([], (new Validator)->errors($xml), 'XML do exemplo deve ser válido');

        $this->assertStringContainsString('<doi_batch xmlns="http://www.crossref.org/schema/5.4.0"', $xml);
        $this->assertStringContainsString('version="5.4.0"', $xml);
        $this->assertStringContainsString('<doi_batch_id>conexao-1-teste</doi_batch_id>', $xml);
        $this->assertStringContainsString('<email_address>doi@conexaoeditora.com.br</email_address>', $xml);
        $this->assertStringContainsString('<book book_type="edited_book">', $xml);
        $this->assertStringContainsString('<book_metadata language="pt">', $xml);
        $this->assertStringNotContainsString('book_series_metadata', $xml);

        // Organizadores: papel editor, primeiro = first, nome separado pela grafia do sort_name.
        $this->assertStringContainsString('<person_name sequence="first" contributor_role="editor">', $xml);
        $this->assertStringContainsString('<given_name>Maria Exemplo da</given_name>', $xml);
        $this->assertStringContainsString('<surname>Silva</surname>', $xml);
        $this->assertStringContainsString('<institution_name>Universidade Federal de Goiás</institution_name>', $xml);
        $this->assertStringContainsString('<ORCID authenticated="false">https://orcid.org/0000-0002-1825-0097</ORCID>', $xml);

        $this->assertStringContainsString('<title>Tratado de Doenças Raras</title>', $xml);
        $this->assertStringContainsString('<subtitle>Diagnóstico, manejo e políticas públicas</subtitle>', $xml);
        $this->assertStringContainsString('<jats:abstract xml:lang="pt">', $xml);
        $this->assertStringContainsString('<edition_number>1</edition_number>', $xml);

        // format=both: uma data por mídia e um ISBN por mídia.
        $this->assertSame(2, substr_count($this->bookMetadata($xml), '<publication_date media_type='));
        $this->assertStringContainsString('<publication_date media_type="online">', $xml);
        $this->assertStringContainsString('<month>03</month>', $xml);
        $this->assertStringContainsString('<day>15</day>', $xml);
        $this->assertStringContainsString('<isbn media_type="print">978-65-00000-00-1</isbn>', $xml);
        $this->assertStringContainsString('<isbn media_type="electronic">978-65-00000-01-8</isbn>', $xml);
        $this->assertStringContainsString('<publisher_place>Goiânia</publisher_place>', $xml);
        $this->assertStringContainsString('<doi>10.00000/conexao.2026.001</doi>', $xml);
        $this->assertStringContainsString('<resource>https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/</resource>', $xml);

        // Capítulos.
        $this->assertSame(2, substr_count($xml, '<content_item component_type="chapter"'));
        $this->assertStringContainsString('<component_number>1</component_number>', $xml);
        $this->assertStringContainsString('<first_page>21</first_page>', $xml);
        $this->assertStringContainsString('<last_page>34</last_page>', $xml);
        $this->assertStringContainsString('<doi>10.00000/conexao.2026.001.01</doi>', $xml);
        $this->assertStringContainsString('<resource>https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/01/</resource>', $xml);
        $this->assertStringContainsString('<unstructured_citation>BRASIL. Ministério da Saúde. Portaria nº 199', $xml);
        $this->assertSame(1, substr_count($xml, '<citation_list>'), 'só o capítulo 1 tem referências');
    }

    public function test_landing_url_do_payload_prevalece_sobre_o_slug(): void
    {
        $payload = $this->payload();
        $payload['book']['landing_url'] = 'https://conexaoeditora.com.br/livro/tratado/';
        $payload['chapters'][0]['landing_url'] = 'https://conexaoeditora.com.br/livro/tratado/capitulo/01/';

        $xml = XmlBuilder::fromConfig()->build($payload, 'conexao-1-teste');

        $this->assertStringContainsString('<resource>https://conexaoeditora.com.br/livro/tratado/</resource>', $xml);
        $this->assertStringContainsString('<resource>https://conexaoeditora.com.br/livro/tratado/capitulo/01/</resource>', $xml);
        $this->assertSame([], (new Validator)->errors($xml));
    }

    public function test_livro_de_serie_com_issn_usa_book_series_metadata(): void
    {
        $payload = $this->payload();
        $payload['book']['series'] = ['title' => 'Coleção Saúde Pública', 'issn' => '1234-5679'];
        $payload['book']['volume'] = '3';
        $payload['book']['references'] = [['text' => 'Obra citada pelo livro.', 'doi' => '10.1000/xyz123']];

        $xml = XmlBuilder::fromConfig()->build($payload, 'conexao-1-serie');

        $this->assertSame([], (new Validator)->errors($xml), 'variação de série deve ser válida');
        $this->assertStringContainsString('<book_series_metadata language="pt">', $xml);
        $this->assertStringContainsString('<series_metadata>', $xml);
        $this->assertStringContainsString('<title>Coleção Saúde Pública</title>', $xml);
        $this->assertStringContainsString('<issn media_type="print">1234-5679</issn>', $xml);
        $this->assertStringContainsString('<volume>3</volume>', $xml);
        $this->assertStringContainsString('<doi>10.1000/xyz123</doi>', $xml);
        $this->assertStringNotContainsString('<book_metadata', $xml);
    }

    public function test_payload_sem_titulo_falha_com_mensagem_clara(): void
    {
        $payload = $this->payload();
        $payload['book']['title'] = '';
        $payload['chapters'][1]['title'] = null;

        try {
            XmlBuilder::fromConfig()->build($payload, 'conexao-1-x');
            $this->fail('deveria recusar o payload sem título');
        } catch (CrossrefException $e) {
            $this->assertStringContainsString('O livro precisa de título.', $e->getMessage());
            $this->assertContains('Capítulo 2 sem título.', $e->errors);
        }
    }

    public function test_payload_sem_isbn_nem_doi_valido_lista_todos_os_problemas(): void
    {
        $payload = $this->payload();
        $payload['book']['isbn_print'] = null;
        $payload['book']['isbn_ebook'] = null;
        $payload['book']['doi'] = 'conexao.2026.001';

        $this->expectException(CrossrefException::class);
        $this->expectExceptionMessage('ISBN');

        XmlBuilder::fromConfig()->build($payload, 'conexao-1-x');
    }

    public function test_validador_devolve_erros_legiveis_em_portugues(): void
    {
        $xml = XmlBuilder::fromConfig()->build($this->payload(), 'conexao-1-teste');

        // Elemento fora de ordem/desconhecido e ORCID fora do padrão.
        $quebrado = str_replace('<surname>Silva</surname>', '<sobrenome>Silva</sobrenome>', $xml);
        $quebrado = str_replace('https://orcid.org/0000-0001-5109-3700', '0000-0001-5109-3700', $quebrado);

        $errors = (new Validator)->errors($quebrado);

        $this->assertNotEmpty($errors);
        $this->assertMatchesRegularExpression('/^Linha \d+: Elemento \'sobrenome\': não é esperado aqui/u', $errors[0]);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, "'ORCID'") && (str_contains($e, 'formato exigido') || str_contains($e, 'valor válido'))), implode("\n", $errors));
        $this->assertStringNotContainsString('{http://', implode(' ', $errors), 'namespaces ficam fora da mensagem');

        $malformado = (new Validator)->errors('<doi_batch>');
        $this->assertCount(1, $malformado);
        $this->assertStringStartsWith('XML malformado', $malformado[0]);
        $this->assertTrue((new Validator)->isValid($xml));
    }

    public function test_separacao_de_nome_e_sobrenome(): void
    {
        $this->assertSame(['Maria Exemplo da', 'Silva'], XmlBuilder::splitName('Maria Exemplo da Silva', 'SILVA, Maria Exemplo da'));
        $this->assertSame(['João', 'Souza Neto'], XmlBuilder::splitName('João Souza Neto', 'SOUZA NETO, João'));
        $this->assertSame(['Ana Exemplo', 'Rocha'], XmlBuilder::splitName('Ana Exemplo Rocha', null));
        $this->assertSame(['Carlos', 'Pereira'], XmlBuilder::splitName('Carlos Lima', 'PEREIRA, Carlos'), 'sort_name que não bate com o nome: vale o sort_name');
        $this->assertSame(['', 'Madonna'], XmlBuilder::splitName('Madonna', null));
        $this->assertSame(['Carlos', 'Pereira'], XmlBuilder::splitName('', 'PEREIRA, Carlos'), 'só sort_name: sobrenome em caixa normal');
    }

    // ------------------------------------------------------------ depósito

    public function test_create_grava_xml_checksum_e_um_item_por_doi(): void
    {
        $deposit = DepositService::make()->create($this->payload(), 1);

        $this->assertSame(Deposit::QUEUED, $deposit->status);
        $this->assertSame('test', $deposit->environment);
        $this->assertSame(1, $deposit->book_id);
        $this->assertStringStartsWith('conexao-1-', $deposit->batch_id);
        $this->assertStringStartsWith('crossref/', $deposit->xml_path);
        Storage::disk('local')->assertExists($deposit->xml_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($deposit->xml_path)), $deposit->checksum);

        $this->assertSame(
            ['book:1:10.00000/conexao.2026.001', 'chapter:10:10.00000/conexao.2026.001.01', 'chapter:11:10.00000/conexao.2026.001.02'],
            $deposit->items->map(fn ($i) => "{$i->target_type}:{$i->target_id}:{$i->doi}")->all(),
        );
        $this->assertSame([DepositItem::PENDING], $deposit->items->pluck('status')->unique()->all());
        $this->assertSame(1, SyncLog::where('action', 'deposit.created')->count());
    }

    public function test_deposito_aceito_fica_sent_e_nenhum_doi_registrado(): void
    {
        Http::fake(['test.crossref.org/servlet/deposit' => Http::response(self::SUCCESS_HTML, 200)]);

        $deposit = DepositService::make()->create($this->payload(), 1);
        $deposit = DepositService::make()->send($deposit);

        $this->assertSame(Deposit::SENT, $deposit->status, 'resposta: '.$deposit->response.' | log: '.SyncLog::latest('id')->first()?->message);
        $this->assertSame(200, $deposit->http_status);
        $this->assertNotNull($deposit->submitted_at);
        $this->assertNull($deposit->result_at);
        $this->assertSame(0, DepositItem::where('status', DepositItem::REGISTERED)->count(), 'HTTP 200 nunca registra DOI');
        $this->assertSame(3, DepositItem::where('status', DepositItem::PENDING)->count());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($deposit) {
            $parts = collect($request->data())->keyBy('name');

            return $request->url() === 'https://test.crossref.org/servlet/deposit'
                && $request->isMultipart()
                && $parts['operation']['contents'] === 'doMDUpload'
                && $parts['login_id']['contents'] === 'doi@conexaoeditora.com.br/bcfg'
                && $parts['login_passwd']['contents'] === 'segredo-de-teste'
                && $request->hasFile('fname', null, $deposit->filename())
                && str_contains($parts['fname']['contents'], '<doi_batch');
        });

        $this->assertSame(1, SyncLog::where('action', 'deposit.sent')->count());
    }

    public function test_deposito_recusado_com_http_200_fica_failed(): void
    {
        Http::fake(['*' => Http::response(self::FAILURE_HTML, 200)]);

        $deposit = DepositService::make()->create($this->payload(), 1);
        $deposit = DepositService::make()->send($deposit);

        $this->assertSame(Deposit::FAILED, $deposit->status);
        $this->assertSame(200, $deposit->http_status);
        $this->assertNull($deposit->submitted_at);
        $this->assertStringContainsString('FAILURE', $deposit->response);
        $this->assertStringContainsString('not authorized', SyncLog::where('action', 'deposit.failed')->first()->message);
        $this->assertSame(0, DepositItem::where('status', '!=', DepositItem::PENDING)->count());

        // Falhou antes de haver relatório: pode ser reenviado.
        $this->assertTrue($deposit->canBeSent());
    }

    public function test_http_401_tambem_e_recusa(): void
    {
        Http::fake(['*' => Http::response('Unauthorized', 401)]);

        $deposit = DepositService::make()->send(DepositService::make()->create($this->payload(), 1));

        $this->assertSame(Deposit::FAILED, $deposit->status);
        $this->assertSame(401, $deposit->http_status);
        Http::assertSentCount(1); // resposta HTTP é definitiva: sem retry
    }

    public function test_timeout_marca_failed_e_relanca_para_a_fila(): void
    {
        Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $deposit = DepositService::make()->create($this->payload(), 1);

        try {
            DepositService::make()->send($deposit);
            $this->fail('timeout deve relançar ConnectionException');
        } catch (ConnectionException) {
        }

        $deposit->refresh();
        $this->assertSame(Deposit::FAILED, $deposit->status);
        $this->assertNull($deposit->http_status);
        $this->assertStringContainsString('timed out', $deposit->response);
        $this->assertTrue($deposit->canBeSent());
        Http::assertSentCount(3); // só falha de rede repete
        $this->assertStringContainsString('Sem resposta da Crossref', SyncLog::where('action', 'deposit.failed')->first()->message);
    }

    public function test_sem_credenciais_nao_envia(): void
    {
        config(['hub.crossref.login' => null]);
        Http::fake();

        $deposit = DepositService::make()->create($this->payload(), 1);

        $this->expectException(CrossrefException::class);
        $this->expectExceptionMessage('CROSSREF_LOGIN');

        try {
            DepositService::make()->send($deposit);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_producao_usa_doi_crossref_org(): void
    {
        config(['hub.crossref.env' => 'production']);
        Http::fake(['doi.crossref.org/*' => Http::response(self::SUCCESS_HTML, 200)]);

        $deposit = DepositService::make()->create($this->payload(), 1);
        $this->assertSame('production', $deposit->environment);

        DepositService::make()->send($deposit);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://doi.crossref.org/servlet/deposit');
    }

    // ------------------------------------------------------------ resultado

    public function test_resultado_parcial_registra_um_doi_e_falha_outro(): void
    {
        $deposit = $this->sentDeposit();
        $diagnostic = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <doi_batch_diagnostic status="completed" sp="ds5">
              <submission_id>1234567890</submission_id>
              <batch_id>{$deposit->batch_id}</batch_id>
              <record_diagnostic status="Success">
                <doi>10.00000/conexao.2026.001</doi>
                <msg>Successfully added</msg>
              </record_diagnostic>
              <record_diagnostic status="Warning">
                <doi>10.00000/conexao.2026.001.01</doi>
                <msg>Added with warning: resource already exists</msg>
              </record_diagnostic>
              <record_diagnostic status="Failure">
                <doi>10.00000/conexao.2026.001.02</doi>
                <msg>Record not processed because submitted version: 20261022 is less or equal to previously submitted version</msg>
              </record_diagnostic>
              <batch_data>
                <record_count>3</record_count>
                <success_count>1</success_count>
                <warning_count>1</warning_count>
                <failure_count>1</failure_count>
              </batch_data>
            </doi_batch_diagnostic>
            XML;
        Http::fake(['test.crossref.org/servlet/submissionDownload*' => Http::response($diagnostic, 200)]);

        $deposit = DepositService::make()->refresh($deposit);

        $this->assertSame(Deposit::FAILED, $deposit->status, 'um DOI falhou: depósito falha');
        $this->assertNotNull($deposit->result_at);

        $byDoi = $deposit->items->keyBy('doi');
        $this->assertSame(DepositItem::REGISTERED, $byDoi['10.00000/conexao.2026.001']->status);
        $this->assertSame('Successfully added', $byDoi['10.00000/conexao.2026.001']->message);
        $this->assertSame(DepositItem::REGISTERED, $byDoi['10.00000/conexao.2026.001.01']->status, 'Warning conta como registrado');
        $this->assertSame(DepositItem::FAILED, $byDoi['10.00000/conexao.2026.001.02']->status);
        $this->assertStringContainsString('less or equal to previously submitted version', $byDoi['10.00000/conexao.2026.001.02']->message);

        Http::assertSent(function (Request $r) use ($deposit) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return str_starts_with($r->url(), 'https://test.crossref.org/servlet/submissionDownload')
                && $q === ['usr' => 'doi@conexaoeditora.com.br/bcfg', 'pwd' => 'segredo-de-teste', 'doi_batch_id' => $deposit->batch_id, 'file_name' => $deposit->filename(), 'type' => 'result'];
        });

        $log = SyncLog::where('action', 'deposit.failed')->latest('id')->first();
        $this->assertStringContainsString('2 DOI(s) registrado(s), 1 com falha', $log->message);
    }

    public function test_resultado_completo_conclui_o_deposito(): void
    {
        $deposit = $this->sentDeposit();
        $records = $deposit->items->map(fn ($i) => "<record_diagnostic status=\"Success\"><doi>{$i->doi}</doi><msg>Successfully added</msg></record_diagnostic>")->implode('');
        Http::fake(['*' => Http::response("<doi_batch_diagnostic status=\"completed\"><batch_id>{$deposit->batch_id}</batch_id>{$records}<batch_data><record_count>3</record_count><success_count>3</success_count><warning_count>0</warning_count><failure_count>0</failure_count></batch_data></doi_batch_diagnostic>", 200)]);

        $deposit = DepositService::make()->refresh($deposit);

        $this->assertSame(Deposit::DONE, $deposit->status);
        $this->assertSame(3, $deposit->items->where('status', DepositItem::REGISTERED)->count());
        $this->assertSame(1, SyncLog::where('action', 'deposit.done')->count());
    }

    public function test_lote_ainda_em_processamento_mantem_tudo_pendente(): void
    {
        $deposit = $this->sentDeposit();
        Http::fake(['*' => Http::response('<doi_batch_diagnostic status="in_process"><batch_id>'.$deposit->batch_id.'</batch_id></doi_batch_diagnostic>', 200)]);

        $deposit = DepositService::make()->refresh($deposit);

        $this->assertSame(Deposit::PROCESSING, $deposit->status);
        $this->assertNull($deposit->result_at);
        $this->assertSame(3, $deposit->items()->where('status', DepositItem::PENDING)->count());
        $this->assertSame(1, SyncLog::where('action', 'deposit.pending')->count());

        // Resposta em texto (arquivo ainda não conhecido) também é "aguarde".
        Http::fake(['*' => Http::response('Submission still being processed', 200)]);
        $deposit = DepositService::make()->refresh($deposit);
        $this->assertSame(Deposit::PROCESSING, $deposit->status);

        // Resposta inesperada não mexe no estado (fica para a próxima varredura).
        Http::fake(['*' => Http::response('Internal error', 500)]);
        $deposit = DepositService::make()->refresh($deposit);
        $this->assertSame(Deposit::PROCESSING, $deposit->status);
        $this->assertSame(0, DepositItem::where('status', '!=', DepositItem::PENDING)->count(), 'nunca registered sem relatório');
    }

    public function test_doi_fora_do_relatorio_concluido_e_falha(): void
    {
        $deposit = $this->sentDeposit();
        Http::fake(['*' => Http::response('<doi_batch_diagnostic status="completed"><record_diagnostic status="Success"><doi>10.00000/conexao.2026.001</doi><msg>ok</msg></record_diagnostic><batch_data><record_count>1</record_count><success_count>1</success_count><warning_count>0</warning_count><failure_count>0</failure_count></batch_data></doi_batch_diagnostic>', 200)]);

        $deposit = DepositService::make()->refresh($deposit);

        $this->assertSame(Deposit::FAILED, $deposit->status);
        $this->assertSame(1, $deposit->items->where('status', DepositItem::REGISTERED)->count());
        $this->assertSame(2, $deposit->items->where('status', DepositItem::FAILED)->count());
        $this->assertStringContainsString('não consta no relatório', $deposit->items->firstWhere('status', DepositItem::FAILED)->message);
    }

    public function test_refresh_so_vale_para_deposito_enviado(): void
    {
        Http::fake();
        $deposit = DepositService::make()->create($this->payload(), 1);

        $this->expectException(CrossrefException::class);
        DepositService::make()->refresh($deposit);
    }

    // ------------------------------------------------------------ comandos

    public function test_comando_crossref_xml_gera_arquivo_valido(): void
    {
        $saida = sys_get_temp_dir().'/crossref-'.uniqid().'.xml';

        $this->artisan('hub:crossref-xml', ['json' => $this->fixturePath(), '--saida' => $saida])
            ->expectsOutputToContain('XML válido')
            ->assertSuccessful();

        $this->assertFileExists($saida);
        $this->assertStringContainsString('<book book_type="edited_book">', file_get_contents($saida));
        @unlink($saida);

        $this->artisan('hub:crossref-xml', ['json' => '/caminho/inexistente.json'])->assertFailed();
    }

    public function test_comandos_de_envio_e_resultado(): void
    {
        Http::fake([
            'test.crossref.org/servlet/deposit' => Http::response(self::SUCCESS_HTML, 200),
            'test.crossref.org/servlet/submissionDownload*' => Http::response('<doi_batch_diagnostic status="queued"/>', 200),
        ]);
        $deposit = DepositService::make()->create($this->payload(), 1);

        $this->artisan('hub:crossref-enviar', ['deposit' => $deposit->id])->expectsOutputToContain('recebido pela Crossref')->assertSuccessful();
        $this->assertSame(Deposit::SENT, $deposit->refresh()->status);

        $this->artisan('hub:crossref-resultado', ['--pendentes' => true])->expectsOutputToContain('Em processamento')->assertSuccessful();
        $this->assertSame(Deposit::PROCESSING, $deposit->refresh()->status);

        $this->artisan('hub:crossref-enviar', ['deposit' => $deposit->batch_id])->assertFailed(); // já saiu: não reenvia
        $this->artisan('hub:crossref-resultado', ['deposit' => '999'])->assertFailed();
    }

    // ------------------------------------------------------------ apoio

    private function payload(): array
    {
        return json_decode((string) file_get_contents($this->fixturePath()), true, 512, JSON_THROW_ON_ERROR);
    }

    private function fixturePath(): string
    {
        return base_path('tests/Fixtures/crossref/publicacao-tratado.json');
    }

    private function sentDeposit(): Deposit
    {
        $deposit = DepositService::make()->create($this->payload(), 1);
        $deposit->update(['status' => Deposit::SENT, 'http_status' => 200, 'submitted_at' => now()]);

        return $deposit->fresh('items');
    }

    private function bookMetadata(string $xml): string
    {
        return substr($xml, strpos($xml, '<book_metadata'), strpos($xml, '</book_metadata>') - strpos($xml, '<book_metadata'));
    }
}
