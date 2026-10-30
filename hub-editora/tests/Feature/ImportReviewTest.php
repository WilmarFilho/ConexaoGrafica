<?php

namespace Tests\Feature;

use App\Editorial\EditorialException;
use App\Editorial\Import\Ai\AiGateway;
use App\Editorial\Import\Integrator;
use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Imports\ImportResource;
use App\Filament\Resources\Imports\Pages\ReviewImport;
use App\Jobs\ProcessImport;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Contribution;
use App\Models\Import;
use App\Models\Institution;
use App\Models\Person;
use App\Models\SyncLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeAiGateway;
use Tests\Support\MakesEditorialFixtures;
use Tests\Support\PdfFixture;
use Tests\TestCase;

/**
 * Conexão DOI, frente E2: tela de revisão da importação por IA e integração
 * ao catálogo. A proposta vem do PdfFixture + FakeAiGateway (frente C).
 */
class ImportReviewTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    private string $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['hub.ai.key' => 'sk-proj-teste-nunca-usada', 'hub.ai.model' => 'gpt-6-astra', 'hub.ai.gs_bin' => null]);
        $this->app->instance(AiGateway::class, new FakeAiGateway);
        $this->pdf = PdfFixture::write();
    }

    protected function tearDown(): void
    {
        @unlink($this->pdf);
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'app_authentication_secret' => 'segredo']);
    }

    private function panel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** Importação processada pela IA simulada: proposta pronta (`suggested`). */
    private function proposal(?int $bookId = null): Import
    {
        $import = Import::receive($this->pdf, 'tratado.pdf', null, $bookId);
        ProcessImport::dispatchSync($import->id);

        return $import->fresh();
    }

    /** Aprova obra e capítulos direto no result, como a tela faria. */
    private function approveAll(Import $import): Import
    {
        $r = $import->result;
        $r['book']['approved'] = true;
        foreach ($r['chapters'] as $i => $c) {
            $r['chapters'][$i]['approved'] = true;
        }
        $import->forceFill(['result' => $r, 'status' => Import::APPROVED, 'approved_at' => now()])->save();

        return $import->fresh();
    }

    // ------------------------------------------------------------ Integrator

    public function test_integrator_creates_book_chapters_people_institutions_and_contributions_and_is_idempotent(): void
    {
        $revisor = $this->user('revisor');
        $import = $this->approveAll($this->proposal());

        // Resumo do capítulo 2 editado na revisão (a IA tinha sugerido um).
        $r = $import->result;
        $r['chapters'][1]['abstract_approved'] = 'Resumo revisado pelo editor.';
        $import->forceFill(['result' => $r])->save();

        $book = Integrator::integrate($import->fresh(), $revisor);

        // Livro
        $this->assertSame(1, Book::count());
        $this->assertSame(PdfFixture::TITLE, $book->title);
        $this->assertSame('Diagnostico, manejo e politicas publicas', $book->subtitle);
        $this->assertSame(['edited_book', 2026, 'pt', 'draft', 'none'], [$book->type, $book->publication_year, $book->language, $book->status, $book->doi_status]);
        $this->assertSame('978-65-00000-00-9', $book->isbn_print);
        $this->assertSame('978-65-00000-01-6', $book->isbn_ebook);
        $this->assertSame('Medicina', $book->subject_area);
        $this->assertSame(['Doencas raras', 'Genetica'], $book->keywords);
        $this->assertStringStartsWith('Esta obra reune', $book->abstract, 'sem resumo impresso: entra o sugerido pela IA a partir dos capítulos');
        $this->assertSame('conexao.2026.001', $book->doi_suffix, 'sufixo pelo DoiSuffix');
        $this->assertSame('tratado-de-doencas-raras', $book->slug);

        // Pessoas e instituições
        $this->assertSame(['Maria Exemplo da Silva', 'Joao Exemplo Pereira', 'Ana Exemplo Rocha', 'Carlos Exemplo Lima'], Person::orderBy('id')->pluck('name')->all());
        $maria = Person::where('name', 'Maria Exemplo da Silva')->firstOrFail();
        $this->assertSame(['SILVA, Maria Exemplo da', '0000-0002-1825-0097'], [$maria->sort_name, $maria->orcid]);
        $this->assertSame(['Universidade Federal de Goias', 'Hospital das Clinicas da UFG'], Institution::orderBy('id')->pluck('name')->all());
        $ufg = Institution::where('name', 'Universidade Federal de Goias')->firstOrFail();
        $this->assertSame(['Goiania', 'GO', 'BR'], [$ufg->city, $ufg->state, $ufg->country]);

        // Contribuições do livro: 2 organizadores + 2 autores, na ordem da proposta.
        $bookContribs = $book->contributions()->with('person')->get();
        $this->assertSame(['editor', 'editor', 'author', 'author'], $bookContribs->pluck('role')->all());
        $this->assertSame($ufg->id, $bookContribs[0]->institution_id);
        $this->assertNull($bookContribs[1]->institution_id, 'João não tem instituição: pendência do checklist');

        // Capítulos
        $chapters = $book->chapters()->with('contributions.person')->get();
        $this->assertCount(2, $chapters);
        [$c1, $c2] = $chapters;
        $this->assertSame([1, '1', 9, 10, 'Secao 1 - Fundamentos'], [$c1->sequence, $c1->number, $c1->first_page, $c1->last_page, $c1->section]);
        $this->assertSame(PdfFixture::CH1, $c1->title);
        $this->assertStringStartsWith('Este capitulo apresenta', $c1->abstract_original);
        $this->assertSame($c1->abstract_original, $c1->abstract_approved, 'sem edição, o aprovado é o original');
        $this->assertSame(['doencas raras', 'historia da medicina', 'legislacao'], $c1->keywords);
        $this->assertCount(1, $c1->references);
        $this->assertSame(4, $c1->ai_evidence['title']['pdf_page']);
        $this->assertSame('conexao.2026.001.01', $c1->doi_suffix);
        $this->assertSame('draft', $c1->status);
        $this->assertSame(['Ana Exemplo Rocha', 'Maria Exemplo da Silva'], $c1->contributions->map(fn ($c) => $c->person->name)->all());
        $this->assertSame($ufg->id, $c1->contributions[0]->institution_id, 'afiliação em texto casou com a instituição cadastrada');

        $this->assertSame([2, 11, 11], [$c2->sequence, $c2->first_page, $c2->last_page]);
        $this->assertSame('Resumo revisado pelo editor.', $c2->abstract_approved);
        $this->assertNotNull($c2->abstract_ai);
        $this->assertNull($c2->abstract_original);
        $this->assertSame('conexao.2026.001.02', $c2->doi_suffix);
        $this->assertSame(['Carlos Exemplo Lima'], $c2->contributions->map(fn ($c) => $c->person->name)->all(), '"Carlos E. Lima" vira a pessoa 3 da obra');

        // Importação
        $import->refresh();
        $this->assertSame([Import::INTEGRATED, $book->id, $revisor->id], [$import->status, $import->book_id, $import->reviewed_by]);
        $this->assertNotNull($import->approved_at);
        $this->assertSame(1, SyncLog::where('action', 'import.integrated')->where('subject_id', $import->id)->count());

        // Idempotente: reexecutar não duplica nada.
        $counts = fn () => [Book::count(), Chapter::count(), Person::count(), Institution::count(), Contribution::count()];
        $this->assertSame([1, 2, 4, 2, 7], $counts());
        Integrator::integrate($import->fresh(), $revisor);
        $this->assertSame([1, 2, 4, 2, 7], $counts());
        $this->assertSame('Resumo revisado pelo editor.', Chapter::where('sequence', 2)->first()->abstract_approved);
    }

    public function test_integrator_updates_the_linked_book_without_erasing_what_the_proposal_did_not_bring(): void
    {
        $existing = $this->makeBook(['title' => 'Titulo antigo', 'abstract' => 'Resumo que já existia', 'doi_suffix' => 'conexao.2026.001']);
        $import = $this->approveAll($this->proposal($existing->id));

        $book = Integrator::integrate($import, $this->user('editor'));

        $this->assertSame($existing->id, $book->id);
        $this->assertSame(1, Book::count());
        $this->assertSame(PdfFixture::TITLE, $book->title, 'o que a proposta trouxe atualiza');
        $this->assertSame('Resumo que já existia', $book->abstract, 'a sugestão da IA não troca o resumo já cadastrado');
        $this->assertSame('conexao.2026.001', $book->doi_suffix, 'DOI não muda');
        $this->assertSame(2, $book->chapters()->count());
    }

    public function test_partial_approval_blocks_integration(): void
    {
        $import = $this->proposal();
        $r = $import->result;
        $r['book']['approved'] = true;
        $r['chapters'][0]['approved'] = true; // capítulo 2 fica de fora
        $import->forceFill(['result' => $r, 'status' => Import::REVIEW])->save();

        $this->assertFalse($import->fresh()->isFullyApproved());
        $this->assertSame('obra aprovada, 1 de 2 capítulo(s) aprovado(s)', $import->fresh()->approvalSummary());

        try {
            Integrator::integrate($import->fresh(), $this->user('revisor'));
            $this->fail('Deveria ter recusado');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('1 de 2', $e->getMessage());
        }

        $this->assertSame(0, Book::count());
        $this->assertSame(Import::REVIEW, $import->fresh()->status);
    }

    // ----------------------------------------------------------- tela (Livewire)

    public function test_review_page_moves_to_review_saves_edits_approves_and_integrates(): void
    {
        $this->panel($this->user('editor'));
        $import = $this->proposal();
        $this->assertSame(Import::SUGGESTED, $import->status);

        $page = fn () => Livewire::test(ReviewImport::class, ['record' => $import->getRouteKey()]);

        $page()->assertOk()
            ->assertSee(PdfFixture::TITLE)
            ->assertSee(PdfFixture::CH1)
            ->assertSee('Aprovar obra')
            ->assertSee('Integrar ao catálogo');
        $this->assertSame(Import::REVIEW, $import->fresh()->status, 'abrir a proposta inicia a revisão');
        $this->assertSame(1, SyncLog::where('action', 'import.review')->count());

        // Integrar antes de aprovar tudo é recusado.
        $page()->callAction('integrar')->assertNotified('Não foi possível');
        $this->assertSame(0, Book::count());

        // Obra: edita o resumo e aprova.
        $page()->set('data.book.abstract', 'Resumo da obra escrito na revisão.')
            ->callAction('aprovar_obra')
            ->assertNotified('Obra aprovada');
        $fresh = $import->fresh();
        $this->assertTrue($fresh->bookApproved());
        $this->assertSame('Resumo da obra escrito na revisão.', $fresh->result['book']['abstract'], 'edição salva no result');
        $this->assertSame(0.98, $fresh->result['book']['evidence']['title']['confidence'], 'evidência preservada');
        $this->assertSame(Import::REVIEW, $fresh->status);

        // Capítulo 2: navega, edita o resumo sugerido pela IA e aprova.
        $page()->call('goTo', 1)
            ->assertSet('current', 1)
            ->assertSee(PdfFixture::CH2)
            ->set('data.chapter.abstract_approved', 'Resumo do capítulo 2 revisado.')
            ->callAction('aprovar_capitulo')
            ->assertNotified('Capítulo 2 aprovado');
        $fresh = $import->fresh();
        $this->assertTrue($fresh->chapterApproved(1));
        $this->assertFalse($fresh->chapterApproved(0));
        $this->assertSame('Resumo do capítulo 2 revisado.', $fresh->result['chapters'][1]['abstract_approved']);
        $this->assertSame(3, $fresh->result['chapters'][1]['contributors'][0]['book_contributor_index'], 'vínculo do autor preservado ao salvar');
        $this->assertSame('ai', $fresh->result['chapters'][1]['abstract_source']);

        // "Aprovar todos sem pendência" pega o capítulo 1 → tudo aprovado → approved.
        $page()->callAction('aprovar_todos')->assertNotified('1 capítulo(s) aprovado(s)');
        $fresh = $import->fresh();
        $this->assertTrue($fresh->isFullyApproved());
        $this->assertSame(Import::APPROVED, $fresh->status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertSame(1, SyncLog::where('action', 'import.approved')->count());

        // Reabrir um capítulo volta a review; aprovar de novo volta a approved.
        $page()->callAction('aprovar_capitulo')->assertNotified('Capítulo 1 reaberto');
        $this->assertSame(Import::REVIEW, $import->fresh()->status);
        $page()->callAction('aprovar_capitulo')->assertNotified('Capítulo 1 aprovado');
        $this->assertSame(Import::APPROVED, $import->fresh()->status);

        // Integra e vai para a edição do livro.
        $page()->callAction('integrar')
            ->assertNotified('Proposta integrada ao catálogo')
            ->assertRedirect(BookResource::getUrl('edit', ['record' => Book::firstOrFail()]));

        $book = Book::firstOrFail();
        $this->assertSame(Import::INTEGRATED, $import->fresh()->status);
        $this->assertSame($book->id, $import->fresh()->book_id);
        $this->assertSame('Resumo da obra escrito na revisão.', $book->abstract);
        $this->assertSame('Resumo do capítulo 2 revisado.', $book->chapters()->where('sequence', 2)->first()->abstract_approved);

        // Integrada: a tela ainda abre (só leitura) e aponta para o livro.
        $page()->assertOk()->assertActionVisible('abrir_livro')->assertActionHidden('integrar')->assertActionHidden('aprovar_obra');
    }

    public function test_reprocess_from_the_page_discards_the_proposal_and_runs_the_job_again(): void
    {
        $this->panel($this->user('editor'));
        $import = $this->proposal();

        Livewire::test(ReviewImport::class, ['record' => $import->getRouteKey()])
            ->assertActionVisible('reprocessar')
            ->callAction('reprocessar')
            ->assertNotified('Importação reenviada à fila')
            ->assertRedirect(ImportResource::getUrl());

        // Fila síncrona nos testes: o job já rodou de novo com a IA simulada.
        $fresh = $import->fresh();
        $this->assertSame(Import::SUGGESTED, $fresh->status);
        $this->assertFalse($fresh->bookApproved());
        $this->assertSame(1, SyncLog::where('action', 'import.reprocess')->count());
        $this->assertSame(2, SyncLog::where('action', 'import.suggested')->count());
        Storage::disk('local')->assertExists($fresh->file_path);

        // Aprovada, não reprocessa mais.
        $this->assertFalse($this->approveAll($fresh)->canReprocess());
    }

    public function test_page_shows_progress_or_error_while_there_is_no_proposal(): void
    {
        $this->panel($this->user('editor'));

        $import = Import::receive($this->pdf, 'tratado.pdf');
        Livewire::test(ReviewImport::class, ['record' => $import->getRouteKey()])
            ->assertOk()->assertSee('Na fila')->assertActionHidden('integrar')->assertActionVisible('reprocessar');
        $this->assertSame(Import::RECEIVED, $import->fresh()->status, 'sem proposta não vira review');

        $import->forceFill(['status' => Import::ERROR, 'error' => 'A API da OpenAI recusou a chave'])->save();
        Livewire::test(ReviewImport::class, ['record' => $import->getRouteKey()])
            ->assertOk()->assertSee('A API da OpenAI recusou a chave')->assertActionVisible('reprocessar');
    }

    // ---------------------------------------------------------- acesso e rotas

    public function test_pdf_route_requires_login_and_an_editorial_role(): void
    {
        $import = Import::receive($this->pdf, 'tratado.pdf');
        $url = route('hub.imports.pdf', $import);

        $this->assertSame("/importacoes/{$import->id}/pdf", parse_url($url, PHP_URL_PATH));
        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs($this->user('expedicao'))->get($url)->assertForbidden();
        $this->actingAs($this->user('deposito'))->get($url)->assertForbidden();

        $response = $this->actingAs($this->user('editor'))->get($url);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());

        $this->actingAs($this->user('revisor'))->get($url)->assertOk();
    }

    public function test_pages_render_for_editorial_roles_and_not_for_expedition_or_deposit(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $import = $this->proposal();

        $urls = [
            ImportResource::getUrl(),
            ImportResource::getUrl('create'),
            ImportResource::getUrl('review', ['record' => $import]),
        ];
        $this->assertSame('/admin/importacoes', parse_url($urls[0], PHP_URL_PATH));
        $this->assertSame('/admin/importacoes/nova', parse_url($urls[1], PHP_URL_PATH));

        foreach (['expedicao', 'deposito'] as $role) {
            $u = $this->user($role);
            foreach ($urls as $url) {
                $this->assertContains($this->actingAs($u)->get($url)->status(), [403, 404], "{$url} aberto para {$role}");
            }
        }

        foreach (['admin', 'editor', 'revisor'] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->get($urls[0])->assertOk()->assertSee('tratado.pdf')->assertSee('Importar PDF');
            $this->actingAs($u)->get($urls[1])->assertOk()->assertSee('PDF do livro')->assertSee('Enviar para análise');
            $this->actingAs($u)->get($urls[2])->assertOk()->assertSee(PdfFixture::TITLE)->assertSee('hub-pdf-page');
        }

        // Atalhos: lista de livros e etapa 6 do assistente.
        $editor = $this->user('editor');
        $this->actingAs($editor)->get(BookResource::getUrl())->assertOk()->assertSee('Importar PDF');
        $book = $this->tratado();
        $this->actingAs($editor)->get(BookResource::getUrl('edit', ['record' => $book]))->assertOk()->assertSee('Importar PDF');
        $this->assertStringContainsString('?book='.$book->id, ImportResource::getUrl('create', ['book' => $book->id]));

        // `?book=` pré-seleciona o livro na criação.
        $this->actingAs($editor)->get(ImportResource::getUrl('create', ['book' => $book->id]))->assertOk()->assertSee($book->title);
    }
}
