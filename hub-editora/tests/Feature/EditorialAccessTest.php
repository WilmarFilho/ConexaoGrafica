<?php

namespace Tests\Feature;

use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ViewBook;
use App\Filament\Resources\Books\RelationManagers\ChaptersRelationManager;
use App\Filament\Resources\Chapters\ChapterResource;
use App\Filament\Resources\Chapters\Pages\ListChapters;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\People\Pages\ManagePeople;
use App\Filament\Resources\People\PersonResource;
use App\Models\Book;
use App\Models\Person;
use App\Models\SyncLog;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/** Perfis e telas do módulo Editorial no Filament. */
class EditorialAccessTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    private function user(string $role): User
    {
        // MFA é obrigatório no painel; marcado como configurado para não cair na tela de setup.
        return User::factory()->create(['role' => $role, 'app_authentication_secret' => 'segredo']);
    }

    private function panel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_roles_have_labels_and_the_expected_editorial_permissions(): void
    {
        $this->assertSame(['admin', 'expedicao', 'editor', 'revisor', 'deposito', 'vendedor'], array_keys(User::ROLES));
        $this->assertSame('Depósito', (new User(['role' => 'deposito']))->roleLabel());

        $perm = fn (string $role) => [
            (new User(['role' => $role]))->canAccessEditorial(),
            (new User(['role' => $role]))->canEditEditorial(),
            (new User(['role' => $role]))->canApproveEditorial(),
            (new User(['role' => $role]))->canDeposit(),
        ];

        $this->assertSame([true, true, true, true], $perm('admin'));
        $this->assertSame([false, false, false, false], $perm('expedicao'));
        $this->assertSame([true, true, false, false], $perm('editor'));
        $this->assertSame([true, true, true, false], $perm('revisor'));
        $this->assertSame([true, false, false, true], $perm('deposito'));
    }

    public function test_expedition_cannot_open_the_publications_menu_but_the_editorial_roles_can(): void
    {
        $this->seed(ChannelSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $urls = [
            BookResource::getUrl(),
            ChapterResource::getUrl(),
            PersonResource::getUrl(),
            InstitutionResource::getUrl(),
        ];

        $this->assertSame('/admin/livros', parse_url($urls[0], PHP_URL_PATH));

        $exp = $this->user('expedicao');
        foreach ($urls as $url) {
            $this->assertContains($this->actingAs($exp)->get($url)->status(), [403, 404], "{$url} aberto para a expedição");
        }
        $this->actingAs($exp)->get('/admin/orders')->assertOk();

        foreach (['admin', 'editor', 'revisor', 'deposito'] as $role) {
            $u = $this->user($role);
            foreach ($urls as $url) {
                $this->actingAs($u)->get($url)->assertOk();
            }
        }

        // Depósito só consulta: não cria livro nem pessoa.
        $this->actingAs($this->user('deposito'))->get(BookResource::getUrl('create'))->assertForbidden();
        $this->actingAs($this->user('editor'))->get(BookResource::getUrl('create'))->assertOk()->assertSee('Dados da obra');
    }

    public function test_create_wizard_saves_a_draft_with_generated_doi_slug_and_default_commercial_links(): void
    {
        $this->panel($this->user('editor'));

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Atualidades em Ultrassonografia',
                'subtitle' => 'Volume 2',
                'type' => 'edited_book',
                'language' => 'pt',
                'publisher' => 'Conexão Editora',
                'publication_place' => 'Goiânia',
                'publication_year' => 2026,
                'format' => 'both',
                'subject_area' => 'Medicina',
                'isbn_print' => '978-85-333-0227-3',
                'doi_prefix' => '10.00000',
                'doi_suffix' => 'conexao.2026.001',
                'abstract' => 'Resumo científico.',
                'keywords' => ['Ultrassonografia'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $book = Book::firstOrFail();
        $this->assertSame('draft', $book->status);
        $this->assertSame('atualidades-em-ultrassonografia', $book->slug);
        $this->assertSame('10.00000/conexao.2026.001', $book->doi);
        $this->assertSame(['print', 'ebook', 'combo'], $book->commercialLinks->pluck('modality')->all(), 'as três modalidades já vêm na etapa 7');
        $this->assertSame(1, SyncLog::where('action', 'editorial.book_created')->count());
    }

    public function test_create_wizard_rejects_invalid_isbn_and_used_doi_suffix(): void
    {
        $this->panel($this->user('editor'));
        $this->makeBook(['title' => 'Já existe', 'doi_suffix' => 'conexao.2026.001']);

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Outro', 'publication_year' => 2026, 'subject_area' => 'Medicina', 'abstract' => 'x',
                'isbn_print' => '978-85-333-0227-4',
                'doi_suffix' => 'conexao.2026.001',
            ])
            ->call('create')
            ->assertHasFormErrors(['isbn_print', 'doi_suffix']);

        $this->assertSame(1, Book::count());
    }

    public function test_edit_page_renders_the_eight_steps_with_the_chapters_manager(): void
    {
        $editor = $this->user('editor');
        $book = $this->tratado();

        $this->actingAs($editor)->get(BookResource::getUrl('edit', ['record' => $book]))
            ->assertOk()
            ->assertSee('Dados da obra')
            ->assertSee('Autores e organizadores')
            ->assertSee('Identificadores e DOI')
            ->assertSee('Comercialização')
            ->assertSee('Revisão')
            ->assertSee('Importar JSON')
            ->assertSee('Uma breve história das doenças raras')
            ->assertSee('Verificar campos');

        $this->actingAs($this->user('deposito'))->get(BookResource::getUrl('edit', ['record' => $book]))->assertForbidden();
        $this->actingAs($this->user('deposito'))->get(BookResource::getUrl('view', ['record' => $book]))->assertOk()->assertSee('Checklist');
    }

    public function test_chapters_manager_imports_json_and_the_reviewer_approves(): void
    {
        $book = $this->makeBook(['doi_suffix' => 'conexao.2026.001']);
        $this->panel($this->user('editor'));

        $json = json_encode([
            ['title' => 'Primeiro', 'first_page' => 1, 'last_page' => 10, 'contributors' => [['name' => 'Ana Exemplo Rocha', 'orcid' => '0000-0001-5109-3700']]],
            ['title' => 'Segundo', 'first_page' => 11, 'last_page' => 20, 'contributors' => [['name' => 'Fulano Sem Nada']]],
        ]);

        $rm = fn () => Livewire::test(ChaptersRelationManager::class, ['ownerRecord' => $book, 'pageClass' => EditBook::class]);

        $rm()->callTableAction('importar_json', data: ['json' => $json])
            ->assertHasNoTableActionErrors()
            ->assertNotified('2 capítulo(s) importado(s)');

        $this->assertSame(2, $book->chapters()->count());
        [$primeiro, $segundo] = $book->chapters()->orderBy('sequence')->get();

        // Editor não vê "Aprovar"; revisor vê e aprova.
        $rm()->assertTableActionHidden('aprovar', $primeiro);

        $this->panel($this->user('revisor'));
        $rm()->assertTableActionVisible('aprovar', $primeiro)
            ->callTableAction('aprovar', $primeiro)
            ->assertNotified('Capítulo aprovado');
        $this->assertSame('approved', $primeiro->fresh()->status);

        // Capítulo com autor sem ORCID nem instituição não aprova.
        $rm()->callTableAction('aprovar', $segundo)->assertNotified('Não foi possível');
        $this->assertSame('draft', $segundo->fresh()->status);

        Livewire::test(ListChapters::class)->assertCanSeeTableRecords([$primeiro, $segundo]);
    }

    public function test_view_page_shows_the_transition_actions_by_role_and_runs_them(): void
    {
        $book = $this->tratado();
        $book->update(['status' => 'draft', 'isbn_print' => '978-85-333-0227-3', 'isbn_ebook' => null]);

        $this->panel($this->user('editor'));
        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertActionVisible('enviar_revisao')
            ->assertActionHidden('aprovar')
            ->assertActionHidden('publicar')
            ->callAction('enviar_revisao')
            ->assertNotified('Livro enviado para revisão');
        $this->assertSame('review', $book->fresh()->status);

        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertActionHidden('aprovar');

        $this->panel($this->user('revisor'));
        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertActionVisible('aprovar')
            ->callAction('aprovar')
            ->assertNotified('Livro aprovado');
        $this->assertSame('approved', $book->fresh()->status);

        // Sem o site configurado, publicar avisa em vez de enfileirar.
        config(['hub.wordpress.url' => null, 'hub.wordpress.secret' => null]);
        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertActionVisible('publicar')
            ->callAction('publicar')
            ->assertNotified('Não foi possível');
        $this->assertSame('approved', $book->fresh()->status);

        $this->panel($this->user('deposito'));
        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertActionHidden('publicar')
            ->assertActionHidden('aprovar');
    }

    public function test_people_screen_validates_orcid_and_generates_sort_name(): void
    {
        $this->panel($this->user('editor'));

        Livewire::test(ManagePeople::class)
            ->callAction('create', data: ['name' => 'Pedro Pires Ferreira Neto', 'orcid' => '0000-0002-1825-0098'])
            ->assertHasActionErrors(['orcid']);

        Livewire::test(ManagePeople::class)
            ->callAction('create', data: ['name' => 'Pedro Pires Ferreira Neto', 'orcid' => 'https://orcid.org/0000-0002-1825-0097'])
            ->assertHasNoActionErrors();

        $p = Person::firstOrFail();
        $this->assertSame('FERREIRA NETO, Pedro Pires', $p->sort_name);
        $this->assertSame('0000-0002-1825-0097', $p->orcid);
    }
}
