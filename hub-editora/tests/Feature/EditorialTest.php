<?php

namespace Tests\Feature;

use App\Editorial\BookWorkflow;
use App\Editorial\ChapterImporter;
use App\Editorial\DoiSuffix;
use App\Editorial\EditorialException;
use App\Editorial\Slug;
use App\Editorial\WordPress\PublishBook;
use App\Models\Chapter;
use App\Models\Institution;
use App\Models\Person;
use App\Models\SyncLog;
use App\Models\User;
use App\Rules\Isbn13;
use App\Rules\Orcid;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/** Regras, models e transições do módulo Editorial (frente A). */
class EditorialTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    // ---- Validações ----------------------------------------------------------

    public function test_isbn13_rule_checks_the_check_digit_and_ignores_hyphens(): void
    {
        $this->assertTrue(Isbn13::isValid('978-85-333-0227-3'));
        $this->assertTrue(Isbn13::isValid('9788533302273'));
        $this->assertTrue(Isbn13::isValid('979-10-90636-07-1'));
        $this->assertFalse(Isbn13::isValid('978-85-333-0227-4'), 'dígito verificador errado');
        $this->assertFalse(Isbn13::isValid('85-333-0227-3'), 'ISBN-10 não vale');
        $this->assertFalse(Isbn13::isValid('978-65-00000-00-1'), 'o ISBN do JSON de exemplo é fictício');

        $v = Validator::make(['isbn' => '978-85-333-0227-4'], ['isbn' => [new Isbn13]]);
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('ISBN-13 inválido', $v->errors()->first('isbn'));
        $this->assertTrue(Validator::make(['isbn' => null], ['isbn' => [new Isbn13]])->passes(), 'vazio passa; obrigatoriedade é do checklist');
    }

    public function test_orcid_rule_applies_iso_7064_and_normalizes_the_url_form(): void
    {
        $this->assertTrue(Orcid::isValid('0000-0002-1825-0097'));
        $this->assertTrue(Orcid::isValid('https://orcid.org/0000-0001-5109-3700'));
        $this->assertTrue(Orcid::isValid('0000000218250097'));
        $this->assertTrue(Orcid::isValid('0000-0002-1694-233X'), 'verificador X');
        $this->assertFalse(Orcid::isValid('0000-0002-1825-0098'));
        $this->assertFalse(Orcid::isValid('0000-0002-1825'));

        $this->assertSame('0000-0001-5109-3700', Orcid::normalize('https://orcid.org/0000-0001-5109-3700'));
        $this->assertSame('0000-0002-1694-233X', Orcid::normalize('0000 0002 1694 233x'));

        $v = Validator::make(['orcid' => '0000-0002-1825-0098'], ['orcid' => [new Orcid]]);
        $this->assertStringContainsString('ORCID inválido', $v->errors()->first('orcid'));
    }

    public function test_person_gets_sort_name_and_canonical_orcid(): void
    {
        $maria = Person::create(['name' => ' Maria Exemplo da Silva ', 'orcid' => 'https://orcid.org/0000-0002-1825-0097']);
        $this->assertSame('SILVA, Maria Exemplo da', $maria->sort_name);
        $this->assertSame('0000-0002-1825-0097', $maria->orcid);
        $this->assertSame('SILVA', $maria->surname());
        $this->assertSame('Maria Exemplo da', $maria->givenName());

        $this->assertSame('FERREIRA NETO, Pedro Pires', Person::sortNameFor('Pedro Pires Ferreira Neto'));
        $this->assertSame('ROCHA, Ana Exemplo', Person::sortNameFor('Ana Exemplo Rocha'));
        $this->assertSame('MADONNA', Person::sortNameFor('Madonna'));

        $custom = Person::create(['name' => 'Nice Carvalho', 'sort_name' => 'CARVALHO, N.']);
        $this->assertSame('CARVALHO, N.', $custom->sort_name, 'informado não é sobrescrito');
    }

    // ---- DOI e slug ------------------------------------------------------------

    public function test_doi_suffix_is_sequential_per_year_and_chapters_follow_the_book(): void
    {
        $this->assertSame('10.00000', DoiSuffix::prefix(), 'prefixo de demonstração até a frente D definir CROSSREF_PREFIX');
        $this->assertSame('conexao.2026.001', DoiSuffix::forBook(2026));

        $a = $this->makeBook(['title' => 'Primeiro']);
        $this->assertSame('conexao.2026.001', $a->doi_suffix, 'gerado ao salvar quando vazio');
        $this->assertSame('10.00000/conexao.2026.001', $a->doi);

        $b = $this->makeBook(['title' => 'Segundo']);
        $this->assertSame('conexao.2026.002', $b->doi_suffix);
        $this->assertSame('conexao.2025.001', DoiSuffix::forBook(2025), 'sequência é por ano');

        $this->makeBook(['title' => 'Manual', 'doi_suffix' => 'conexao.2026.010']);
        $this->assertSame('conexao.2026.011', DoiSuffix::forBook(2026), 'continua do maior usado');

        $c1 = $this->chapterFor($a, ['title' => 'Um']);
        $c2 = $this->chapterFor($a, ['title' => 'Dois']);
        $this->assertSame([1, 2], [$c1->sequence, $c2->sequence]);
        $this->assertSame('conexao.2026.001.01', $c1->doi_suffix);
        $this->assertSame('10.00000/conexao.2026.001.02', $c2->doi);
        $this->assertSame('conexao.2026.001.100', DoiSuffix::forChapter($a, 100), '3 dígitos a partir de 100');

        $this->assertFalse(DoiSuffix::isAvailable('10.00000', 'conexao.2026.001'));
        $this->assertFalse(DoiSuffix::isAvailable('10.00000', 'conexao.2026.001.01'), 'sufixo de capítulo também conta');
        $this->assertTrue(DoiSuffix::isAvailable('10.00000', 'conexao.2026.001', $a), 'o próprio livro não conflita consigo');
        $this->assertTrue(DoiSuffix::isAvailable('10.00000', 'conexao.2026.999'));

        config(['hub.crossref.prefix' => '10.12345']);
        $this->assertSame('conexao.2026.001', DoiSuffix::forBook(2026), 'outro prefixo, outra sequência');
    }

    public function test_doi_must_be_unique_and_cannot_change_after_validation(): void
    {
        $a = $this->makeBook(['title' => 'A', 'doi_suffix' => 'conexao.2026.001']);

        $this->expectException(QueryException::class);
        $this->makeBook(['title' => 'B', 'doi_suffix' => 'conexao.2026.001']);
    }

    public function test_doi_suffix_is_editable_only_while_draft_and_not_validated(): void
    {
        $book = $this->makeBook();
        $this->assertTrue($book->isDoiEditable());

        $book->update(['doi_suffix' => 'conexao.2026.077']);
        $this->assertSame('conexao.2026.077', $book->fresh()->doi_suffix);

        $book->update(['doi_status' => 'validated']);
        $this->assertFalse($book->fresh()->isDoiEditable());

        $this->expectException(EditorialException::class);
        $book->fresh()->update(['doi_suffix' => 'conexao.2026.078']);
    }

    public function test_slug_is_generated_from_the_title_made_unique_and_locked_after_sending(): void
    {
        $a = $this->makeBook(['title' => 'Tratado de Doenças Raras']);
        $b = $this->makeBook(['title' => 'Tratado de Doenças Raras']);
        $this->assertSame('tratado-de-doencas-raras', $a->slug);
        $this->assertSame('tratado-de-doencas-raras-2', $b->slug);

        $a->update(['title' => 'Outro título']);
        $this->assertSame('tratado-de-doencas-raras', $a->fresh()->slug, 'título novo não troca o slug sozinho');

        $a->update(['slug' => 'Tratado Novo']);
        $this->assertSame('tratado-novo', $a->fresh()->slug, 'editável e normalizado enquanto o DOI não foi enviado');

        $a->update(['doi_status' => 'sent']);
        $this->assertTrue(Slug::isLocked($a->fresh()));

        $x = $this->chapterFor($a, ['title' => 'Capítulo']);
        $y = $this->chapterFor($a, ['title' => 'Capítulo']);
        $this->assertSame(['capitulo', 'capitulo-2'], [$x->slug, $y->slug], 'único dentro do livro');
        $z = $this->chapterFor($b, ['title' => 'Capítulo']);
        $this->assertSame('capitulo', $z->slug, 'outro livro pode repetir');

        try {
            $a->fresh()->update(['slug' => 'nao-pode']);
            $this->fail('slug mudou depois do envio');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('não pode mudar', $e->getMessage());
        }

        $a->fresh()->update(['title' => 'Título pode mudar']);
        $this->assertSame('tratado-novo', $a->fresh()->slug);
    }

    // ---- Transições ----------------------------------------------------------

    public function test_book_goes_from_draft_to_review_to_approved_with_checklist_and_audit(): void
    {
        $editor = User::factory()->editor()->create();
        $revisor = User::factory()->revisor()->create();
        $book = $this->tratado();
        $book->update(['status' => 'draft']);

        $this->assertFalse(BookWorkflow::isReadyForReview($book), 'o ISBN do exemplo não passa no checksum');
        $labels = collect(BookWorkflow::checklist($book))->pluck('ok', 'key');
        $this->assertTrue($labels['contributors']);
        $this->assertTrue($labels['chapters_approved']);
        $this->assertFalse($labels['isbn'], 'os ISBNs do exemplo são fictícios');

        // Checklist pendente trava o envio: o ISBN fictício do exemplo.
        try {
            BookWorkflow::submitForReview($book, $editor);
            $this->fail('enviou com checklist pendente');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('ISBN-13', $e->getMessage());
        }

        $book->update(['isbn_print' => '978-85-333-0227-3', 'isbn_ebook' => null]);
        $this->assertTrue(BookWorkflow::isReadyForReview($book->fresh()));
        $this->assertSame([], BookWorkflow::pending($book->fresh(), 'approve'));

        $this->actingAs($editor);
        BookWorkflow::submitForReview($book->fresh(), $editor);
        $this->assertSame('review', $book->fresh()->status);

        $log = SyncLog::where('action', 'editorial.review_requested')->firstOrFail();
        $this->assertSame($editor->id, $log->user_id);
        $this->assertSame(['from' => 'draft', 'to' => 'review'], array_intersect_key($log->context, ['from' => 1, 'to' => 1]));

        // Editor não aprova; revisor sim.
        try {
            BookWorkflow::approve($book->fresh(), $editor);
            $this->fail('editor aprovou');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('revisores', $e->getMessage());
        }
        $this->assertSame('review', $book->fresh()->status);

        BookWorkflow::approve($book->fresh(), $revisor);
        $book->refresh();
        $this->assertSame('approved', $book->status);
        $this->assertSame($revisor->id, $book->approved_by);
        $this->assertNotNull($book->approved_at);
        $this->assertSame(1, SyncLog::where('action', 'editorial.approved')->count());

        // Rascunho não publica; aprovado enfileira o job.
        Queue::fake();
        config(['hub.wordpress.url' => 'https://site.test', 'hub.wordpress.secret' => str_repeat('s', 64)]);
        BookWorkflow::requestPublish($book, $editor);
        Queue::assertPushed(PublishBook::class, fn (PublishBook $j) => $j->bookId === $book->id && $j->userId === $editor->id);
        $this->assertSame(1, SyncLog::where('action', 'editorial.publish_requested')->count());

        BookWorkflow::backToDraft($book, $revisor, 'faltou a capa');
        $this->assertSame('draft', $book->fresh()->status);
        $this->assertNull($book->fresh()->approved_by);
        $this->assertStringContainsString('faltou a capa', SyncLog::where('action', 'editorial.returned')->firstOrFail()->message);

        try {
            BookWorkflow::requestPublish($book->fresh(), $editor);
            $this->fail('rascunho foi para o site');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('aprovados', $e->getMessage());
        }
        Queue::assertPushed(PublishBook::class, 1);
    }

    public function test_approval_requires_every_chapter_approved_and_people_with_orcid_or_institution(): void
    {
        $revisor = User::factory()->revisor()->create();
        $book = $this->tratado();
        $book->update(['status' => 'draft', 'isbn_print' => '978-85-333-0227-3', 'isbn_ebook' => null]);

        $pendente = $this->chapterFor($book, ['title' => 'Novo capítulo', 'first_page' => 53, 'last_page' => 60]);
        $semNada = Person::create(['name' => 'Fulano de Tal']);
        $pendente->contributions()->create(['person_id' => $semNada->id, 'role' => 'author', 'sequence' => 1]);

        $pending = BookWorkflow::pending($book->fresh(), 'review');
        $this->assertContains('Capítulos completos (título, páginas, autores com ORCID ou instituição)', $pending);

        $pendente->contributions()->first()->update(['affiliation_text' => 'Clínica Exemplo']);
        $this->assertSame([], BookWorkflow::pending($book->fresh(), 'review'));
        $this->assertSame(['Capítulos aprovados'], BookWorkflow::pending($book->fresh(), 'approve'));

        BookWorkflow::submitForReview($book->fresh());
        try {
            BookWorkflow::approve($book->fresh(), $revisor);
            $this->fail('aprovou com capítulo pendente');
        } catch (EditorialException $e) {
            $this->assertStringContainsString('Capítulos aprovados', $e->getMessage());
        }

        $pendente->update(['abstract_original' => 'Resumo do original.', 'abstract_ai' => 'Sugestão da IA.']);
        BookWorkflow::approveChapter($pendente->fresh(), $revisor);
        $pendente->refresh();
        $this->assertSame('approved', $pendente->status);
        $this->assertSame('Resumo do original.', $pendente->abstract_approved, 'sem aprovado explícito, vale o original antes da IA');
        $this->assertSame($revisor->id, $pendente->approved_by);
        $this->assertSame(1, SyncLog::where('action', 'editorial.chapter_approved')->count());

        BookWorkflow::approve($book->fresh(), $revisor);
        $this->assertSame('approved', $book->fresh()->status);
    }

    public function test_editor_cannot_approve_chapters_and_unpublish_needs_a_published_book(): void
    {
        $editor = User::factory()->editor()->create();
        $book = $this->tratado();
        $chapter = $this->chapterFor($book, ['title' => 'X', 'status' => 'review']);

        $this->expectException(EditorialException::class);
        BookWorkflow::approveChapter($chapter, $editor);
    }

    public function test_renumbering_chapter_suffixes_follows_the_current_order_and_skips_validated_ones(): void
    {
        $book = $this->makeBook();
        $a = $this->chapterFor($book, ['title' => 'A']); // .01
        $b = $this->chapterFor($book, ['title' => 'B']); // .02
        $c = $this->chapterFor($book, ['title' => 'C', 'doi_status' => 'validated']); // .03, travado

        $a->update(['sequence' => 2]);
        $b->update(['sequence' => 1]);

        $this->assertSame(2, BookWorkflow::renumberChapterSuffixes($book));
        $this->assertSame('conexao.2026.001.02', $a->fresh()->doi_suffix);
        $this->assertSame('conexao.2026.001.01', $b->fresh()->doi_suffix);
        $this->assertSame('conexao.2026.001.03', $c->fresh()->doi_suffix);
    }

    // ---- Importação de capítulos por JSON --------------------------------------

    public function test_chapters_can_be_imported_from_the_contract_json(): void
    {
        $book = $this->makeBook(['doi_suffix' => 'conexao.2026.001']);
        $existing = Person::create(['name' => 'Maria Exemplo da Silva', 'orcid' => '0000-0002-1825-0097']);
        Institution::create(['name' => 'Universidade Federal de Goiás', 'acronym' => 'UFG', 'city' => 'Goiânia', 'state' => 'GO']);

        $chapters = $this->examplePayload()['chapters'];
        $chapters[] = ['title' => 'Capítulo sem nada', 'first_page' => 53, 'last_page' => 60, 'contributors' => [['name' => 'Novo Autor', 'affiliation' => 'Clínica Exemplo']]];

        $r = ChapterImporter::import($book, json_encode(['chapters' => $chapters]));

        $this->assertSame(['chapters' => 3, 'people' => 3, 'institutions' => 1], $r);
        $this->assertSame(1, Person::where('orcid', '0000-0002-1825-0097')->count(), 'pessoa existente reaproveitada pelo ORCID');
        $this->assertSame(2, Institution::count(), 'UFG reaproveitada; Hospital criado');

        $book->refresh();
        $this->assertSame([1, 2, 3], $book->chapters->pluck('sequence')->all());
        $first = $book->chapters->first();
        $this->assertSame('Uma breve história das doenças raras', $first->title);
        $this->assertSame('conexao.2026.001.01', $first->doi_suffix);
        $this->assertSame('uma-breve-historia-das-doencas-raras', $first->slug);
        $this->assertSame([21, 34], [$first->first_page, $first->last_page]);
        $this->assertSame('Este capítulo apresenta a evolução do conceito de doença rara, os marcos regulatórios internacionais e brasileiros e o papel das associações de pacientes.', $first->abstract_original);
        $this->assertNull($first->abstract_approved, 'importado não é aprovado');
        $this->assertSame('draft', $first->status);
        $this->assertSame(['Ana Exemplo Rocha', 'Maria Exemplo da Silva'], $first->contributions->map(fn ($c) => $c->person->name)->all());
        $this->assertSame($existing->id, $first->contributions[1]->person_id);
        $this->assertSame('UFG', $first->contributions[0]->institution->acronym);
        $this->assertCount(1, $first->references);

        $last = $book->chapters->last();
        $this->assertSame('Clínica Exemplo', $last->contributions->first()->affiliation_text, 'afiliação em texto quando não é objeto');
        $this->assertNull($last->contributions->first()->institution_id);
        $this->assertSame('conexao.2026.001.03', $last->doi_suffix);

        $log = SyncLog::where('action', 'editorial.chapters_imported')->firstOrFail();
        $this->assertSame($book->id, $log->subject_id);
        $this->assertSame('book', $log->subject_type, 'morph map do contrato');

        $this->expectException(EditorialException::class);
        ChapterImporter::import($book, '{"isso": "não é lista"}');
    }

    public function test_chapter_pages_must_be_in_order(): void
    {
        $book = $this->makeBook();

        $this->expectException(EditorialException::class);
        $this->chapterFor($book, ['first_page' => 20, 'last_page' => 10]);
    }

    public function test_contribution_is_unique_per_person_and_role(): void
    {
        $book = $this->makeBook();
        $p = Person::create(['name' => 'Alguém']);
        $book->contributions()->create(['person_id' => $p->id, 'role' => 'editor', 'sequence' => 1]);
        $book->contributions()->create(['person_id' => $p->id, 'role' => 'author', 'sequence' => 2]);
        $this->assertCount(2, $book->contributions);

        $this->expectException(QueryException::class);
        $book->contributions()->create(['person_id' => $p->id, 'role' => 'editor', 'sequence' => 3]);
    }

    public function test_chapter_is_deleted_with_the_book_and_knows_its_inherited_values(): void
    {
        $book = $this->makeBook(['language' => 'en', 'publication_date' => '2026-03-15']);
        $chapter = $this->chapterFor($book);
        $this->assertSame('en', $chapter->effectiveLanguage());
        $this->assertSame('2026-03-15', $chapter->effectivePublicationDate()?->format('Y-m-d'));

        $book->delete();
        $this->assertSame(0, Chapter::count());
    }
}
