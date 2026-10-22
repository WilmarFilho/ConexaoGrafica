<?php

namespace Tests\Feature;

use App\Crossref\BookDeposits;
use App\Editorial\EditorialException;
use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Books\DepositActions;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ViewBook;
use App\Filament\Resources\Books\RelationManagers\DepositsRelationManager;
use App\Jobs\RefreshDeposit;
use App\Jobs\SendDeposit;
use App\Models\Book;
use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\SyncLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/**
 * Frente E1 do Conexão DOI: o módulo Editorial (livro e capítulos) ligado ao
 * depósito na Crossref. Tudo com Http::fake; nenhum depósito real.
 */
class BookDepositTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    private const SUCCESS_HTML = '<html><head><title>SUCCESS</title></head><body><h2>SUCCESS</h2><p>Your batch submission was successfully received.</p></body></html>';

    private const FAILURE_HTML = '<html><head><title>FAILURE</title></head><body><h2>FAILURE</h2><p>Login ID and/or password are incorrect.</p></body></html>';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hub.crossref.env' => 'test',
            'hub.crossref.login' => 'doi@conexaoeditora.com.br/bcfg',
            'hub.crossref.password' => 'segredo-de-teste',
            'hub.crossref.prefix' => '10.00000',
            'hub.crossref.depositor_name' => 'Conexão Editora',
            'hub.crossref.depositor_email' => 'doi@conexaoeditora.com.br',
            'hub.crossref.registrant' => 'Conexão Editora',
            'hub.crossref.landing_base' => 'https://conexaoeditora.com.br',
        ]);

        Storage::fake('local');
        Sleep::fake();
        Http::preventStrayRequests();
    }

    // ------------------------------------------------------------ registro

    public function test_registrar_exige_livro_publicado_com_url_publica_no_livro_e_nos_capitulos(): void
    {
        $book = $this->tratado(); // published, mas sem as URLs do site
        $deposito = $this->user('deposito');

        $this->assertFalse($book->canBeDeposited());

        try {
            BookDeposits::make()->register($book, $deposito);
            $this->fail('sem landing_url no livro tinha de falhar');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('URL pública', $e->getMessage());
        }

        $book->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/']);
        $book->chapters()->where('sequence', 1)->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/01/']);

        try {
            BookDeposits::make()->register($book->fresh(), $deposito);
            $this->fail('capítulo sem landing_url tinha de falhar');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('Capítulo(s) sem URL pública: 2', $e->getMessage());
        }

        $book->update(['status' => 'approved']);
        try {
            BookDeposits::make()->register($book->fresh(), $deposito);
            $this->fail('livro não publicado tinha de falhar');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('Só livros publicados', $e->getMessage());
        }

        $book->update(['status' => 'published']);
        $book->chapters()->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/00/']);
        $book = $book->fresh();
        $this->assertNull(BookDeposits::blocker($book));
        try {
            BookDeposits::make()->register($book, $this->user('editor'));
            $this->fail('editor não deposita');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('Seu perfil', $e->getMessage());
        }

        $this->assertSame(0, Deposit::count());
        $this->assertSame('none', $book->fresh()->doi_status);
    }

    public function test_registrar_gera_deposito_com_todos_os_dois_e_deixa_livro_e_capitulos_prontos(): void
    {
        Queue::fake();
        $book = $this->publishedTratado();

        $deposit = BookDeposits::make()->register($book, $this->user('deposito'));

        $this->assertSame(Deposit::QUEUED, $deposit->status);
        $this->assertSame('test', $deposit->environment);
        $this->assertSame($book->id, $deposit->book_id);
        $this->assertSame(
            ['book:1:10.00000/conexao.2026.001', 'chapter:10:10.00000/conexao.2026.001.01', 'chapter:11:10.00000/conexao.2026.001.02'],
            $deposit->items->map(fn ($i) => "{$i->target_type}:{$i->target_id}:{$i->doi}")->all(),
        );

        // As URLs que vão para a Crossref são as devolvidas pelo site, não as montadas pelo slug.
        $xml = Storage::disk('local')->get($deposit->xml_path);
        $this->assertStringContainsString('<resource>https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/</resource>', $xml);
        $this->assertStringContainsString('<resource>https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/02/</resource>', $xml);

        $this->assertSame('ready', $book->fresh()->doi_status);
        $this->assertSame(['ready', 'ready'], $book->chapters()->pluck('doi_status')->all());

        Queue::assertPushed(SendDeposit::class, fn (SendDeposit $job) => $job->depositId === $deposit->id);
        $this->assertSame(1, SyncLog::where('action', 'deposit.requested')->count());
        $this->assertSame(1, $book->deposits()->count());
    }

    public function test_envio_aceito_deixa_livro_e_capitulos_em_sent(): void
    {
        Queue::fake();
        $book = $this->publishedTratado();
        $deposit = BookDeposits::make()->register($book, $this->user('deposito'));

        Http::fake(['test.crossref.org/servlet/deposit' => Http::response(self::SUCCESS_HTML, 200)]);
        (new SendDeposit($deposit->id))->handle();

        $this->assertSame(Deposit::SENT, $deposit->fresh()->status);
        $this->assertSame('sent', $book->fresh()->doi_status, 'HTTP 200 leva só a sent');
        $this->assertSame(['sent', 'sent'], $book->chapters()->pluck('doi_status')->all());
        Queue::assertPushed(RefreshDeposit::class);
        $this->assertSame(0, DepositItem::where('status', DepositItem::REGISTERED)->count());
    }

    public function test_envio_recusado_deixa_livro_e_capitulos_em_failed(): void
    {
        Queue::fake();
        $book = $this->publishedTratado();
        $deposit = BookDeposits::make()->register($book, $this->user('deposito'));

        // Recusa no envio: depósito e DOIs ficam failed (sem relatório, o depósito ainda pode ser reenviado).
        Http::fake(['test.crossref.org/servlet/deposit' => Http::response(self::FAILURE_HTML, 200)]);
        (new SendDeposit($deposit->id))->handle();

        $this->assertSame(Deposit::FAILED, $deposit->fresh()->status);
        $this->assertTrue($deposit->fresh()->canBeSent());
        $this->assertSame('failed', $book->fresh()->doi_status);
        $this->assertSame(['failed', 'failed'], $book->chapters()->pluck('doi_status')->all());
        Queue::assertNotPushed(RefreshDeposit::class);
    }

    public function test_resultado_parcial_registra_o_livro_e_marca_o_capitulo_que_falhou(): void
    {
        $book = $this->publishedTratado();
        $deposit = $this->sentDeposit($book);

        Http::fake(['test.crossref.org/servlet/submissionDownload*' => Http::response($this->diagnostic($deposit, [
            '10.00000/conexao.2026.001' => ['Success', 'Successfully added'],
            '10.00000/conexao.2026.001.01' => ['Success', 'Successfully added'],
            '10.00000/conexao.2026.001.02' => ['Failure', 'Record not processed: resource URL unreachable'],
        ]), 200)]);

        (new RefreshDeposit($deposit->id))->handle();

        $this->assertSame(Deposit::FAILED, $deposit->fresh()->status);
        $this->assertSame('registered', $book->fresh()->doi_status);
        $this->assertSame(['registered', 'failed'], $book->chapters()->pluck('doi_status')->all());

        $log = SyncLog::where('action', 'deposit.synced')->latest('id')->firstOrFail();
        $this->assertSame($book->id, $log->subject_id);
        $this->assertStringContainsString('2 registrado', $log->message);
        $this->assertStringContainsString('1 falhou', $log->message);
    }

    public function test_reenvio_apos_registrado_gera_deposito_novo_com_os_mesmos_dois(): void
    {
        Queue::fake();
        $book = $this->publishedTratado();
        $first = $this->sentDeposit($book);
        $first->update(['status' => Deposit::DONE, 'result_at' => now()]);
        $first->items()->update(['status' => DepositItem::REGISTERED]);
        BookDeposits::make()->sync($first->fresh());
        $this->assertSame('registered', $book->fresh()->doi_status);

        // Em andamento (sent/processing) não registra de novo.
        $book->update(['doi_status' => 'processing']);
        try {
            BookDeposits::make()->register($book->fresh(), $this->user('deposito'));
            $this->fail('livro em processamento não registra de novo');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('em andamento', $e->getMessage());
        }

        $book->update(['doi_status' => 'registered']);
        $second = BookDeposits::make()->register($book->fresh(), $this->user('deposito'));

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->batch_id, $second->batch_id);
        $this->assertSame($first->items->pluck('doi')->all(), $second->items->pluck('doi')->all(), 'atualização usa os mesmos DOIs');
        $this->assertSame(2, $book->deposits()->count());
        $this->assertSame('ready', $book->fresh()->doi_status);
        $this->assertSame(['ready', 'ready'], $book->chapters()->pluck('doi_status')->all());
        $this->assertStringContainsString('Atualização de 3 DOI(s)', SyncLog::where('action', 'deposit.requested')->latest('id')->first()->message);

        // O resultado atrasado do lote antigo não sobrescreve o reenvio.
        $this->assertSame(0, BookDeposits::make()->sync($first->fresh()));
        $this->assertSame('ready', $book->fresh()->doi_status);
    }

    public function test_comando_de_resultado_sincroniza_o_livro(): void
    {
        $book = $this->publishedTratado();
        $deposit = $this->sentDeposit($book);

        Http::fake(['test.crossref.org/servlet/submissionDownload*' => Http::response($this->diagnostic($deposit, [
            '10.00000/conexao.2026.001' => ['Success', 'Successfully added'],
            '10.00000/conexao.2026.001.01' => ['Success', 'Successfully added'],
            '10.00000/conexao.2026.001.02' => ['Warning', 'Added with warning'],
        ]), 200)]);

        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, Artisan::call('hub:crossref-resultado', ['--pendentes' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString("{$deposit->batch_id}: Concluído (3 registro(s) do livro atualizado(s))", $output);
        $this->assertStringContainsString('10.00000/conexao.2026.001.02', $output);

        $this->assertSame(Deposit::DONE, $deposit->fresh()->status);
        $this->assertSame('registered', $book->fresh()->doi_status);
        $this->assertSame(['registered', 'registered'], $book->chapters()->pluck('doi_status')->all());
    }

    public function test_varredura_de_resultado_esta_agendada_a_cada_15_minutos(): void
    {
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, Artisan::call('schedule:list'));
        $this->assertStringContainsString('hub:crossref-resultado --pendentes', Artisan::output());

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $e) => str_contains($e->command, 'hub:crossref-resultado') && str_contains($e->command, '--pendentes'));

        $this->assertNotNull($event, 'varredura de resultado não está no agendador');
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping, 'withoutOverlapping');
        $this->assertTrue($event->onOneServer, 'onOneServer');
    }

    // ------------------------------------------------------------ Filament

    public function test_acoes_de_deposito_aparecem_so_para_admin_e_deposito(): void
    {
        Queue::fake();
        $book = $this->publishedTratado();

        // Editor e revisor nem recebem as ações (ViewBook só as monta para quem deposita).
        foreach (['editor', 'revisor'] as $role) {
            $this->panel($this->user($role));
            Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
                ->assertActionDoesNotExist('registrar_doi')
                ->assertActionDoesNotExist('consultar_resultado')
                ->assertActionDoesNotExist('baixar_xml');
        }

        // Expedição nem abre o livro.
        $this->actingAs($this->user('expedicao'));
        $this->assertContains($this->get(BookResource::getUrl('view', ['record' => $book]))->status(), [403, 404]);

        $this->panel($this->user('admin'));
        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])->assertActionVisible('registrar_doi');

        // A confirmação mostra o ambiente, o aviso de cobrança e os DOIs do lote.
        $html = DepositActions::confirmationHtml($book);
        $this->assertStringContainsString('Ambiente: homologação', $html);
        $this->assertStringContainsString('em produção a Crossref cobra por DOI', $html);
        $this->assertStringContainsString('3 DOI(s) no lote', $html);
        $this->assertStringContainsString('10.00000/conexao.2026.001.02', $html);
        $this->assertStringContainsString('Cap. 2 · Genética nas doenças raras', $html);

        $this->panel($this->user('deposito'));
        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertActionVisible('registrar_doi')
            ->assertActionHidden('consultar_resultado')
            ->assertActionHidden('baixar_xml')
            ->callAction('registrar_doi')
            ->assertNotified();

        $deposit = $book->deposits()->firstOrFail();
        $this->assertSame('ready', $book->fresh()->doi_status);
        Queue::assertPushed(SendDeposit::class);

        // Com depósito enviado, consulta e download aparecem; a consulta sincroniza o livro.
        $deposit->update(['status' => Deposit::SENT, 'http_status' => 200, 'submitted_at' => now()]);
        Http::fake(['test.crossref.org/servlet/submissionDownload*' => Http::response('<doi_batch_diagnostic status="queued"/>', 200)]);

        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertActionVisible('consultar_resultado')
            ->assertActionVisible('baixar_xml')
            ->callAction('consultar_resultado')
            ->assertNotified("Depósito {$deposit->batch_id}: Em processamento");

        $this->assertSame('processing', $book->fresh()->doi_status);
        $this->assertSame(['processing', 'processing'], $book->chapters()->pluck('doi_status')->all());

        // Livro sem URL pública: o botão existe, mas o serviço explica.
        $book->update(['landing_url' => null]);
        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->callAction('registrar_doi')
            ->assertNotified('Não foi possível');
    }

    public function test_confirmacao_em_producao_avisa_que_a_crossref_cobra_por_doi(): void
    {
        config(['hub.crossref.env' => 'production']);
        $book = $this->publishedTratado();

        $this->assertTrue(DepositActions::isProduction());
        $html = DepositActions::confirmationHtml($book);
        $this->assertStringContainsString('Ambiente: produção', $html);
        $this->assertStringContainsString('doi.crossref.org', $html);
        $this->assertStringContainsString('a Crossref cobra por DOI registrado', $html);
        $this->assertStringContainsString('3 DOI(s) neste lote', $html);

        // Livro já registrado: o texto avisa que é atualização dos mesmos DOIs.
        $book->update(['doi_status' => 'registered']);
        $this->assertStringContainsString('atualiza os metadados', DepositActions::confirmationHtml($book->fresh()));

        // Sem URL pública, a confirmação já mostra o impedimento.
        $book->update(['landing_url' => null]);
        $this->assertStringContainsString('URL pública', DepositActions::confirmationHtml($book->fresh()));
    }

    public function test_historico_de_depositos_aparece_na_tela_do_livro_para_quem_deposita(): void
    {
        $book = $this->publishedTratado();
        $deposit = $this->sentDeposit($book);
        $deposit->items()->where('doi', '10.00000/conexao.2026.001.02')->update(['status' => DepositItem::FAILED, 'message' => 'resource URL unreachable']);

        $this->panel($this->user('editor'));
        $this->assertFalse(DepositsRelationManager::canViewForRecord($book, ViewBook::class));

        $this->panel($this->user('deposito'));
        $this->assertTrue(DepositsRelationManager::canViewForRecord($book, ViewBook::class));
        $this->assertFalse(DepositsRelationManager::canViewForRecord($book, EditBook::class), 'só na tela de resumo');

        Livewire::test(DepositsRelationManager::class, ['ownerRecord' => $book, 'pageClass' => ViewBook::class])
            ->assertCanSeeTableRecords([$deposit])
            ->assertSee($deposit->batch_id)
            ->assertSee('Homologação')
            ->assertSee('Enviado')
            ->assertSee('0 registrado(s) · 1 com falha · 2 aguardando')
            ->assertTableActionVisible('consultar', $deposit)
            ->assertTableActionVisible('itens', $deposit)
            ->assertTableActionVisible('baixar_xml', $deposit);

        // Conteúdo do modal "Itens" (os modais não entram no HTML do teste Livewire).
        $html = DepositActions::itemsHtml($deposit->fresh());
        $this->assertStringContainsString('10.00000/conexao.2026.001.02', $html);
        $this->assertStringContainsString('resource URL unreachable', $html);
        $this->assertStringContainsString('Capítulo #11', $html);
        $this->assertStringContainsString('Livro #1', $html);
        $this->assertStringContainsString('Falhou', $html);

        $this->actingAs($this->user('deposito'))
            ->get(BookResource::getUrl('view', ['record' => $book]))
            ->assertOk()
            ->assertSee('Depósitos na Crossref')
            ->assertSee($deposit->batch_id);
    }

    // ------------------------------------------------------------ apoio

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'app_authentication_secret' => 'segredo']);
    }

    private function panel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** O Tratado publicado no site, com as URLs que o WordPress devolve. */
    private function publishedTratado(): Book
    {
        $book = $this->tratado();
        $book->update(['landing_url' => 'https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/', 'published_at' => now()]);
        foreach ($book->chapters as $c) {
            $c->update(['landing_url' => sprintf('https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/%02d/', $c->sequence)]);
        }

        return $book->fresh();
    }

    private function sentDeposit(Book $book): Deposit
    {
        Queue::fake();
        $deposit = BookDeposits::make()->register($book, $this->user('deposito'));
        $deposit->update(['status' => Deposit::SENT, 'http_status' => 200, 'submitted_at' => now()]);
        BookDeposits::make()->sync($deposit->fresh());
        $this->assertSame('sent', $book->fresh()->doi_status);

        return $deposit->fresh('items');
    }

    /** @param array<string, array{0: string, 1: string}> $records doi => [status, msg] */
    private function diagnostic(Deposit $deposit, array $records): string
    {
        $counts = ['Success' => 0, 'Warning' => 0, 'Failure' => 0];
        $xml = '';
        foreach ($records as $doi => [$status, $msg]) {
            $counts[$status]++;
            $xml .= "<record_diagnostic status=\"{$status}\"><doi>{$doi}</doi><msg>{$msg}</msg></record_diagnostic>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?><doi_batch_diagnostic status="completed" sp="ds5">'
            ."<submission_id>1234567890</submission_id><batch_id>{$deposit->batch_id}</batch_id>{$xml}"
            .'<batch_data><record_count>'.count($records).'</record_count><success_count>'.$counts['Success'].'</success_count>'
            .'<warning_count>'.$counts['Warning'].'</warning_count><failure_count>'.$counts['Failure'].'</failure_count></batch_data></doi_batch_diagnostic>';
    }
}
