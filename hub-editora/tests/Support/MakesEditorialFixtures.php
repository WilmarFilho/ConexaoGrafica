<?php

namespace Tests\Support;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Institution;
use App\Models\Person;

/**
 * Fixtures do módulo Editorial. `tratado()` reproduz exatamente o
 * `conexao-editora/docs/exemplos/publicacao-tratado.json` (ids inclusive),
 * para o payload poder ser comparado byte a byte.
 */
trait MakesEditorialFixtures
{
    protected function examplePayload(): array
    {
        // Cópia de conexao-editora/docs/exemplos/publicacao-tratado.json (o servidor de testes só recebe hub-editora/).
        $path = dirname(__DIR__).'/Fixtures/publicacao-tratado.json';

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Livro mínimo válido (rascunho), sem pessoas nem capítulos. */
    protected function makeBook(array $attributes = []): Book
    {
        return Book::create(array_merge([
            'title' => 'Atualidades em Ultrassonografia',
            'publication_year' => 2026,
            'subject_area' => 'Medicina',
            'abstract' => 'Resumo científico da obra.',
            'isbn_print' => '978-85-333-0227-3', // válido
        ], $attributes));
    }

    /** O Tratado do JSON de exemplo: 2 organizadores, 2 capítulos, 4 pessoas, 3 instituições, 3 links. */
    protected function tratado(): Book
    {
        $ufg = Institution::create(['name' => 'Universidade Federal de Goiás', 'acronym' => 'UFG', 'city' => 'Goiânia', 'state' => 'GO', 'country' => 'BR']);
        $uerj = Institution::create(['name' => 'Universidade do Estado do Rio de Janeiro', 'city' => 'Rio de Janeiro', 'state' => 'RJ', 'country' => 'BR']);
        $hc = Institution::create(['name' => 'Hospital das Clínicas da UFG', 'city' => 'Goiânia', 'state' => 'GO', 'country' => 'BR']);

        $maria = Person::create(['id' => 1, 'name' => 'Maria Exemplo da Silva', 'orcid' => '0000-0002-1825-0097']);
        $joao = Person::create(['id' => 2, 'name' => 'João Exemplo Pereira']);
        $ana = Person::create(['id' => 3, 'name' => 'Ana Exemplo Rocha', 'orcid' => '0000-0001-5109-3700']);
        $carlos = Person::create(['id' => 4, 'name' => 'Carlos Exemplo Lima']);

        $book = Book::create([
            'id' => 1,
            'title' => 'Tratado de Doenças Raras',
            'subtitle' => 'Diagnóstico, manejo e políticas públicas',
            'alt_title' => 'Treatise on Rare Diseases',
            'alt_title_lang' => 'en',
            'type' => 'edited_book',
            'edition_number' => 1,
            'language' => 'pt',
            'publisher' => 'Conexão Editora',
            'publication_place' => 'Goiânia',
            'publication_year' => 2026,
            'publication_date' => '2026-03-15',
            'pages' => 993,
            'format' => 'both',
            'subject_area' => 'Medicina',
            'subject_subarea' => 'Genética médica',
            'audience' => 'Médicos, residentes e estudantes da área da saúde',
            'isbn_print' => '978-65-00000-00-1',
            'isbn_ebook' => '978-65-00000-01-8',
            'doi_prefix' => '10.00000',
            'doi_suffix' => 'conexao.2026.001',
            'keywords' => ['Doenças raras', 'Genética', 'Diagnóstico', 'Políticas públicas'],
            'keywords_en' => ['Rare diseases', 'Genetics', 'Diagnosis', 'Public policy'],
            'abstract' => 'Obra organizada que reúne 74 capítulos sobre o diagnóstico, o manejo clínico e as políticas públicas relacionadas às doenças raras no Brasil, escrita por especialistas de diversas instituições.',
            'synopsis' => 'O mais completo tratado brasileiro sobre doenças raras, com a contribuição de mais de 70 autores.',
            'references' => [],
            'status' => 'published',
        ]);

        $book->contributions()->create(['person_id' => $maria->id, 'role' => 'editor', 'sequence' => 1, 'institution_id' => $ufg->id]);
        $book->contributions()->create(['person_id' => $joao->id, 'role' => 'editor', 'sequence' => 2, 'institution_id' => $uerj->id]);

        $c1 = $book->chapters()->create([
            'id' => 10,
            'sequence' => 1,
            'number' => '1',
            'section' => 'Seção 1 — Fundamentos',
            'title' => 'Uma breve história das doenças raras',
            'title_en' => 'A brief history of rare diseases',
            'first_page' => 21,
            'last_page' => 34,
            'abstract_approved' => 'Este capítulo apresenta a evolução do conceito de doença rara, os marcos regulatórios internacionais e brasileiros e o papel das associações de pacientes.',
            'keywords' => ['Doenças raras', 'História da medicina', 'Legislação'],
            'references' => [['text' => 'BRASIL. Ministério da Saúde. Portaria nº 199, de 30 de janeiro de 2014. Diário Oficial da União, Brasília, 2014.', 'doi' => null]],
            'status' => 'approved',
        ]);
        $c1->contributions()->create(['person_id' => $ana->id, 'role' => 'author', 'sequence' => 1, 'institution_id' => $ufg->id]);
        $c1->contributions()->create(['person_id' => $maria->id, 'role' => 'author', 'sequence' => 2, 'institution_id' => $ufg->id]);

        $c2 = $book->chapters()->create([
            'id' => 11,
            'sequence' => 2,
            'number' => '2',
            'section' => 'Seção 1 — Fundamentos',
            'title' => 'Genética nas doenças raras',
            'first_page' => 35,
            'last_page' => 52,
            'abstract_approved' => 'Revisão dos mecanismos genéticos envolvidos nas doenças raras, dos métodos de diagnóstico molecular e do aconselhamento genético.',
            'keywords' => ['Genética', 'Diagnóstico molecular', 'Aconselhamento genético'],
            'references' => [],
            'status' => 'approved',
        ]);
        $c2->contributions()->create(['person_id' => $carlos->id, 'role' => 'author', 'sequence' => 1, 'institution_id' => $hc->id]);

        $book->commercialLinks()->createMany([
            ['modality' => 'print', 'label' => 'Comprar livro impresso', 'woo_product_id' => 1234, 'enabled' => true, 'sort_order' => 1],
            ['modality' => 'ebook', 'label' => 'Comprar e-book', 'woo_product_id' => 1235, 'enabled' => true, 'sort_order' => 2],
            ['modality' => 'combo', 'label' => 'Comprar impresso + e-book', 'woo_product_id' => null, 'enabled' => false, 'sort_order' => 3],
        ]);

        return $book->fresh();
    }

    protected function chapterFor(Book $book, array $attributes = []): Chapter
    {
        return $book->chapters()->create(array_merge([
            'title' => 'Capítulo de teste',
            'first_page' => 1,
            'last_page' => 10,
        ], $attributes));
    }
}
