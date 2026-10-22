<?php

namespace App\Editorial\Import;

use App\Editorial\Import\Ai\AiException;
use App\Editorial\Import\Ai\AiGateway;
use App\Editorial\Import\Ai\UsageMeter;
use Illuminate\Support\Str;

/**
 * Lê um livro em PDF com a API da OpenAI e monta a proposta de cadastro
 * (livro + capítulos + pessoas) no formato documentado em docs/importacao-ia.md.
 *
 * Três momentos, cada um com saída estruturada (JSON Schema):
 *  1. frontMatter(): as primeiras N páginas vão como documento PDF (layout importa)
 *     → dados da obra, pessoas com instituição, sumário com páginas impressas.
 *  2. segment(): determinístico, cruza o sumário com os cabeçalhos do texto extraído
 *     → intervalo de páginas do PDF e páginas impressas de cada capítulo.
 *  3. chapter(): o texto do capítulo, com marcadores de página, → título confirmado,
 *     autores ligados aos da obra, resumo (original preservado ou sugerido), palavras-chave,
 *     referências; tudo com evidência {pdf_page, excerpt} e confiança 0–1.
 *     Capítulo longo é dividido em partes e consolidado numa chamada final.
 */
class Analyzer
{
    /** Sobe quando qualquer prompt ou schema muda; fica gravado em imports.prompt_version. */
    public const PROMPT_VERSION = '2026-10-22.1';

    public const FORMAT = 'conexao-doi/import-proposal@1';

    /** Caracteres por token, estimativa conservadora para português. */
    private const CHARS_PER_TOKEN = 3.2;

    public readonly UsageMeter $usage;

    public function __construct(
        private readonly AiGateway $ai,
        private readonly PdfSlicer $slicer = new PdfSlicer,
        private readonly Segmenter $segmenter = new Segmenter,
    ) {
        $this->usage = new UsageMeter((string) config('hub.ai.model'));
    }

    // ---------------------------------------------------------------- 1. obra

    /**
     * @param  list<array{pdf_page:int, printed_page:?int, text:string}>  $pages
     * @return array{data:array, mode:string, pages:int}
     */
    public function frontMatter(string $pdfPath, array $pages): array
    {
        $n = max(1, (int) config('hub.ai.front_matter_pages', 30));
        $slice = $this->slicer->firstPages($pdfPath, $n, count($pages));
        $sent = min($n, count($pages));

        try {
            if ($slice['path'] !== null) {
                $content = [
                    ['type' => 'input_file', 'filename' => 'paginas-iniciais.pdf',
                        'file_data' => 'data:application/pdf;base64,'.base64_encode(file_get_contents($slice['path']))],
                    ['type' => 'input_text', 'text' => "As {$sent} primeiras páginas do PDF estão no arquivo acima (página 1 do arquivo = página 1 do PDF). Extraia os dados da obra, as pessoas e o sumário conforme o schema."],
                ];
            } else {
                $content = [
                    ['type' => 'input_text', 'text' => "Texto extraído das {$sent} primeiras páginas do PDF (o layout se perdeu; cada página começa com [[p. X | PDF Y]]):\n\n".Segmenter::text($pages, 1, $sent)],
                ];
            }

            $result = $this->ai->structured(Prompts::frontMatter(), $content, 'front_matter', Schemas::frontMatter(), $this->maxOutput());
            $this->usage->add('front_matter', $result);
        } finally {
            if ($slice['mode'] === PdfSlicer::MODE_PDF && $slice['path'] !== null) {
                @unlink($slice['path']);
            }
        }

        return ['data' => $result->data, 'mode' => $slice['mode'], 'pages' => $sent];
    }

    // ------------------------------------------------------- 2. segmentação

    /**
     * Sumário da IA → capítulos com intervalo de páginas.
     *
     * @return array{offset:?int, anchors:int, chapters:list<array<string,mixed>>}
     */
    public function segment(array $pages, array $frontMatter): array
    {
        $toc = [];
        $seq = 0;
        foreach ((array) ($frontMatter['toc'] ?? []) as $section) {
            foreach ((array) ($section['chapters'] ?? []) as $ch) {
                if (trim((string) ($ch['title'] ?? '')) === '') {
                    continue;
                }
                $toc[] = [
                    'sequence' => ++$seq,
                    'number' => isset($ch['number']) ? (string) $ch['number'] : null,
                    'title' => trim($ch['title']),
                    'section' => $section['section'] ?? null,
                    'printed_page' => isset($ch['printed_page']) ? (int) $ch['printed_page'] : null,
                    'toc_authors' => array_values(array_filter((array) ($ch['authors'] ?? []))),
                ];
            }
        }

        $result = $this->segmenter->segment($pages, $toc, $frontMatter['end_of_chapters_printed_page'] ?? null);
        foreach ($result['chapters'] as $i => $c) {
            $result['chapters'][$i]['toc_authors'] = $toc[$i]['toc_authors'];
        }

        return $result;
    }

    // ---------------------------------------------------------- 3. capítulo

    /**
     * Analisa um capítulo já segmentado. Nunca lança por recusa/JSON inválido:
     * devolve o capítulo com status 'failed' e needs_review para o revisor decidir.
     *
     * @param  list<array{pdf_page:int, printed_page:?int, text:string}>  $pages
     * @param  array<string,mixed>  $chapter  item de segment()['chapters']
     * @param  list<array<string,mixed>>  $bookContributors  pessoas da obra (saída de frontMatter)
     */
    public function chapter(array $pages, array $chapter, array $bookContributors, string $bookTitle): array
    {
        $seq = (int) $chapter['sequence'];
        $base = $this->chapterSkeleton($chapter);

        if ($chapter['pdf_first_page'] === null) {
            $base['status'] = 'failed';
            $base['error'] = 'Capítulo não localizado no texto do PDF.';
            $base['needs_review'] = array_values(array_unique(array_merge($base['needs_review'], ['pages', 'title', 'authors', 'abstract', 'keywords'])));

            return $base;
        }

        $people = array_map(fn ($c) => $c['name'].($c['affiliation_text'] ? ' — '.$c['affiliation_text'] : ''), $bookContributors);
        $parts = $this->split($pages, (int) $chapter['pdf_first_page'], (int) $chapter['pdf_last_page']);

        try {
            if (count($parts) === 1) {
                $result = $this->ai->structured(Prompts::chapter($people, $bookTitle), [['type' => 'input_text', 'text' => $parts[0]]], 'chapter', Schemas::chapter(), $this->maxOutput());
                $this->usage->add('chapter', $result, $seq);
                $data = $result->data;
            } else {
                $partials = [];
                foreach ($parts as $i => $text) {
                    $r = $this->ai->structured(Prompts::chapterPart($i + 1, count($parts), $bookTitle), [['type' => 'input_text', 'text' => $text]], 'chapter_part', Schemas::chapterPart(), $this->maxOutput());
                    $this->usage->add('chapter_part', $r, $seq);
                    $partials[] = $r->data;
                }
                $json = json_encode($partials, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $result = $this->ai->structured(Prompts::chapterConsolidate($people, $bookTitle), [['type' => 'input_text', 'text' => $json]], 'chapter', Schemas::chapter(), $this->maxOutput());
                $this->usage->add('chapter_consolidate', $result, $seq);
                $data = $result->data;
            }
        } catch (AiException $e) {
            $this->usage->add('chapter', $e->usage, $seq, $e->reason);
            $base['status'] = 'failed';
            $base['error'] = $e->getMessage();
            $base['needs_review'] = array_values(array_unique(array_merge($base['needs_review'], ['title', 'authors', 'abstract', 'keywords'])));
            $base['usage'] = $this->usage->forChapter($seq);

            return $base;
        }

        return $this->mergeChapter($base, $data, $bookContributors);
    }

    // ------------------------------------------------------------ proposta

    /**
     * Monta o `result` final. Pode ser chamado com capítulos ainda sem análise
     * (progresso parcial): eles ficam com status 'pending'.
     */
    public function buildResult(array $import, array $frontMatter, array $segmentation, array $chapters, array $warnings = []): array
    {
        $fm = $frontMatter['data'];
        $contributors = $this->bookContributors($fm);
        $evidence = $this->evidenceMap($fm['evidence'] ?? []);

        $book = [
            'title' => $fm['title'] ?? null,
            'subtitle' => $fm['subtitle'] ?? null,
            'alt_title' => $fm['alt_title'] ?? null,
            'alt_title_lang' => $fm['alt_title_lang'] ?? 'en',
            'type' => in_array($fm['type'] ?? null, ['edited_book', 'monograph', 'reference', 'other'], true) ? $fm['type'] : null,
            'series' => ($fm['series_title'] ?? null) ? ['title' => $fm['series_title'], 'issn' => $fm['series_issn'] ?? null] : null,
            'volume' => $fm['volume'] ?? null,
            'edition_number' => $fm['edition_number'] ?? null,
            'language' => $fm['language'] ?? 'pt',
            'publisher' => $fm['publisher'] ?? null,
            'publication_place' => $fm['publication_place'] ?? null,
            'publication_year' => $fm['publication_year'] ?? null,
            'publication_date' => $fm['publication_date'] ?? null,
            'pages' => $fm['pages'] ?? $import['pages'] ?? null,
            'format' => null,
            'subject_area' => $fm['subject_area'] ?? null,
            'subject_subarea' => $fm['subject_subarea'] ?? null,
            'audience' => null,
            'isbn_print' => $this->isbn($fm['isbn_print'] ?? null),
            'isbn_ebook' => $this->isbn($fm['isbn_ebook'] ?? null),
            'other_isbns' => array_values(array_map(fn ($o) => ['type' => $o['type'] ?? null, 'value' => $o['value'] ?? null], (array) ($fm['other_isbns'] ?? []))),
            'keywords' => array_values((array) ($fm['keywords'] ?? [])),
            'keywords_en' => [],
            'abstract' => $fm['abstract'] ?? null,
            'abstract_source' => ($fm['abstract'] ?? null) ? 'pdf' : null,
            'synopsis' => null,
            'references' => [],
            'contributors' => $contributors,
            'toc' => $this->tocOutline($segmentation['chapters']),
            'evidence' => $evidence,
            'notes' => $fm['notes'] ?? null,
        ];
        $book['needs_review'] = $this->bookNeedsReview($book, $evidence);
        if (($fm['isbn_print'] ?? null) && $book['isbn_print'] === null) {
            $book['needs_review'][] = 'isbn_print';
            $warnings[] = 'ISBN impresso lido pela IA não passou na validação ISBN-13: '.$fm['isbn_print'];
        }
        if (($fm['isbn_ebook'] ?? null) && $book['isbn_ebook'] === null) {
            $book['needs_review'][] = 'isbn_ebook';
            $warnings[] = 'ISBN do e-book lido pela IA não passou na validação ISBN-13: '.$fm['isbn_ebook'];
        }
        $book['needs_review'] = array_values(array_unique($book['needs_review']));

        $bySeq = [];
        foreach ($chapters as $c) {
            $bySeq[$c['sequence']] = $c;
        }
        $outChapters = [];
        foreach ($segmentation['chapters'] as $seg) {
            $outChapters[] = $bySeq[$seg['sequence']] ?? $this->chapterSkeleton($seg) + ['status' => 'pending'];
        }

        if ($segmentation['offset'] === null) {
            $warnings[] = 'Nenhum número de página impresso foi reconhecido; as páginas impressas vieram só do sumário.';
        }
        $lost = count(array_filter($outChapters, fn ($c) => $c['pdf_first_page'] === null));
        if ($lost > 0) {
            $warnings[] = "{$lost} capítulo(s) do sumário não foram localizados no texto.";
        }

        return [
            'format' => self::FORMAT,
            'generated_at' => now()->toIso8601String(),
            'model' => (string) config('hub.ai.model'),
            'prompt_version' => self::PROMPT_VERSION,
            'source' => [
                'file' => $import['original_name'] ?? null,
                'pages' => $import['pages'] ?? count($segmentation['chapters']),
                'pages_with_text' => $import['pages_with_text'] ?? null,
                'front_matter' => ['pages' => $frontMatter['pages'], 'mode' => $frontMatter['mode']],
                'page_offset' => $segmentation['offset'],
                'anchors' => $segmentation['anchors'],
            ],
            'book' => $book,
            'chapters' => $outChapters,
            'people' => $this->people($contributors, $outChapters),
            'warnings' => array_values(array_unique($warnings)),
            'summary' => [
                'chapters' => count($outChapters),
                'analyzed' => count(array_filter($outChapters, fn ($c) => ($c['status'] ?? '') === 'suggested')),
                'failed' => count(array_filter($outChapters, fn ($c) => ($c['status'] ?? '') === 'failed')),
                'needs_review' => count(array_filter($outChapters, fn ($c) => ! empty($c['needs_review']))),
                'abstracts_from_pdf' => count(array_filter($outChapters, fn ($c) => ($c['abstract_source'] ?? null) === 'pdf')),
                'abstracts_from_ai' => count(array_filter($outChapters, fn ($c) => ($c['abstract_source'] ?? null) === 'ai')),
            ],
        ];
    }

    // ------------------------------------------------------------- apoio

    /** Pessoas da obra no formato do contrato, com evidência e confiança. */
    public function bookContributors(array $fm): array
    {
        $out = [];
        $seq = ['editor' => 0, 'author' => 0, 'translator' => 0, 'other' => 0];
        foreach ((array) ($fm['contributors'] ?? []) as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $role = in_array($c['role'] ?? null, ['editor', 'author', 'translator', 'other'], true) ? $c['role'] : 'author';
            $orcid = $this->orcid($c['orcid'] ?? null);
            $aff = $c['affiliation'] ?? null;
            $needs = [];
            if ($orcid === null && ! $aff) {
                $needs[] = 'affiliation'; // regra: ORCID ou instituição
            }
            if (($c['orcid'] ?? null) && $orcid === null) {
                $needs[] = 'orcid';
            }
            $out[] = [
                'name' => $name,
                'sort_name' => self::sortName($name),
                'orcid' => $orcid,
                'role' => $role,
                'sequence' => ++$seq[$role],
                'affiliation' => $aff ? ['name' => $aff, 'city' => $c['city'] ?? null, 'state' => $c['state'] ?? null, 'country' => $c['country'] ?? ($c['state'] ?? null ? 'BR' : null)] : null,
                'affiliation_text' => $aff,
                'evidence' => $c['evidence'] ?? null,
                'confidence' => (float) ($c['confidence'] ?? 0),
                'needs_review' => $needs,
            ];
        }

        return $out;
    }

    /**
     * "SOBRENOME, Nome" no padrão ABNT: partícula fica com o prenome ("SILVA, Maria
     * Exemplo da") e sufixo de parentesco vai com o sobrenome ("FERREIRA FILHO, Wilmar").
     */
    public static function sortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) < 2) {
            return mb_strtoupper($name);
        }
        $suffixes = ['filho', 'júnior', 'junior', 'jr', 'jr.', 'neto', 'sobrinho', 'segundo', 'terceiro'];

        $last = [array_pop($parts)];
        if (in_array(mb_strtolower(Str::ascii($last[0])), $suffixes, true) && count($parts) > 1) {
            array_unshift($last, array_pop($parts));
        }

        return mb_strtoupper(implode(' ', $last)).', '.implode(' ', $parts);
    }

    private function chapterSkeleton(array $seg): array
    {
        return [
            'sequence' => $seg['sequence'],
            'number' => $seg['number'] ?? null,
            'section' => $seg['section'] ?? null,
            'title' => $seg['title'],
            'subtitle' => null,
            'title_en' => null,
            'language' => null,
            'first_page' => $seg['first_page'] ?? null,
            'last_page' => $seg['last_page'] ?? null,
            'pdf_first_page' => $seg['pdf_first_page'] ?? null,
            'pdf_last_page' => $seg['pdf_last_page'] ?? null,
            'abstract_original' => null,
            'abstract_ai' => null,
            'abstract_source' => null,
            'keywords' => [],
            'keywords_source' => null,
            'references' => [],
            'contributors' => [],
            'toc_authors' => $seg['toc_authors'] ?? [],
            'evidence' => [
                'pages' => ! empty($seg['evidence']) ? $seg['evidence'] + ['confidence' => $seg['confidence'] ?? 0, 'source' => 'segmenter'] : null,
            ],
            'confidence' => (float) ($seg['confidence'] ?? 0),
            'needs_review' => ! empty($seg['needs_review']) ? ['pages'] : [],
            'notes' => null,
            'status' => 'pending',
            'error' => null,
            'usage' => null,
        ];
    }

    private function mergeChapter(array $base, array $data, array $bookContributors): array
    {
        $evidence = $this->evidenceMap($data['evidence'] ?? []);
        $needs = $base['needs_review'];

        $title = trim((string) ($data['title'] ?? '')) ?: $base['title'];
        if ($this->segmenter->normalize($title) !== $this->segmenter->normalize($base['title'])) {
            $needs[] = 'title'; // sumário e corpo divergem: revisor escolhe
        }

        $contrib = [];
        $i = 0;
        foreach ((array) ($data['authors'] ?? []) as $a) {
            $name = trim((string) ($a['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $idx = $this->matchContributor($a['book_contributor'] ?? $name, $bookContributors) ?? $this->matchContributor($name, $bookContributors);
            $aff = $a['affiliation'] ?? ($idx !== null ? $bookContributors[$idx]['affiliation_text'] : null);
            $orcid = $this->orcid($a['orcid'] ?? null) ?? ($idx !== null ? $this->orcid($bookContributors[$idx]['orcid'] ?? null) : null);
            $cn = [];
            if ($idx === null) {
                $cn[] = 'person'; // não está na lista da obra: pode ser pessoa nova ou grafia diferente
            }
            if ($orcid === null && ! $aff) {
                $cn[] = 'affiliation';
            }
            $contrib[] = [
                'name' => $name,
                'sort_name' => self::sortName($name),
                'orcid' => $orcid,
                'role' => 'author',
                'sequence' => ++$i,
                'book_contributor_index' => $idx,
                'affiliation_text' => $aff,
                'evidence' => $a['evidence'] ?? null,
                'confidence' => (float) ($a['confidence'] ?? 0),
                'needs_review' => $cn,
            ];
        }
        if ($contrib === []) {
            $needs[] = 'authors';
        } elseif (array_filter($contrib, fn ($c) => $c['needs_review'] !== [])) {
            $needs[] = 'authors';
        }

        $original = $this->cleanText($data['abstract_original'] ?? null);
        $ai = $this->cleanText($data['abstract_ai'] ?? null);
        if ($original !== null) {
            $ai = null; // o original manda; a sugestão só existe na falta dele
        }
        $words = $ai !== null ? str_word_count(Str::ascii($ai)) : 0;
        if ($original === null && $ai === null) {
            $needs[] = 'abstract';
        } elseif ($ai !== null && ($words < 100 || $words > 280)) {
            $needs[] = 'abstract'; // fora da faixa pedida (120–250, com folga)
        }
        $evidence['abstract'] = ($evidence['abstract_original'] ?? $evidence['abstract_ai'] ?? $evidence['abstract'] ?? null);
        if ($ai !== null) {
            $evidence['abstract'] = ['pdf_page' => $base['pdf_first_page'], 'excerpt' => null, 'confidence' => $evidence['abstract']['confidence'] ?? 0.7, 'source' => 'ai'];
        } elseif (is_array($evidence['abstract'])) {
            $evidence['abstract']['source'] = 'pdf';
        }

        $kwOriginal = $this->strings($data['keywords_original'] ?? []);
        $kwAi = $this->strings($data['keywords_ai'] ?? []);
        $keywords = $kwOriginal !== [] ? $kwOriginal : $kwAi;
        $kwSource = $kwOriginal !== [] ? 'pdf' : ($kwAi !== [] ? 'ai' : null);
        if ($keywords === []) {
            $needs[] = 'keywords';
        }
        $evidence['keywords'] = $evidence['keywords_original'] ?? $evidence['keywords_ai'] ?? $evidence['keywords'] ?? null;
        if ($kwSource === 'ai') {
            $evidence['keywords'] = ['pdf_page' => $base['pdf_first_page'], 'excerpt' => null, 'confidence' => 0.7, 'source' => 'ai'];
        } elseif (is_array($evidence['keywords'])) {
            $evidence['keywords']['source'] = 'pdf';
        }

        $refs = [];
        foreach ((array) ($data['references'] ?? []) as $r) {
            $text = trim((string) ($r['text'] ?? ''));
            if ($text !== '') {
                $refs[] = ['text' => $text, 'doi' => $this->doi($r['doi'] ?? null)];
            }
        }

        $base['evidence']['pages'] = $base['evidence']['pages'] ?? null;
        foreach (['title', 'authors', 'abstract', 'keywords', 'references', 'subtitle', 'title_en', 'number', 'language'] as $f) {
            if (isset($evidence[$f])) {
                $base['evidence'][$f] = $evidence[$f] + ['source' => $evidence[$f]['source'] ?? 'pdf'];
            }
        }

        $confidences = array_filter(array_map(fn ($e) => $e['confidence'] ?? null, $base['evidence']), fn ($c) => $c !== null);

        return array_merge($base, [
            'title' => $title,
            'subtitle' => $data['subtitle'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'number' => $data['number'] ?? $base['number'],
            'language' => $data['language'] ?? null,
            'abstract_original' => $original,
            'abstract_ai' => $ai,
            'abstract_source' => $original !== null ? 'pdf' : ($ai !== null ? 'ai' : null),
            'keywords' => $keywords,
            'keywords_source' => $kwSource,
            'references' => $refs,
            'contributors' => $contrib,
            'confidence' => $confidences ? round(array_sum($confidences) / count($confidences), 2) : $base['confidence'],
            'needs_review' => array_values(array_unique($needs)),
            'notes' => $data['notes'] ?? null,
            'status' => 'suggested',
            'error' => null,
            'usage' => $this->usage->forChapter((int) $base['sequence']),
        ]);
    }

    /** Pessoas únicas da proposta (obra + capítulos), para a etapa "Autores" da revisão. */
    private function people(array $bookContributors, array $chapters): array
    {
        $people = [];
        $keys = [];
        $add = function (string $key, string $name, ?string $aff, ?string $orcid, string $role, ?int $chapter) use (&$people) {
            if (! isset($people[$key])) {
                $people[$key] = ['name' => $name, 'sort_name' => self::sortName($name), 'orcid' => $orcid, 'affiliation_text' => $aff, 'roles' => [], 'chapters' => [], 'needs_review' => []];
            }
            $people[$key]['orcid'] ??= $orcid;
            $people[$key]['affiliation_text'] ??= $aff;
            $people[$key]['roles'][] = $role;
            if ($chapter !== null) {
                $people[$key]['chapters'][] = $chapter;
            }
        };
        foreach ($bookContributors as $i => $c) {
            $keys[$i] = trim($this->segmenter->normalize($c['name']));
            $add($keys[$i], $c['name'], $c['affiliation_text'], $c['orcid'], $c['role'], null);
        }
        foreach ($chapters as $ch) {
            foreach ((array) ($ch['contributors'] ?? []) as $c) {
                // Autor já ligado a uma pessoa da obra entra nela, mesmo com grafia diferente no capítulo.
                $idx = $c['book_contributor_index'] ?? null;
                $key = $idx !== null && isset($keys[$idx]) ? $keys[$idx] : trim($this->segmenter->normalize($c['name']));
                $add($key, $c['name'], $c['affiliation_text'] ?? null, $c['orcid'] ?? null, 'author', (int) $ch['sequence']);
            }
        }
        foreach ($people as &$p) {
            $p['roles'] = array_values(array_unique($p['roles']));
            $p['chapters'] = array_values(array_unique($p['chapters']));
            if ($p['orcid'] === null && ! $p['affiliation_text']) {
                $p['needs_review'][] = 'affiliation';
            }
        }

        return array_values($people);
    }

    private function tocOutline(array $chapters): array
    {
        $out = [];
        foreach ($chapters as $c) {
            $section = $c['section'] ?? null;
            $k = (string) $section;
            $out[$k] ??= ['section' => $section, 'chapters' => []];
            $out[$k]['chapters'][] = $c['sequence'];
        }

        return array_values($out);
    }

    private function bookNeedsReview(array $book, array $evidence): array
    {
        $needs = [];
        foreach (['title', 'type', 'publisher', 'publication_place', 'publication_year', 'language', 'subject_area'] as $f) {
            if ($book[$f] === null || $book[$f] === '') {
                $needs[] = $f;
            }
        }
        if ($book['isbn_print'] === null && $book['isbn_ebook'] === null) {
            $needs[] = 'isbn_print';
        }
        if ($book['abstract'] === null) {
            $needs[] = 'abstract';
        }
        if ($book['keywords'] === []) {
            $needs[] = 'keywords';
        }
        if ($book['contributors'] === []) {
            $needs[] = 'contributors';
        }
        foreach ($evidence as $f => $e) {
            if (($e['confidence'] ?? 1) < 0.6) {
                $needs[] = $f;
            }
        }

        return array_values(array_unique($needs));
    }

    /** Lista {field, pdf_page, excerpt, confidence} → mapa por campo. */
    private function evidenceMap(array $list): array
    {
        $map = [];
        foreach ($list as $e) {
            $f = trim((string) ($e['field'] ?? ''));
            if ($f === '') {
                continue;
            }
            $map[$f] = [
                'pdf_page' => isset($e['pdf_page']) ? (int) $e['pdf_page'] : null,
                'excerpt' => isset($e['excerpt']) ? mb_substr((string) $e['excerpt'], 0, 300) : null,
                'confidence' => isset($e['confidence']) ? round((float) $e['confidence'], 2) : null,
            ];
        }

        return $map;
    }

    private function matchContributor(?string $name, array $contributors): ?int
    {
        if (! $name) {
            return null;
        }
        $key = trim($this->segmenter->normalize($name));
        foreach ($contributors as $i => $c) {
            if (trim($this->segmenter->normalize($c['name'])) === $key) {
                return $i;
            }
        }
        // Sobrenome + primeiro nome iguais (ex.: "Maria E. da Silva" × "Maria Exemplo da Silva").
        $parts = explode(' ', $key);
        if (count($parts) >= 2) {
            foreach ($contributors as $i => $c) {
                $cp = explode(' ', trim($this->segmenter->normalize($c['name'])));
                if (count($cp) >= 2 && $cp[0] === $parts[0] && end($cp) === end($parts)) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Texto do capítulo em partes que caibam em max_chapter_tokens.
     *
     * @return list<string>
     */
    private function split(array $pages, int $from, int $to): array
    {
        $limit = max(2000, (int) config('hub.ai.max_chapter_tokens', 60000)) * self::CHARS_PER_TOKEN;
        $parts = [];
        $current = [];
        $size = 0;
        foreach ($pages as $p) {
            if ($p['pdf_page'] < $from || $p['pdf_page'] > $to) {
                continue;
            }
            $len = mb_strlen($p['text']) + 30;
            if ($current !== [] && $size + $len > $limit) {
                $parts[] = $current;
                $current = [];
                $size = 0;
            }
            $current[] = $p;
            $size += $len;
        }
        if ($current !== []) {
            $parts[] = $current;
        }

        return array_map(fn ($chunk) => Segmenter::text($chunk, $from, $to), $parts ?: [[]]);
    }

    private function maxOutput(): int
    {
        return max(1024, (int) config('hub.ai.max_output_tokens', 32000));
    }

    private function cleanText(?string $text): ?string
    {
        $text = trim((string) $text);
        $text = preg_replace('/\s*\n\s*/', ' ', $text) ?? $text;

        return $text === '' ? null : $text;
    }

    private function strings(mixed $list): array
    {
        return array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) $list), fn ($s) => $s !== ''));
    }

    /** ISBN-13 válido (dígito verificador) → formatado como veio; inválido → null (fica em needs_review). */
    public function isbn(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) !== 13) {
            return null;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return ((10 - $sum % 10) % 10) === (int) $digits[12] ? trim($raw) : null;
    }

    /** ORCID com checksum válido ou null. */
    public function orcid(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $clean = strtoupper(preg_replace('/[^0-9Xx]/', '', (string) preg_replace('~^https?://orcid\.org/~i', '', $raw)) ?? '');
        if (! preg_match('/^\d{15}[\dX]$/', $clean)) {
            return null;
        }
        $total = 0;
        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $clean[$i]) * 2;
        }
        $check = (12 - ($total % 11)) % 11;
        $expected = $check === 10 ? 'X' : (string) $check;
        if ($clean[15] !== $expected) {
            return null;
        }

        return implode('-', str_split($clean, 4));
    }

    private function doi(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $d = preg_replace('~^(https?://)?(dx\.)?doi\.org/~i', '', trim($raw)) ?? '';

        return preg_match('~^10\.\d{4,9}/\S+$~', $d) ? $d : null;
    }
}
