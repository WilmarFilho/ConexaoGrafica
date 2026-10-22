<?php

namespace App\Editorial;

use Illuminate\Support\Str;

/**
 * Citações ABNT NBR 6023:2018, APA 7, Vancouver (NLM), BibTeX e RIS, para
 * livro e capítulo — porte fiel de `conexao-doi/inc/citacoes.php` (frente B),
 * sobre os mesmos arrays do payload (`Payload::forBook()['book']` e cada
 * item de `chapters`). As fichas PDF saem com o mesmo texto do site.
 *
 * Decisões onde a norma deixa escolha (as mesmas do plugin):
 *  - ABNT: "(org.)" no singular; título do livro em negrito; edição só a
 *    partir da 2ª; "DOI: 10.…".
 *  - APA: iniciais, "(Org.)/(Orgs.)", título em itálico, "https://doi.org/…".
 *  - Vancouver: até 6 autores, depois "et al"; "editores"; "doi: 10.…".
 *  - BibTeX: @book e @incollection; chave = sufixo do DOI sem pontuação.
 *  - RIS: TY BOOK / CHAP.
 */
class Citations
{
    public const LABELS = ['abnt' => 'ABNT', 'apa' => 'APA', 'vancouver' => 'Vancouver', 'bibtex' => 'BibTeX', 'ris' => 'RIS'];

    /** Os cinco formatos para um livro: [chave => [rotulo, html, texto, bloco]]. */
    public static function forBook(array $book): array
    {
        $people = self::bookPeople($book);

        return self::assemble([
            'abnt' => self::abntBook($book, $people),
            'apa' => self::apaBook($book, $people),
            'vancouver' => self::vancouverBook($book, $people),
            'bibtex' => self::bibtexBook($book, $people),
            'ris' => self::risBook($book, $people),
        ]);
    }

    /** Os cinco formatos para um capítulo. */
    public static function forChapter(array $chapter, array $book): array
    {
        $bookPeople = self::bookPeople($book);
        $authors = self::list($chapter['contributors'] ?? []);

        return self::assemble([
            'abnt' => self::abntChapter($chapter, $book, $authors, $bookPeople),
            'apa' => self::apaChapter($chapter, $book, $authors, $bookPeople),
            'vancouver' => self::vancouverChapter($chapter, $book, $authors, $bookPeople),
            'bibtex' => self::bibtexChapter($chapter, $book, $authors, $bookPeople),
            'ris' => self::risChapter($chapter, $book, $authors, $bookPeople),
        ]);
    }

    private static function assemble(array $html): array
    {
        $out = [];

        foreach ($html as $key => $content) {
            $out[$key] = [
                'rotulo' => self::LABELS[$key],
                'html' => $content,
                'texto' => html_entity_decode(trim(strip_tags($content)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'bloco' => in_array($key, ['bibtex', 'ris'], true),
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------- pessoas

    /** Contribuintes com nome, na ordem de `sequence` (igual a conexao_doi_lista). */
    public static function list($contributors): array
    {
        if (! is_array($contributors)) {
            return [];
        }

        $list = array_values(array_filter($contributors, static fn ($c) => is_array($c) && ! empty($c['name'])));
        usort($list, static fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));

        return $list;
    }

    /** Quem responde pelo livro: organizadores (role editor) quando há, senão autores. */
    private static function bookPeople(array $book): array
    {
        $all = self::list($book['contributors'] ?? []);
        $editors = array_values(array_filter($all, static fn ($c) => ($c['role'] ?? '') === 'editor'));

        if ($editors) {
            return ['lista' => $editors, 'org' => true];
        }

        $authors = array_values(array_filter($all, static fn ($c) => ($c['role'] ?? 'author') === 'author'));

        return ['lista' => $authors ?: $all, 'org' => false];
    }

    private static function nameParts(array $person): array
    {
        $sort = trim((string) ($person['sort_name'] ?? ''));

        if ($sort !== '' && str_contains($sort, ',')) {
            [$surname, $given] = array_map('trim', explode(',', $sort, 2));
        } else {
            $parts = preg_split('/\s+/', trim((string) ($person['name'] ?? ''))) ?: [];
            $surname = (string) array_pop($parts);
            $given = implode(' ', $parts);
        }

        return ['sobrenome' => $surname, 'nomes' => $given];
    }

    private static function surnameTitle(string $surname): string
    {
        return mb_convert_case(mb_strtolower($surname, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private static function initials(string $given, string $separator = '. ', bool $finalDot = true): string
    {
        $particles = ['da', 'de', 'do', 'das', 'dos', 'e', 'y', 'von', 'van', 'del', 'della', 'di', 'la', 'le'];
        $initials = [];

        foreach (preg_split('/[\s-]+/', trim($given)) ?: [] as $name) {
            if ($name === '' || in_array(mb_strtolower($name, 'UTF-8'), $particles, true)) {
                continue;
            }
            $initials[] = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        }

        $text = implode($separator, $initials);

        return ($text !== '' && $finalDot) ? $text.'.' : $text;
    }

    private static function nameAbnt(array $p): string
    {
        $n = self::nameParts($p);

        return mb_strtoupper($n['sobrenome'], 'UTF-8').($n['nomes'] !== '' ? ', '.$n['nomes'] : '');
    }

    private static function nameApa(array $p): string
    {
        $n = self::nameParts($p);
        $i = self::initials($n['nomes']);

        return self::surnameTitle($n['sobrenome']).($i !== '' ? ', '.$i : '');
    }

    private static function nameApaDirect(array $p): string
    {
        $n = self::nameParts($p);
        $i = self::initials($n['nomes']);

        return trim($i.' '.self::surnameTitle($n['sobrenome']));
    }

    private static function nameVancouver(array $p): string
    {
        $n = self::nameParts($p);

        return trim(self::surnameTitle($n['sobrenome']).' '.self::initials($n['nomes'], '', false));
    }

    private static function nameBibtex(array $p): string
    {
        $n = self::nameParts($p);

        return self::surnameTitle($n['sobrenome']).($n['nomes'] !== '' ? ', '.$n['nomes'] : '');
    }

    private static function joinApa(array $names): string
    {
        if (count($names) <= 1) {
            return implode('', $names);
        }
        $last = array_pop($names);

        return implode(', ', $names).', & '.$last;
    }

    private static function joinApaDirect(array $names): string
    {
        if (count($names) <= 1) {
            return implode('', $names);
        }
        if (count($names) === 2) {
            return $names[0].' & '.$names[1];
        }
        $last = array_pop($names);

        return implode(', ', $names).', & '.$last;
    }

    private static function joinVancouver(array $names): string
    {
        if (count($names) > 6) {
            return implode(', ', array_slice($names, 0, 6)).', et al';
        }

        return implode(', ', $names);
    }

    // ---------------------------------------------------------------- pedaços

    public static function fullTitle(array $work): string
    {
        $title = trim((string) ($work['title'] ?? ''));
        $sub = trim((string) ($work['subtitle'] ?? ''));

        return $sub !== '' ? $title.': '.$sub : $title;
    }

    private static function doiUrl(string $doi): string
    {
        return $doi !== '' ? 'https://doi.org/'.$doi : '';
    }

    private static function pages(array $chapter, string $dash = '-'): string
    {
        $from = (string) ($chapter['first_page'] ?? '');
        $to = (string) ($chapter['last_page'] ?? '');

        if ($from === '' && $to === '') {
            return '';
        }

        return ($from !== '' && $to !== '' && $from !== $to) ? $from.$dash.$to : ($from ?: $to);
    }

    private static function edition(array $book): int
    {
        return (int) ($book['edition_number'] ?? 0);
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }

    private static function withDot(string $s): string
    {
        return str_ends_with($s, '.') ? $s : $s.'.';
    }

    private static function placePublisherYear(array $book, string $sep): string
    {
        return trim(($book['publication_place'] ?? '').': '.($book['publisher'] ?? ''), ': ').$sep.($book['publication_year'] ?? '');
    }

    // ---------------------------------------------------------------- ABNT

    private static function abntBook(array $book, array $people): string
    {
        $names = array_map([self::class, 'nameAbnt'], $people['lista']);
        $parts = [];

        if ($names) {
            $parts[] = self::e(implode('; ', $names).($people['org'] ? ' (org.)' : '')).'.';
        }

        $parts[] = '<b>'.self::e(self::fullTitle($book)).'</b>.';

        if (self::edition($book) > 1) {
            $parts[] = self::edition($book).'. ed.';
        }

        $parts[] = self::e(self::placePublisherYear($book, ', ')).'.';

        if (! empty($book['pages'])) {
            $parts[] = (int) $book['pages'].' p.';
        }
        if (! empty($book['isbn_print'])) {
            $parts[] = 'ISBN '.self::e((string) $book['isbn_print']).'.';
        }
        if (! empty($book['doi'])) {
            $parts[] = 'DOI: '.self::e((string) $book['doi']).'.';
        }

        return implode(' ', $parts);
    }

    private static function abntChapter(array $chapter, array $book, array $authors, array $bookPeople): string
    {
        $parts = [];
        $names = array_map([self::class, 'nameAbnt'], $authors);

        if ($names) {
            $parts[] = self::e(implode('; ', $names)).'.';
        }

        $parts[] = self::e(self::fullTitle($chapter)).'.';

        $bookNames = array_map([self::class, 'nameAbnt'], $bookPeople['lista']);
        $in = 'In: ';
        if ($bookNames) {
            $in .= self::e(implode('; ', $bookNames).($bookPeople['org'] ? ' (org.)' : '')).'. ';
        }
        $in .= '<b>'.self::e(self::fullTitle($book)).'</b>.';
        $parts[] = $in;

        if (self::edition($book) > 1) {
            $parts[] = self::edition($book).'. ed.';
        }

        $parts[] = self::e(self::placePublisherYear($book, ', ')).'.';

        $pages = self::pages($chapter);
        if ($pages !== '') {
            $parts[] = 'p. '.self::e($pages).'.';
        }
        if (! empty($chapter['doi'])) {
            $parts[] = 'DOI: '.self::e((string) $chapter['doi']).'.';
        }

        return implode(' ', $parts);
    }

    // ---------------------------------------------------------------- APA 7

    private static function apaBook(array $book, array $people): string
    {
        $names = array_map([self::class, 'nameApa'], $people['lista']);
        $parts = [];

        if ($names) {
            $authors = self::joinApa($names);
            if ($people['org']) {
                $authors .= count($names) > 1 ? ' (Orgs.)' : ' (Org.)';
            }
            $parts[] = self::e(self::withDot($authors));
        }

        $parts[] = '('.self::e((string) ($book['publication_year'] ?? 's.d.')).').';

        $title = '<i>'.self::e(self::fullTitle($book)).'</i>';
        if (self::edition($book) > 1) {
            $title .= ' ('.self::edition($book).'a ed.)';
        }
        $parts[] = $title.'.';
        $parts[] = self::e((string) ($book['publisher'] ?? '')).'.';

        if (! empty($book['doi'])) {
            $parts[] = self::e(self::doiUrl((string) $book['doi']));
        }

        return implode(' ', $parts);
    }

    private static function apaChapter(array $chapter, array $book, array $authors, array $bookPeople): string
    {
        $parts = [];
        $names = array_map([self::class, 'nameApa'], $authors);

        if ($names) {
            $parts[] = self::e(self::withDot(self::joinApa($names)));
        }

        $year = $chapter['publication_date'] ?? null;
        $year = $year ? substr((string) $year, 0, 4) : (string) ($book['publication_year'] ?? 's.d.');
        $parts[] = '('.self::e($year).').';
        $parts[] = self::e(self::fullTitle($chapter)).'.';

        $bookNames = array_map([self::class, 'nameApaDirect'], $bookPeople['lista']);
        $in = 'In ';
        if ($bookNames) {
            $in .= self::e(self::joinApaDirect($bookNames));
            if ($bookPeople['org']) {
                $in .= count($bookNames) > 1 ? ' (Orgs.)' : ' (Org.)';
            }
            $in .= ', ';
        }
        $in .= '<i>'.self::e(self::fullTitle($book)).'</i>';

        $details = [];
        if (self::edition($book) > 1) {
            $details[] = self::edition($book).'a ed.';
        }
        $pages = self::pages($chapter, '–');
        if ($pages !== '') {
            $details[] = (str_contains($pages, '–') ? 'pp. ' : 'p. ').$pages;
        }
        if ($details) {
            $in .= ' ('.self::e(implode(', ', $details)).')';
        }

        $parts[] = $in.'.';
        $parts[] = self::e((string) ($book['publisher'] ?? '')).'.';

        if (! empty($chapter['doi'])) {
            $parts[] = self::e(self::doiUrl((string) $chapter['doi']));
        }

        return implode(' ', $parts);
    }

    // ---------------------------------------------------------------- Vancouver

    private static function vancouverBook(array $book, array $people): string
    {
        $names = array_map([self::class, 'nameVancouver'], $people['lista']);
        $parts = [];

        if ($names) {
            $parts[] = self::e(self::joinVancouver($names).($people['org'] ? (count($names) > 1 ? ', editores' : ', editor') : '')).'.';
        }

        $parts[] = self::e(self::fullTitle($book)).'.';

        if (self::edition($book) > 1) {
            $parts[] = self::edition($book).'a ed.';
        }

        $parts[] = self::e(self::placePublisherYear($book, '; ')).'.';

        if (! empty($book['pages'])) {
            $parts[] = (int) $book['pages'].' p.';
        }
        if (! empty($book['doi'])) {
            $parts[] = 'doi: '.self::e((string) $book['doi']);
        }

        return implode(' ', $parts);
    }

    private static function vancouverChapter(array $chapter, array $book, array $authors, array $bookPeople): string
    {
        $parts = [];
        $names = array_map([self::class, 'nameVancouver'], $authors);

        if ($names) {
            $parts[] = self::e(self::joinVancouver($names)).'.';
        }

        $parts[] = self::e(self::fullTitle($chapter)).'.';

        $bookNames = array_map([self::class, 'nameVancouver'], $bookPeople['lista']);
        $in = 'In: ';
        if ($bookNames) {
            $in .= self::e(self::joinVancouver($bookNames).($bookPeople['org'] ? (count($bookNames) > 1 ? ', editores' : ', editor') : '')).'. ';
        }
        $in .= self::e(self::fullTitle($book)).'.';
        $parts[] = $in;

        if (self::edition($book) > 1) {
            $parts[] = self::edition($book).'a ed.';
        }

        $parts[] = self::e(self::placePublisherYear($book, '; ')).'.';

        $pages = self::pages($chapter);
        if ($pages !== '') {
            $parts[] = 'p. '.self::e($pages).'.';
        }
        if (! empty($chapter['doi'])) {
            $parts[] = 'doi: '.self::e((string) $chapter['doi']);
        }

        return implode(' ', $parts);
    }

    // ---------------------------------------------------------------- BibTeX

    private static function bibtexKey(string $doi, string $fallback): string
    {
        $suffix = $doi !== '' && str_contains($doi, '/') ? substr($doi, strpos($doi, '/') + 1) : $fallback;
        $key = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($suffix)));

        return $key !== '' ? $key : 'conexao';
    }

    private static function bibtexValue(string $value): string
    {
        return str_replace(['\\', '{', '}'], ['\\textbackslash{}', '\\{', '\\}'], $value);
    }

    private static function bibtexEntry(string $type, string $key, array $fields): string
    {
        $lines = [];

        foreach ($fields as $name => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $lines[] = sprintf('  %s = {%s},', $name, self::bibtexValue($value));
        }

        return self::e('@'.$type.'{'.$key.",\n".implode("\n", $lines)."\n}");
    }

    private static function bibtexBook(array $book, array $people): string
    {
        $names = implode(' and ', array_map([self::class, 'nameBibtex'], $people['lista']));
        $doi = (string) ($book['doi'] ?? '');

        return self::bibtexEntry('book', self::bibtexKey($doi, (string) ($book['slug'] ?? '')), [
            'title' => self::fullTitle($book),
            $people['org'] ? 'editor' : 'author' => $names,
            'publisher' => $book['publisher'] ?? '',
            'address' => $book['publication_place'] ?? '',
            'year' => $book['publication_year'] ?? '',
            'edition' => self::edition($book) > 1 ? self::edition($book) : '',
            'isbn' => ($book['isbn_print'] ?? '') ?: ($book['isbn_ebook'] ?? ''),
            'doi' => $doi,
            'url' => self::doiUrl($doi),
            'language' => $book['language'] ?? '',
            'keywords' => implode(', ', (array) ($book['keywords'] ?? [])),
        ]);
    }

    private static function bibtexChapter(array $chapter, array $book, array $authors, array $bookPeople): string
    {
        $doi = (string) ($chapter['doi'] ?? '');
        $date = $chapter['publication_date'] ?? null;

        return self::bibtexEntry('incollection', self::bibtexKey($doi, (string) ($chapter['slug'] ?? '')), [
            'title' => self::fullTitle($chapter),
            'author' => implode(' and ', array_map([self::class, 'nameBibtex'], $authors)),
            'booktitle' => self::fullTitle($book),
            $bookPeople['org'] ? 'editor' : 'bookauthor' => implode(' and ', array_map([self::class, 'nameBibtex'], $bookPeople['lista'])),
            'publisher' => $book['publisher'] ?? '',
            'address' => $book['publication_place'] ?? '',
            'year' => $date ? substr((string) $date, 0, 4) : ($book['publication_year'] ?? ''),
            'chapter' => $chapter['number'] ?? '',
            'pages' => self::pages($chapter, '--'),
            'isbn' => ($book['isbn_print'] ?? '') ?: ($book['isbn_ebook'] ?? ''),
            'doi' => $doi,
            'url' => self::doiUrl($doi),
            'language' => $chapter['language'] ?? ($book['language'] ?? ''),
            'keywords' => implode(', ', (array) ($chapter['keywords'] ?? [])),
        ]);
    }

    // ---------------------------------------------------------------- RIS

    private static function risEntry(array $lines): string
    {
        $out = [];

        foreach ($lines as [$tag, $value]) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $out[] = $tag.'  - '.str_replace(["\r", "\n"], ' ', $value);
        }

        $out[] = 'ER  - ';

        return self::e(implode("\n", $out));
    }

    private static function risBook(array $book, array $people): string
    {
        $lines = [['TY', 'BOOK']];

        foreach ($people['lista'] as $p) {
            $lines[] = [$people['org'] ? 'ED' : 'AU', self::nameBibtex($p)];
        }

        $lines[] = ['TI', self::fullTitle($book)];
        $lines[] = ['PY', $book['publication_year'] ?? ''];
        $lines[] = ['DA', str_replace('-', '/', (string) ($book['publication_date'] ?? ''))];
        $lines[] = ['PB', $book['publisher'] ?? ''];
        $lines[] = ['CY', $book['publication_place'] ?? ''];
        $lines[] = ['ET', self::edition($book) > 1 ? self::edition($book) : ''];
        $lines[] = ['SN', $book['isbn_print'] ?? ''];
        $lines[] = ['SN', $book['isbn_ebook'] ?? ''];
        $lines[] = ['DO', $book['doi'] ?? ''];
        $lines[] = ['UR', self::doiUrl((string) ($book['doi'] ?? ''))];
        $lines[] = ['LA', $book['language'] ?? ''];

        foreach ((array) ($book['keywords'] ?? []) as $kw) {
            $lines[] = ['KW', $kw];
        }

        $lines[] = ['AB', $book['abstract'] ?? ''];

        return self::risEntry($lines);
    }

    private static function risChapter(array $chapter, array $book, array $authors, array $bookPeople): string
    {
        $lines = [['TY', 'CHAP']];

        foreach ($authors as $p) {
            $lines[] = ['AU', self::nameBibtex($p)];
        }
        foreach ($bookPeople['lista'] as $p) {
            $lines[] = [$bookPeople['org'] ? 'ED' : 'A2', self::nameBibtex($p)];
        }

        $date = $chapter['publication_date'] ?? null;

        $lines[] = ['TI', self::fullTitle($chapter)];
        $lines[] = ['T2', self::fullTitle($book)];
        $lines[] = ['PY', $date ? substr((string) $date, 0, 4) : ($book['publication_year'] ?? '')];
        $lines[] = ['DA', str_replace('-', '/', (string) ($date ?? ''))];
        $lines[] = ['PB', $book['publisher'] ?? ''];
        $lines[] = ['CY', $book['publication_place'] ?? ''];
        $lines[] = ['SP', $chapter['first_page'] ?? ''];
        $lines[] = ['EP', $chapter['last_page'] ?? ''];
        $lines[] = ['SN', $book['isbn_print'] ?? ''];
        $lines[] = ['DO', $chapter['doi'] ?? ''];
        $lines[] = ['UR', self::doiUrl((string) ($chapter['doi'] ?? ''))];
        $lines[] = ['LA', $chapter['language'] ?? ($book['language'] ?? '')];

        foreach ((array) ($chapter['keywords'] ?? []) as $kw) {
            $lines[] = ['KW', $kw];
        }

        $lines[] = ['AB', $chapter['abstract'] ?? ''];

        return self::risEntry($lines);
    }
}
