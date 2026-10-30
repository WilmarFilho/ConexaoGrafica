<?php

namespace Tests\Support;

use App\Editorial\Import\Ai\AiGateway;
use App\Editorial\Import\Ai\StructuredResult;
use Tests\Support\PdfFixture as F;

/**
 * API da OpenAI simulada: identifica a etapa pelo nome do schema pedido (front_matter,
 * chapter_part, chapter, book_summary) e devolve JSON plausível para o livro do PdfFixture.
 * Cada chamada custa 1.000 tokens de entrada e 200 de saída.
 * `failures` permite forçar uma exceção na n-ésima chamada (1-based).
 */
class FakeAiGateway implements AiGateway
{
    /** @var list<array{stage:string, instructions:string, content:array, schema:array, max_tokens:int}> */
    public array $calls = [];

    /** @var array<int, \Throwable> número da chamada → exceção a lançar */
    public array $failures = [];

    public array $usage = ['input_tokens' => 1000, 'output_tokens' => 200, 'cached_input_tokens' => 0, 'reasoning_tokens' => 0];

    public function structured(string $instructions, array $content, string $schemaName, array $schema, int $maxTokens): StructuredResult
    {
        $stage = $schemaName;
        $this->calls[] = compact('stage', 'instructions', 'content', 'schema') + ['max_tokens' => $maxTokens];
        $n = count($this->calls);
        if (isset($this->failures[$n])) {
            throw $this->failures[$n];
        }

        $text = implode("\n", array_map(fn ($b) => $b['text'] ?? '', $content));
        $data = match ($stage) {
            'front_matter' => $this->frontMatter(),
            'chapter_part' => $this->part($text),
            'book_summary' => $this->bookSummary(),
            default => str_contains($text, F::CH2) || str_contains($text, 'key_points') ? $this->chapter2() : $this->chapter1(),
        };

        return new StructuredResult($data, $this->usage, (string) config('hub.ai.model'), 'completed');
    }

    public function frontMatter(): array
    {
        return [
            'title' => F::TITLE, 'subtitle' => 'Diagnostico, manejo e politicas publicas', 'alt_title' => null, 'alt_title_lang' => null,
            'type' => 'edited_book', 'edition_number' => 1, 'volume' => null, 'series_title' => null, 'series_issn' => null,
            'language' => 'pt', 'publisher' => 'Conexao Editora', 'publication_place' => 'Goiania', 'publication_year' => 2026, 'publication_date' => null,
            'pages' => null, 'isbn_print' => '978-65-00000-00-9', 'isbn_ebook' => '978-65-00000-01-6', 'other_isbns' => [],
            'subject_area' => 'Medicina', 'subject_subarea' => 'Genetica medica', 'keywords' => ['Doencas raras', 'Genetica'], 'abstract' => null,
            'contributors' => [
                ['name' => 'Maria Exemplo da Silva', 'role' => 'editor', 'affiliation' => 'Universidade Federal de Goias', 'city' => 'Goiania', 'state' => 'GO', 'country' => 'BR', 'orcid' => '0000-0002-1825-0097',
                    'evidence' => ['pdf_page' => 1, 'excerpt' => 'Organizadores: Maria Exemplo da Silva'], 'confidence' => 0.95],
                ['name' => 'Joao Exemplo Pereira', 'role' => 'editor', 'affiliation' => null, 'city' => null, 'state' => null, 'country' => null, 'orcid' => null,
                    'evidence' => ['pdf_page' => 1, 'excerpt' => 'e Joao Exemplo Pereira'], 'confidence' => 0.9],
                ['name' => 'Ana Exemplo Rocha', 'role' => 'author', 'affiliation' => 'Universidade Federal de Goias', 'city' => null, 'state' => null, 'country' => null, 'orcid' => null,
                    'evidence' => ['pdf_page' => 2, 'excerpt' => 'Ana Exemplo Rocha - Universidade Federal de Goias'], 'confidence' => 0.9],
                ['name' => 'Carlos Exemplo Lima', 'role' => 'author', 'affiliation' => 'Hospital das Clinicas da UFG', 'city' => null, 'state' => null, 'country' => null, 'orcid' => null,
                    'evidence' => ['pdf_page' => 2, 'excerpt' => 'Carlos Exemplo Lima - Hospital das Clinicas da UFG'], 'confidence' => 0.9],
            ],
            'toc' => [[
                'section' => 'Secao 1 - Fundamentos',
                'chapters' => [
                    ['number' => '1', 'title' => F::CH1, 'authors' => [], 'printed_page' => 9],
                    ['number' => '2', 'title' => F::CH2, 'authors' => [], 'printed_page' => 11],
                ],
            ]],
            'end_of_chapters_printed_page' => 13,
            'evidence' => [
                ['field' => 'title', 'pdf_page' => 1, 'excerpt' => F::TITLE, 'confidence' => 0.98],
                ['field' => 'isbn_print', 'pdf_page' => 2, 'excerpt' => 'ISBN 978-65-00000-00-9 (impresso)', 'confidence' => 0.95],
                ['field' => 'publication_year', 'pdf_page' => 1, 'excerpt' => 'Goiania - 2026', 'confidence' => 0.9],
            ],
            'notes' => null,
        ];
    }

    public function chapter1(): array
    {
        return [
            'title' => F::CH1, 'subtitle' => null, 'title_en' => null, 'number' => '1', 'language' => 'pt',
            'authors' => [
                ['name' => 'Ana Exemplo Rocha', 'affiliation' => null, 'city' => null, 'state' => null, 'country' => null, 'orcid' => null, 'book_contributor' => 'Ana Exemplo Rocha',
                    'evidence' => ['pdf_page' => 4, 'excerpt' => 'Ana Exemplo Rocha, Maria Exemplo da Silva'], 'confidence' => 0.95],
                ['name' => 'Maria Exemplo da Silva', 'affiliation' => null, 'city' => null, 'state' => null, 'country' => null, 'orcid' => null, 'book_contributor' => 'Maria Exemplo da Silva',
                    'evidence' => ['pdf_page' => 4, 'excerpt' => 'Ana Exemplo Rocha, Maria Exemplo da Silva'], 'confidence' => 0.95],
            ],
            'abstract_original' => 'Este capitulo apresenta a evolucao do conceito de doenca rara, os marcos regulatorios internacionais e brasileiros e o papel das associacoes.',
            'abstract_ai' => null,
            'keywords_original' => ['doencas raras', 'historia da medicina', 'legislacao'],
            'keywords_ai' => [],
            'references' => [['text' => 'BRASIL. Ministerio da Saude. Portaria n. 199, de 30 de janeiro de 2014.', 'doi' => null]],
            'evidence' => [
                ['field' => 'title', 'pdf_page' => 4, 'excerpt' => F::CH1, 'confidence' => 0.98],
                ['field' => 'abstract_original', 'pdf_page' => 4, 'excerpt' => 'Resumo: Este capitulo apresenta', 'confidence' => 0.95],
                ['field' => 'keywords_original', 'pdf_page' => 4, 'excerpt' => 'Palavras-chave: doencas raras', 'confidence' => 0.95],
            ],
            'notes' => null,
        ];
    }

    public function chapter2(): array
    {
        $abstract = str_repeat('As doencas raras sao em sua maioria de origem genetica e este capitulo revisa os mecanismos moleculares envolvidos. ', 8);

        return [
            'title' => F::CH2, 'subtitle' => null, 'title_en' => null, 'number' => '2', 'language' => 'pt',
            'authors' => [
                ['name' => 'Carlos E. Lima', 'affiliation' => null, 'city' => null, 'state' => null, 'country' => null, 'orcid' => null, 'book_contributor' => 'Carlos Exemplo Lima',
                    'evidence' => ['pdf_page' => 6, 'excerpt' => 'Carlos Exemplo Lima'], 'confidence' => 0.9],
            ],
            'abstract_original' => null,
            'abstract_ai' => trim($abstract),
            'keywords_original' => [],
            'keywords_ai' => ['Genetica', 'Diagnostico molecular', 'Aconselhamento genetico'],
            'references' => [],
            'evidence' => [['field' => 'title', 'pdf_page' => 6, 'excerpt' => F::CH2, 'confidence' => 0.97]],
            'notes' => null,
        ];
    }

    public function bookSummary(): array
    {
        return [
            'abstract' => trim(str_repeat('Esta obra reune capitulos sobre a historia e a genetica das doencas raras, para profissionais de saude e estudantes. ', 9)),
            'keywords' => ['Doencas raras', 'Genetica medica', 'Politicas publicas de saude', 'Diagnostico'],
        ];
    }

    private function part(string $text): array
    {
        return [
            'title' => str_contains($text, 'parte 1') ? F::CH2 : null, 'authors' => [], 'abstract_original' => null, 'keywords_original' => [], 'references' => [],
            'key_points' => ['Ponto da parte '.mb_substr($text, 0, 20)],
            'evidence' => [],
        ];
    }
}
