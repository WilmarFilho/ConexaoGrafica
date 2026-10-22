<?php

namespace App\Crossref;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

/**
 * Gera o `doi_batch` 5.4.0 da Crossref a partir do payload do contrato
 * (conexao-editora/docs/conexao-doi-contrato.md, seções 4 e 6; exemplo em
 * docs/exemplos/publicacao-tratado.json). Não depende de models: recebe o array
 * `['book' => [...], 'chapters' => [...]]` e devolve a string XML em UTF-8.
 *
 * Decisões onde o contrato é omisso:
 * - given_name/surname: o sobrenome é a parte de `sort_name` antes da vírgula,
 *   localizada dentro de `name` para manter a grafia original ("SILVA, Maria
 *   Exemplo da" + "Maria Exemplo da Silva" → surname "Silva", given "Maria
 *   Exemplo da"). Sem `sort_name`, o sobrenome é a última palavra do nome.
 * - `sequence="first"` vai para o contribuidor de menor `sequence`; os demais
 *   são `additional`. Papel `other` não existe na Crossref e é omitido.
 * - Datas: `publication_date` (ISO) vira mês/dia/ano; sem ela, só o ano. O
 *   capítulo herda a data do livro. Uma `publication_date` por mídia do `format`
 *   (print → print, ebook → online, both → as duas).
 * - `landing_url` ausente: monta `{landing_base}/livro/{slug}/` e
 *   `.../capitulo/{NN}/` (padrão da seção 5 do contrato). As URLs reais vêm do WP.
 * - Resumos vão em `jats:abstract` com `xml:lang`; parágrafos separados por linha em branco.
 * - Referências: `doi` quando informado (e `unstructured_citation` junto, se houver texto).
 */
class XmlBuilder
{
    public const NS = 'http://www.crossref.org/schema/5.4.0';

    public const NS_JATS = 'http://www.ncbi.nlm.nih.gov/JATS1';

    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public const NS_XML = 'http://www.w3.org/XML/1998/namespace';

    public const SCHEMA_LOCATION = 'http://www.crossref.org/schema/5.4.0 https://www.crossref.org/schemas/crossref5.4.0.xsd';

    private const ROLES = ['author' => 'author', 'editor' => 'editor', 'translator' => 'translator', 'reviewer' => 'reviewer'];

    private DOMDocument $doc;

    public function __construct(
        private readonly string $depositorName,
        private readonly string $depositorEmail,
        private readonly string $registrant,
        private readonly string $landingBase,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) (config('hub.crossref.depositor_name') ?: 'Conexão Editora'),
            (string) config('hub.crossref.depositor_email'),
            (string) (config('hub.crossref.registrant') ?: 'Conexão Editora'),
            rtrim((string) (config('hub.crossref.landing_base') ?: 'https://conexaoeditora.com.br'), '/'),
        );
    }

    /** Identificador único do lote (doi_batch_id, máx. 100 caracteres). */
    public static function newBatchId(int $bookId): string
    {
        return sprintf('conexao-%d-%s-%s', $bookId, now()->format('Ymd-His'), Str::lower(Str::random(6)));
    }

    /**
     * Lista os DOIs que o payload registra, na ordem do XML.
     *
     * @return list<array{target_type: string, target_id: int, doi: string}>
     */
    public function dois(array $payload): array
    {
        $book = $payload['book'] ?? [];
        $out = [['target_type' => 'book', 'target_id' => (int) ($book['hub_id'] ?? 0), 'doi' => (string) ($book['doi'] ?? '')]];

        foreach ($payload['chapters'] ?? [] as $chapter) {
            $out[] = ['target_type' => 'chapter', 'target_id' => (int) ($chapter['hub_id'] ?? 0), 'doi' => (string) ($chapter['doi'] ?? '')];
        }

        return $out;
    }

    /**
     * @throws CrossrefException quando faltam dados obrigatórios (antes de gerar o XML)
     */
    public function build(array $payload, string $batchId, ?string $timestamp = null): string
    {
        $book = $payload['book'] ?? [];
        $chapters = array_values($payload['chapters'] ?? []);

        $this->assertComplete($book, $chapters);

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;

        $root = $this->doc->createElementNS(self::NS, 'doi_batch');
        $root->setAttribute('version', '5.4.0');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:jats', self::NS_JATS);
        $root->setAttributeNS(self::NS_XSI, 'xsi:schemaLocation', self::SCHEMA_LOCATION);
        $this->doc->appendChild($root);

        $root->appendChild($this->head($batchId, $timestamp ?? now()->format('YmdHisv')));

        $body = $this->el('body');
        $root->appendChild($body);

        $bookEl = $this->el('book');
        $bookEl->setAttribute('book_type', $this->bookType($book));
        $body->appendChild($bookEl);

        // Os elementos são anexados à árvore antes de receber filhos: assim o DOM
        // reaproveita o xmlns:jats da raiz em vez de redeclará-lo em cada resumo.
        $meta = $this->el($this->isSeries($book) ? 'book_series_metadata' : 'book_metadata');
        $bookEl->appendChild($meta);
        $this->isSeries($book) ? $this->fillBookSeriesMetadata($meta, $book) : $this->fillBookMetadata($meta, $book);

        foreach ($chapters as $chapter) {
            $item = $this->el('content_item');
            $bookEl->appendChild($item);
            $this->fillContentItem($item, $chapter, $book);
        }

        return $this->doc->saveXML();
    }

    // ---------------------------------------------------------------- blocos

    private function head(string $batchId, string $timestamp): DOMElement
    {
        $head = $this->el('head');
        $head->appendChild($this->text('doi_batch_id', $batchId));
        $head->appendChild($this->text('timestamp', preg_replace('/\D/', '', $timestamp) ?: now()->format('YmdHis')));

        $depositor = $this->el('depositor');
        $depositor->appendChild($this->text('depositor_name', $this->depositorName));
        $depositor->appendChild($this->text('email_address', $this->depositorEmail));
        $head->appendChild($depositor);

        $head->appendChild($this->text('registrant', $this->registrant));

        return $head;
    }

    private function fillBookMetadata(DOMElement $meta, array $book): void
    {
        $this->setLanguage($meta, $book['language'] ?? null);

        $this->appendIf($meta, $this->contributors($book['contributors'] ?? []));
        $meta->appendChild($this->titles($book['title'], $book['subtitle'] ?? null));
        $this->appendAbstracts($meta, $book);
        $this->appendIf($meta, $this->optionalText('edition_number', $book['edition_number'] ?? null, 15));
        $this->appendDates($meta, $this->bookDate($book), $this->mediaTypes($book));
        $this->appendIsbns($meta, $book);
        $meta->appendChild($this->publisher($book));
        $meta->appendChild($this->doiData($book['doi'], $this->bookLanding($book)));
        $this->appendIf($meta, $this->citationList($book['references'] ?? [], 'b'.($book['hub_id'] ?? 0)));
    }

    private function fillBookSeriesMetadata(DOMElement $meta, array $book): void
    {
        $this->setLanguage($meta, $book['language'] ?? null);

        $series = $this->el('series_metadata');
        $series->appendChild($this->titles($book['series']['title'] ?? $book['series_title'] ?? null, null));
        $issn = $this->text('issn', $this->clean($book['series']['issn'] ?? $book['series_issn'] ?? ''));
        $issn->setAttribute('media_type', ($book['format'] ?? 'print') === 'ebook' ? 'electronic' : 'print');
        $series->appendChild($issn);
        $meta->appendChild($series);

        $this->appendIf($meta, $this->contributors($book['contributors'] ?? []));
        $meta->appendChild($this->titles($book['title'], $book['subtitle'] ?? null));
        $this->appendAbstracts($meta, $book);
        $this->appendIf($meta, $this->optionalText('volume', $book['volume'] ?? null, 32));
        $this->appendIf($meta, $this->optionalText('edition_number', $book['edition_number'] ?? null, 15));
        $this->appendDates($meta, $this->bookDate($book), $this->mediaTypes($book));
        $this->appendIsbns($meta, $book);
        $meta->appendChild($this->publisher($book));
        $meta->appendChild($this->doiData($book['doi'], $this->bookLanding($book)));
        $this->appendIf($meta, $this->citationList($book['references'] ?? [], 'b'.($book['hub_id'] ?? 0)));
    }

    private function fillContentItem(DOMElement $item, array $chapter, array $book): void
    {
        $item->setAttribute('component_type', 'chapter');
        $this->setLanguage($item, $chapter['language'] ?? $book['language'] ?? null);

        $this->appendIf($item, $this->contributors($chapter['contributors'] ?? []));
        $item->appendChild($this->titles($chapter['title'], $chapter['subtitle'] ?? null));
        $this->appendAbstracts($item, $chapter, $chapter['language'] ?? $book['language'] ?? null);

        $number = $this->clean($chapter['number'] ?? '') ?: (isset($chapter['sequence']) ? (string) $chapter['sequence'] : '');
        $this->appendIf($item, $this->optionalText('component_number', $number, 50));

        $date = $this->parseDate($chapter['publication_date'] ?? null) ?? $this->bookDate($book);
        $this->appendDates($item, $date, $this->mediaTypes($book));

        $first = $this->clean($chapter['first_page'] ?? '');
        if ($first !== '') {
            $pages = $this->el('pages');
            $pages->appendChild($this->text('first_page', $first));
            $this->appendIf($pages, $this->optionalText('last_page', $chapter['last_page'] ?? null, 32));
            $item->appendChild($pages);
        }

        $item->appendChild($this->doiData($chapter['doi'], $this->chapterLanding($chapter, $book)));
        $this->appendIf($item, $this->citationList($chapter['references'] ?? [], 'c'.($chapter['hub_id'] ?? 0)));
    }

    /** @return DOMElement|null null quando não sobra nenhum contribuidor com papel aceito */
    private function contributors(array $list): ?DOMElement
    {
        $list = array_values(array_filter($list, fn ($c) => isset(self::ROLES[$c['role'] ?? ''])));
        usort($list, fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));

        if ($list === []) {
            return null;
        }

        $el = $this->el('contributors');

        foreach ($list as $i => $c) {
            [$given, $surname] = self::splitName((string) ($c['name'] ?? ''), $c['sort_name'] ?? null);

            $person = $this->el('person_name');
            $person->setAttribute('sequence', $i === 0 ? 'first' : 'additional');
            $person->setAttribute('contributor_role', self::ROLES[$c['role']]);

            if ($given !== '') {
                $person->appendChild($this->text('given_name', mb_substr($given, 0, 60)));
            }
            $person->appendChild($this->text('surname', mb_substr($surname, 0, 60)));

            $aff = $c['affiliation'] ?? null;
            $affName = $this->clean($aff['name'] ?? ($c['affiliation_text'] ?? ''));
            if ($affName !== '') {
                $affiliations = $this->el('affiliations');
                $institution = $this->el('institution');
                $institution->appendChild($this->text('institution_name', mb_substr($affName, 0, 1024)));

                $ror = $this->clean($aff['ror_id'] ?? $aff['ror'] ?? '');
                if ($ror !== '') {
                    $id = $this->text('institution_id', str_starts_with($ror, 'http') ? $ror : 'https://ror.org/'.$ror);
                    $id->setAttribute('type', 'ror');
                    $institution->appendChild($id);
                }

                $place = implode(', ', array_filter(array_map(fn ($k) => $this->clean($aff[$k] ?? ''), ['city', 'state', 'country'])));
                if (mb_strlen($place) >= 2) {
                    $institution->appendChild($this->text('institution_place', mb_substr($place, 0, 255)));
                }

                $affiliations->appendChild($institution);
                $person->appendChild($affiliations);
            }

            $orcid = preg_replace('/[^0-9X]/i', '', (string) ($c['orcid'] ?? ''));
            if ($orcid !== '' && preg_match('/^\d{15}[\dX]$/i', $orcid)) {
                $o = $this->text('ORCID', 'https://orcid.org/'.implode('-', str_split(strtoupper($orcid), 4)));
                $o->setAttribute('authenticated', 'false');
                $person->appendChild($o);
            }

            $el->appendChild($person);
        }

        return $el;
    }

    private function titles(?string $title, ?string $subtitle): DOMElement
    {
        $titles = $this->el('titles');
        $titles->appendChild($this->text('title', $this->clean($title)));
        $this->appendIf($titles, $this->optionalText('subtitle', $subtitle));

        return $titles;
    }

    private function appendAbstracts(DOMElement $parent, array $source, ?string $lang = null): void
    {
        $lang = $lang ?? $source['language'] ?? 'pt';
        $this->appendAbstract($parent, $source['abstract'] ?? null, $lang);
        $this->appendAbstract($parent, $source['abstract_en'] ?? null, 'en');
    }

    /** Anexa primeiro, preenche depois: o DOM reaproveita o xmlns:jats da raiz. */
    private function appendAbstract(DOMElement $parent, ?string $text, ?string $lang): void
    {
        $text = trim(strip_tags((string) $text));
        if ($text === '') {
            return;
        }

        $abstract = $this->doc->createElementNS(self::NS_JATS, 'jats:abstract');
        $parent->appendChild($abstract);
        if ($lang && preg_match('/^[a-z]{2,3}$/i', $lang)) {
            $abstract->setAttributeNS(self::NS_XML, 'xml:lang', strtolower($lang));
        }

        foreach (preg_split('/\n\s*\n/', $text) ?: [$text] as $paragraph) {
            $paragraph = $this->clean($paragraph);
            if ($paragraph !== '') {
                $p = $this->doc->createElementNS(self::NS_JATS, 'jats:p');
                $abstract->appendChild($p);
                $p->appendChild($this->doc->createTextNode($paragraph));
            }
        }
    }

    /** @param array{year: int, month: ?int, day: ?int} $date */
    private function appendDates(DOMElement $parent, array $date, array $mediaTypes): void
    {
        foreach ($mediaTypes as $media) {
            $el = $this->el('publication_date');
            $el->setAttribute('media_type', $media);
            if ($date['month']) {
                $el->appendChild($this->text('month', sprintf('%02d', $date['month'])));
            }
            if ($date['day']) {
                $el->appendChild($this->text('day', sprintf('%02d', $date['day'])));
            }
            $el->appendChild($this->text('year', (string) $date['year']));
            $parent->appendChild($el);
        }
    }

    private function appendIsbns(DOMElement $parent, array $book): void
    {
        foreach ($this->isbns($book) as [$media, $value]) {
            $isbn = $this->text('isbn', $value);
            $isbn->setAttribute('media_type', $media);
            $parent->appendChild($isbn);
        }
    }

    /** @return list<array{0: string, 1: string}> [media_type, isbn] */
    private function isbns(array $book): array
    {
        $out = [];
        if ($v = $this->isbn($book['isbn_print'] ?? null)) {
            $out[] = ['print', $v];
        }
        if ($v = $this->isbn($book['isbn_ebook'] ?? null)) {
            $out[] = ['electronic', $v];
        }
        foreach ((array) ($book['other_isbns'] ?? []) as $other) {
            if ($v = $this->isbn($other['value'] ?? null)) {
                $out[] = [($other['type'] ?? '') === 'print' ? 'print' : 'electronic', $v];
            }
        }

        return $out;
    }

    private function isbn(?string $raw): ?string
    {
        $v = trim((string) $raw);

        return $v !== '' && preg_match('/^(97[89]-)?\d[\d \-]+[\dX]$/', $v) && strlen($v) <= 17 ? $v : null;
    }

    private function publisher(array $book): DOMElement
    {
        $publisher = $this->el('publisher');
        $publisher->appendChild($this->text('publisher_name', mb_substr($this->clean($book['publisher'] ?? '') ?: 'Conexão Editora', 0, 255)));
        $place = $this->clean($book['publication_place'] ?? '');
        if (mb_strlen($place) >= 2) {
            $publisher->appendChild($this->text('publisher_place', mb_substr($place, 0, 255)));
        }

        return $publisher;
    }

    private function doiData(string $doi, string $resource): DOMElement
    {
        $el = $this->el('doi_data');
        $el->appendChild($this->text('doi', trim($doi)));
        $el->appendChild($this->text('resource', $resource));

        return $el;
    }

    private function citationList(array $references, string $keyPrefix): ?DOMElement
    {
        $references = array_values(array_filter($references, fn ($r) => is_array($r) && (trim((string) ($r['text'] ?? '')) !== '' || trim((string) ($r['doi'] ?? '')) !== '')));
        if ($references === []) {
            return null;
        }

        $list = $this->el('citation_list');
        foreach ($references as $i => $ref) {
            $citation = $this->el('citation');
            $citation->setAttribute('key', $keyPrefix.'-ref'.($i + 1));
            $doi = trim((string) ($ref['doi'] ?? ''));
            if ($doi !== '') {
                $citation->appendChild($this->text('doi', preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi)));
            }
            $text = $this->clean($ref['text'] ?? '');
            if ($text !== '') {
                $citation->appendChild($this->text('unstructured_citation', $text));
            }
            $list->appendChild($citation);
        }

        return $list;
    }

    // ---------------------------------------------------------------- regras

    /** @throws CrossrefException */
    private function assertComplete(array $book, array $chapters): void
    {
        $errors = [];

        if ($this->clean($book['title'] ?? '') === '') {
            $errors[] = 'O livro precisa de título.';
        }
        if (! $this->validDoi($book['doi'] ?? null)) {
            $errors[] = 'O livro precisa de DOI no formato 10.XXXX/sufixo.';
        }
        if ($this->clean($book['landing_url'] ?? '') === '' && $this->clean($book['slug'] ?? '') === '') {
            $errors[] = 'O livro precisa de landing_url (URL pública) ou de slug.';
        }
        if (empty($book['publication_year']) && ! $this->parseDate($book['publication_date'] ?? null)) {
            $errors[] = 'O livro precisa de ano ou data de publicação.';
        }
        if ($this->isbns($book) === []) {
            $errors[] = 'O livro precisa de ao menos um ISBN válido (impresso ou e-book).';
        }
        if ($this->isSeries($book) && $this->clean($book['series']['title'] ?? $book['series_title'] ?? '') === '') {
            $errors[] = 'Livro de série precisa do título da série.';
        }
        if ($this->depositorEmail === '') {
            $errors[] = 'Configure CROSSREF_DEPOSITOR_EMAIL no .env (e-mail do depositante).';
        }

        foreach ($book['contributors'] ?? [] as $i => $c) {
            if ($this->clean($c['name'] ?? '') === '') {
                $errors[] = sprintf('Contribuidor %d do livro sem nome.', $i + 1);
            }
        }

        foreach ($chapters as $i => $chapter) {
            $label = sprintf('Capítulo %s', $chapter['number'] ?? $chapter['sequence'] ?? ($i + 1));
            if ($this->clean($chapter['title'] ?? '') === '') {
                $errors[] = "{$label} sem título.";
            }
            if (! $this->validDoi($chapter['doi'] ?? null)) {
                $errors[] = "{$label} sem DOI válido.";
            }
            if ($this->clean($chapter['landing_url'] ?? '') === '' && empty($chapter['sequence']) && $this->clean($book['slug'] ?? '') === '') {
                $errors[] = "{$label} sem landing_url nem sequência para montar a URL.";
            }
            foreach ($chapter['contributors'] ?? [] as $j => $c) {
                if ($this->clean($c['name'] ?? '') === '') {
                    $errors[] = sprintf('%s: contribuidor %d sem nome.', $label, $j + 1);
                }
            }
        }

        if ($errors !== []) {
            throw new CrossrefException('Dados incompletos para a Crossref: '.implode(' ', $errors), $errors);
        }
    }

    private function validDoi(?string $doi): bool
    {
        return (bool) preg_match('#^10\.\d{4,9}/\S{1,200}$#', trim((string) $doi));
    }

    private function isSeries(array $book): bool
    {
        return $this->clean($book['series']['issn'] ?? $book['series_issn'] ?? '') !== '';
    }

    private function bookType(array $book): string
    {
        $type = (string) ($book['type'] ?? 'edited_book');

        return in_array($type, ['edited_book', 'monograph', 'reference', 'other'], true) ? $type : 'other';
    }

    /** @return list<string> */
    private function mediaTypes(array $book): array
    {
        return match ($book['format'] ?? 'print') {
            'ebook' => ['online'],
            'both' => ['print', 'online'],
            default => ['print'],
        };
    }

    /** @return array{year: int, month: ?int, day: ?int} */
    private function bookDate(array $book): array
    {
        return $this->parseDate($book['publication_date'] ?? null)
            ?? ['year' => (int) ($book['publication_year'] ?? 0), 'month' => null, 'day' => null];
    }

    /** @return array{year: int, month: ?int, day: ?int}|null */
    private function parseDate(?string $iso): ?array
    {
        $iso = trim((string) $iso);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2], 'day' => (int) $m[3]];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $iso, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2], 'day' => null];
        }
        if (preg_match('/^\d{4}$/', $iso)) {
            return ['year' => (int) $iso, 'month' => null, 'day' => null];
        }

        return null;
    }

    private function bookLanding(array $book): string
    {
        $url = $this->clean($book['landing_url'] ?? '');

        return $url !== '' ? $url : sprintf('%s/livro/%s/', $this->landingBase, rawurlencode($this->clean($book['slug'])));
    }

    private function chapterLanding(array $chapter, array $book): string
    {
        $url = $this->clean($chapter['landing_url'] ?? '');
        if ($url !== '') {
            return $url;
        }

        $seq = (int) ($chapter['sequence'] ?? 0);

        return sprintf('%s/livro/%s/capitulo/%s/', $this->landingBase, rawurlencode($this->clean($book['slug'] ?? '')), str_pad((string) $seq, 2, '0', STR_PAD_LEFT));
    }

    /**
     * Separa nome e sobrenome. Devolve [given_name, surname]; given_name pode ser ''.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitName(string $name, ?string $sortName): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $words = $name === '' ? [] : explode(' ', $name);

        if ($sortName && str_contains($sortName, ',')) {
            [$surnamePart, $givenPart] = array_map('trim', explode(',', $sortName, 2));
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $surnamePart) ?? '');

            // Procura o sobrenome do sort_name no fim do nome, para manter a grafia original.
            for ($k = count($words) - 1; $k >= 1; $k--) {
                $tail = array_slice($words, $k);
                if (mb_strtolower(implode(' ', $tail)) === $key) {
                    return [implode(' ', array_slice($words, 0, $k)), implode(' ', $tail)];
                }
            }

            $surname = mb_strtoupper($surnamePart) === $surnamePart ? mb_convert_case($surnamePart, MB_CASE_TITLE, 'UTF-8') : $surnamePart;
            $given = $givenPart !== '' ? $givenPart : implode(' ', array_slice($words, 0, -1));

            return [trim($given), trim($surname) !== '' ? trim($surname) : (end($words) ?: $name)];
        }

        if (count($words) <= 1) {
            return ['', $name];
        }

        return [implode(' ', array_slice($words, 0, -1)), end($words)];
    }

    // ---------------------------------------------------------------- utilitários DOM

    private function el(string $name): DOMElement
    {
        return $this->doc->createElementNS(self::NS, $name);
    }

    private function text(string $name, string $value): DOMElement
    {
        $el = $this->el($name);
        $el->appendChild($this->doc->createTextNode($value));

        return $el;
    }

    private function optionalText(string $name, mixed $value, int $max = 255): ?DOMElement
    {
        $value = $this->clean($value === null ? '' : (string) $value);

        return $value === '' ? null : $this->text($name, mb_substr($value, 0, $max));
    }

    private function appendIf(DOMElement $parent, ?DOMElement $child): void
    {
        if ($child) {
            $parent->appendChild($child);
        }
    }

    private function setLanguage(DOMElement $el, ?string $lang): void
    {
        $lang = strtolower(trim((string) $lang));
        if (preg_match('/^[a-z]{2,3}$/', $lang)) {
            $el->setAttribute('language', $lang);
        }
    }

    /** Texto de uma linha: sem HTML, sem caracteres de controle, espaços colapsados. */
    private function clean(mixed $value): string
    {
        $s = strip_tags((string) ($value ?? ''));
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}
