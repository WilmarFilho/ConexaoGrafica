<?php
/**
 * Citações geradas dos dados do Hub: ABNT NBR 6023:2018, APA 7, Vancouver
 * (NLM), BibTeX e RIS, para livro e capítulo.
 *
 * Decisões onde a norma deixa escolha:
 *  - ABNT: organizadores com "(org.)" no singular, como manda a 6023:2018;
 *    título do livro em negrito; edição só a partir da 2ª; "DOI: 10.…".
 *  - APA: nomes com iniciais, "(Org.)/(Orgs.)" em português, título em
 *    itálico, "https://doi.org/…".
 *  - Vancouver: até 6 autores, depois "et al."; "editores" em português;
 *    "doi: 10.…".
 *  - BibTeX: @book e @incollection; chave = sufixo do DOI sem pontuação.
 *  - RIS: TY BOOK / CHAP.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Os cinco formatos para um livro: [chave => [rotulo, html, texto]]. */
function conexao_doi_citacoes_livro(array $livro): array
{
    $pessoas = conexao_doi_citacao_pessoas($livro);

    return conexao_doi_citacoes_monta([
        'abnt' => conexao_doi_abnt_livro($livro, $pessoas),
        'apa' => conexao_doi_apa_livro($livro, $pessoas),
        'vancouver' => conexao_doi_vancouver_livro($livro, $pessoas),
        'bibtex' => conexao_doi_bibtex_livro($livro, $pessoas),
        'ris' => conexao_doi_ris_livro($livro, $pessoas),
    ]);
}

/** Os cinco formatos para um capítulo. */
function conexao_doi_citacoes_capitulo(array $capitulo, array $livro): array
{
    $pessoas_livro = conexao_doi_citacao_pessoas($livro);
    $autores = conexao_doi_lista($capitulo['contributors'] ?? []);

    return conexao_doi_citacoes_monta([
        'abnt' => conexao_doi_abnt_capitulo($capitulo, $livro, $autores, $pessoas_livro),
        'apa' => conexao_doi_apa_capitulo($capitulo, $livro, $autores, $pessoas_livro),
        'vancouver' => conexao_doi_vancouver_capitulo($capitulo, $livro, $autores, $pessoas_livro),
        'bibtex' => conexao_doi_bibtex_capitulo($capitulo, $livro, $autores, $pessoas_livro),
        'ris' => conexao_doi_ris_capitulo($capitulo, $livro, $autores, $pessoas_livro),
    ]);
}

function conexao_doi_citacoes_monta(array $html): array
{
    $rotulos = ['abnt' => 'ABNT', 'apa' => 'APA', 'vancouver' => 'Vancouver', 'bibtex' => 'BibTeX', 'ris' => 'RIS'];
    $saida = [];

    foreach ($html as $chave => $conteudo) {
        $saida[$chave] = [
            'rotulo' => $rotulos[$chave],
            'html' => $conteudo,
            'texto' => html_entity_decode(wp_strip_all_tags($conteudo), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'bloco' => in_array($chave, ['bibtex', 'ris'], true),
        ];
    }

    return $saida;
}

// ---------------------------------------------------------------- pessoas

/**
 * Quem responde pelo livro: os organizadores (role editor) quando há, senão
 * os autores. Devolve [lista, organizado?].
 */
function conexao_doi_citacao_pessoas(array $livro): array
{
    $todos = conexao_doi_lista($livro['contributors'] ?? []);
    $editores = array_values(array_filter($todos, static fn ($c) => ($c['role'] ?? '') === 'editor'));

    if ($editores) {
        return ['lista' => $editores, 'org' => true];
    }

    $autores = array_values(array_filter($todos, static fn ($c) => ($c['role'] ?? 'author') === 'author'));

    return ['lista' => $autores ?: $todos, 'org' => false];
}

/** Sobrenome e nomes de uma pessoa, a partir do sort_name "SOBRENOME, Nome". */
function conexao_doi_nome_partes(array $pessoa): array
{
    $sort = trim((string) ($pessoa['sort_name'] ?? ''));

    if ($sort !== '' && str_contains($sort, ',')) {
        [$sobrenome, $nomes] = array_map('trim', explode(',', $sort, 2));
    } else {
        $partes = preg_split('/\s+/', trim((string) ($pessoa['name'] ?? ''))) ?: [];
        $sobrenome = (string) array_pop($partes);
        $nomes = implode(' ', $partes);
    }

    return ['sobrenome' => $sobrenome, 'nomes' => $nomes];
}

/** Sobrenome com iniciais maiúsculas ("SILVA" → "Silva", "DE OLIVEIRA" → "De Oliveira"). */
function conexao_doi_sobrenome_normal(string $sobrenome): string
{
    return mb_convert_case(mb_strtolower($sobrenome, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/** Iniciais dos nomes, pulando partículas (da, de, dos…). */
function conexao_doi_iniciais(string $nomes, string $separador = '. ', bool $ponto_final = true): string
{
    $particulas = ['da', 'de', 'do', 'das', 'dos', 'e', 'y', 'von', 'van', 'del', 'della', 'di', 'la', 'le'];
    $iniciais = [];

    foreach (preg_split('/[\s-]+/', trim($nomes)) ?: [] as $nome) {
        if ($nome === '' || in_array(mb_strtolower($nome, 'UTF-8'), $particulas, true)) {
            continue;
        }

        $iniciais[] = mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8');
    }

    $texto = implode($separador, $iniciais);

    return ($texto !== '' && $ponto_final) ? $texto.'.' : $texto;
}

function conexao_doi_nome_abnt(array $pessoa): string
{
    $p = conexao_doi_nome_partes($pessoa);

    return mb_strtoupper($p['sobrenome'], 'UTF-8').($p['nomes'] !== '' ? ', '.$p['nomes'] : '');
}

function conexao_doi_nome_apa(array $pessoa): string
{
    $p = conexao_doi_nome_partes($pessoa);
    $iniciais = conexao_doi_iniciais($p['nomes']);

    return conexao_doi_sobrenome_normal($p['sobrenome']).($iniciais !== '' ? ', '.$iniciais : '');
}

/** APA dentro do "In": "M. E. Silva". */
function conexao_doi_nome_apa_direto(array $pessoa): string
{
    $p = conexao_doi_nome_partes($pessoa);
    $iniciais = conexao_doi_iniciais($p['nomes']);

    return trim($iniciais.' '.conexao_doi_sobrenome_normal($p['sobrenome']));
}

function conexao_doi_nome_vancouver(array $pessoa): string
{
    $p = conexao_doi_nome_partes($pessoa);

    return trim(conexao_doi_sobrenome_normal($p['sobrenome']).' '.conexao_doi_iniciais($p['nomes'], '', false));
}

function conexao_doi_nome_bibtex(array $pessoa): string
{
    $p = conexao_doi_nome_partes($pessoa);

    return conexao_doi_sobrenome_normal($p['sobrenome']).($p['nomes'] !== '' ? ', '.$p['nomes'] : '');
}

/** Lista APA: "A, B, & C" (duas pessoas: "A, & B"). */
function conexao_doi_junta_apa(array $nomes): string
{
    if (count($nomes) <= 1) {
        return implode('', $nomes);
    }

    $ultimo = array_pop($nomes);

    return implode(', ', $nomes).', & '.$ultimo;
}

/** Lista APA no "In": "A. Silva & B. Pereira" (três ou mais: "A, B, & C"). */
function conexao_doi_junta_apa_direto(array $nomes): string
{
    if (count($nomes) <= 1) {
        return implode('', $nomes);
    }

    if (count($nomes) === 2) {
        return $nomes[0].' & '.$nomes[1];
    }

    $ultimo = array_pop($nomes);

    return implode(', ', $nomes).', & '.$ultimo;
}

function conexao_doi_junta_vancouver(array $nomes): string
{
    if (count($nomes) > 6) {
        return implode(', ', array_slice($nomes, 0, 6)).', et al';
    }

    return implode(', ', $nomes);
}

// ---------------------------------------------------------------- pedaços

function conexao_doi_titulo_completo(array $obra): string
{
    $titulo = trim((string) ($obra['title'] ?? ''));
    $sub = trim((string) ($obra['subtitle'] ?? ''));

    return $sub !== '' ? $titulo.': '.$sub : $titulo;
}

function conexao_doi_doi_url(string $doi): string
{
    return $doi !== '' ? 'https://doi.org/'.$doi : '';
}

function conexao_doi_paginas(array $capitulo, string $traco = '-'): string
{
    $de = (string) ($capitulo['first_page'] ?? '');
    $ate = (string) ($capitulo['last_page'] ?? '');

    if ($de === '' && $ate === '') {
        return '';
    }

    return ($de !== '' && $ate !== '' && $de !== $ate) ? $de.$traco.$ate : ($de ?: $ate);
}

function conexao_doi_edicao_num(array $livro): int
{
    return (int) ($livro['edition_number'] ?? 0);
}

function conexao_doi_e(string $s): string
{
    return esc_html($s);
}

/** Fecha com ponto, sem dobrar quando a inicial já termina em ponto. */
function conexao_doi_com_ponto(string $s): string
{
    return str_ends_with($s, '.') ? $s : $s.'.';
}

// ---------------------------------------------------------------- ABNT

function conexao_doi_abnt_livro(array $livro, array $pessoas): string
{
    $nomes = array_map('conexao_doi_nome_abnt', $pessoas['lista']);
    $partes = [];

    if ($nomes) {
        $partes[] = conexao_doi_e(implode('; ', $nomes).($pessoas['org'] ? ' (org.)' : '')).'.';
    }

    $partes[] = '<b>'.conexao_doi_e(conexao_doi_titulo_completo($livro)).'</b>.';

    if (conexao_doi_edicao_num($livro) > 1) {
        $partes[] = conexao_doi_edicao_num($livro).'. ed.';
    }

    $partes[] = conexao_doi_e(trim(($livro['publication_place'] ?? '').': '.($livro['publisher'] ?? ''), ': ').', '.($livro['publication_year'] ?? '')).'.';

    if (! empty($livro['pages'])) {
        $partes[] = (int) $livro['pages'].' p.';
    }

    if (! empty($livro['isbn_print'])) {
        $partes[] = 'ISBN '.conexao_doi_e((string) $livro['isbn_print']).'.';
    }

    if (! empty($livro['doi'])) {
        $partes[] = 'DOI: '.conexao_doi_e((string) $livro['doi']).'.';
    }

    return implode(' ', $partes);
}

function conexao_doi_abnt_capitulo(array $capitulo, array $livro, array $autores, array $pessoas_livro): string
{
    $partes = [];
    $nomes = array_map('conexao_doi_nome_abnt', $autores);

    if ($nomes) {
        $partes[] = conexao_doi_e(implode('; ', $nomes)).'.';
    }

    $partes[] = conexao_doi_e(conexao_doi_titulo_completo($capitulo)).'.';

    $nomes_livro = array_map('conexao_doi_nome_abnt', $pessoas_livro['lista']);
    $in = 'In: ';

    if ($nomes_livro) {
        $in .= conexao_doi_e(implode('; ', $nomes_livro).($pessoas_livro['org'] ? ' (org.)' : '')).'. ';
    }

    $in .= '<b>'.conexao_doi_e(conexao_doi_titulo_completo($livro)).'</b>.';
    $partes[] = $in;

    if (conexao_doi_edicao_num($livro) > 1) {
        $partes[] = conexao_doi_edicao_num($livro).'. ed.';
    }

    $partes[] = conexao_doi_e(trim(($livro['publication_place'] ?? '').': '.($livro['publisher'] ?? ''), ': ').', '.($livro['publication_year'] ?? '')).'.';

    $paginas = conexao_doi_paginas($capitulo);

    if ($paginas !== '') {
        $partes[] = 'p. '.conexao_doi_e($paginas).'.';
    }

    if (! empty($capitulo['doi'])) {
        $partes[] = 'DOI: '.conexao_doi_e((string) $capitulo['doi']).'.';
    }

    return implode(' ', $partes);
}

// ---------------------------------------------------------------- APA 7

function conexao_doi_apa_livro(array $livro, array $pessoas): string
{
    $nomes = array_map('conexao_doi_nome_apa', $pessoas['lista']);
    $partes = [];

    if ($nomes) {
        $autores = conexao_doi_junta_apa($nomes);

        if ($pessoas['org']) {
            $autores .= count($nomes) > 1 ? ' (Orgs.)' : ' (Org.)';
        }

        $partes[] = conexao_doi_e(conexao_doi_com_ponto($autores));
    }

    $partes[] = '('.conexao_doi_e((string) ($livro['publication_year'] ?? 's.d.')).').';

    $titulo = '<i>'.conexao_doi_e(conexao_doi_titulo_completo($livro)).'</i>';

    if (conexao_doi_edicao_num($livro) > 1) {
        $titulo .= ' ('.conexao_doi_edicao_num($livro).'a ed.)';
    }

    $partes[] = $titulo.'.';
    $partes[] = conexao_doi_e((string) ($livro['publisher'] ?? '')).'.';

    if (! empty($livro['doi'])) {
        $partes[] = conexao_doi_e(conexao_doi_doi_url((string) $livro['doi']));
    }

    return implode(' ', $partes);
}

function conexao_doi_apa_capitulo(array $capitulo, array $livro, array $autores, array $pessoas_livro): string
{
    $partes = [];
    $nomes = array_map('conexao_doi_nome_apa', $autores);

    if ($nomes) {
        $partes[] = conexao_doi_e(conexao_doi_com_ponto(conexao_doi_junta_apa($nomes)));
    }

    $ano = $capitulo['publication_date'] ?? null;
    $ano = $ano ? substr((string) $ano, 0, 4) : (string) ($livro['publication_year'] ?? 's.d.');
    $partes[] = '('.conexao_doi_e($ano).').';
    $partes[] = conexao_doi_e(conexao_doi_titulo_completo($capitulo)).'.';

    $nomes_livro = array_map('conexao_doi_nome_apa_direto', $pessoas_livro['lista']);
    $in = 'In ';

    if ($nomes_livro) {
        $in .= conexao_doi_e(conexao_doi_junta_apa_direto($nomes_livro));

        if ($pessoas_livro['org']) {
            $in .= count($nomes_livro) > 1 ? ' (Orgs.)' : ' (Org.)';
        }

        $in .= ', ';
    }

    $in .= '<i>'.conexao_doi_e(conexao_doi_titulo_completo($livro)).'</i>';

    $detalhes = [];

    if (conexao_doi_edicao_num($livro) > 1) {
        $detalhes[] = conexao_doi_edicao_num($livro).'a ed.';
    }

    $paginas = conexao_doi_paginas($capitulo, '–');

    if ($paginas !== '') {
        $detalhes[] = (str_contains($paginas, '–') ? 'pp. ' : 'p. ').$paginas;
    }

    if ($detalhes) {
        $in .= ' ('.conexao_doi_e(implode(', ', $detalhes)).')';
    }

    $partes[] = $in.'.';
    $partes[] = conexao_doi_e((string) ($livro['publisher'] ?? '')).'.';

    if (! empty($capitulo['doi'])) {
        $partes[] = conexao_doi_e(conexao_doi_doi_url((string) $capitulo['doi']));
    }

    return implode(' ', $partes);
}

// ---------------------------------------------------------------- Vancouver

function conexao_doi_vancouver_livro(array $livro, array $pessoas): string
{
    $nomes = array_map('conexao_doi_nome_vancouver', $pessoas['lista']);
    $partes = [];

    if ($nomes) {
        $partes[] = conexao_doi_e(conexao_doi_junta_vancouver($nomes).($pessoas['org'] ? (count($nomes) > 1 ? ', editores' : ', editor') : '')).'.';
    }

    $partes[] = conexao_doi_e(conexao_doi_titulo_completo($livro)).'.';

    if (conexao_doi_edicao_num($livro) > 1) {
        $partes[] = conexao_doi_edicao_num($livro).'a ed.';
    }

    $partes[] = conexao_doi_e(trim(($livro['publication_place'] ?? '').': '.($livro['publisher'] ?? ''), ': ').'; '.($livro['publication_year'] ?? '')).'.';

    if (! empty($livro['pages'])) {
        $partes[] = (int) $livro['pages'].' p.';
    }

    if (! empty($livro['doi'])) {
        $partes[] = 'doi: '.conexao_doi_e((string) $livro['doi']);
    }

    return implode(' ', $partes);
}

function conexao_doi_vancouver_capitulo(array $capitulo, array $livro, array $autores, array $pessoas_livro): string
{
    $partes = [];
    $nomes = array_map('conexao_doi_nome_vancouver', $autores);

    if ($nomes) {
        $partes[] = conexao_doi_e(conexao_doi_junta_vancouver($nomes)).'.';
    }

    $partes[] = conexao_doi_e(conexao_doi_titulo_completo($capitulo)).'.';

    $nomes_livro = array_map('conexao_doi_nome_vancouver', $pessoas_livro['lista']);
    $in = 'In: ';

    if ($nomes_livro) {
        $in .= conexao_doi_e(conexao_doi_junta_vancouver($nomes_livro).($pessoas_livro['org'] ? (count($nomes_livro) > 1 ? ', editores' : ', editor') : '')).'. ';
    }

    $in .= conexao_doi_e(conexao_doi_titulo_completo($livro)).'.';
    $partes[] = $in;

    if (conexao_doi_edicao_num($livro) > 1) {
        $partes[] = conexao_doi_edicao_num($livro).'a ed.';
    }

    $partes[] = conexao_doi_e(trim(($livro['publication_place'] ?? '').': '.($livro['publisher'] ?? ''), ': ').'; '.($livro['publication_year'] ?? '')).'.';

    $paginas = conexao_doi_paginas($capitulo);

    if ($paginas !== '') {
        $partes[] = 'p. '.conexao_doi_e($paginas).'.';
    }

    if (! empty($capitulo['doi'])) {
        $partes[] = 'doi: '.conexao_doi_e((string) $capitulo['doi']);
    }

    return implode(' ', $partes);
}

// ---------------------------------------------------------------- BibTeX

function conexao_doi_bibtex_chave(string $doi, string $alternativa): string
{
    $sufixo = $doi !== '' && str_contains($doi, '/') ? substr($doi, strpos($doi, '/') + 1) : $alternativa;
    $chave = preg_replace('/[^a-z0-9]/', '', strtolower(remove_accents($sufixo)));

    return $chave !== '' ? $chave : 'conexao';
}

/** Valor BibTeX: chaves protegem maiúsculas; o próprio { } e \ são escapados. */
function conexao_doi_bibtex_valor(string $valor): string
{
    return str_replace(['\\', '{', '}'], ['\\textbackslash{}', '\\{', '\\}'], $valor);
}

function conexao_doi_bibtex_monta(string $tipo, string $chave, array $campos): string
{
    $linhas = [];

    foreach ($campos as $nome => $valor) {
        $valor = trim((string) $valor);

        if ($valor === '') {
            continue;
        }

        $linhas[] = sprintf('  %s = {%s},', $nome, conexao_doi_bibtex_valor($valor));
    }

    return conexao_doi_e('@'.$tipo.'{'.$chave.",\n".implode("\n", $linhas)."\n}");
}

function conexao_doi_bibtex_livro(array $livro, array $pessoas): string
{
    $nomes = implode(' and ', array_map('conexao_doi_nome_bibtex', $pessoas['lista']));
    $doi = (string) ($livro['doi'] ?? '');

    return conexao_doi_bibtex_monta('book', conexao_doi_bibtex_chave($doi, (string) ($livro['slug'] ?? '')), [
        'title' => conexao_doi_titulo_completo($livro),
        $pessoas['org'] ? 'editor' : 'author' => $nomes,
        'publisher' => $livro['publisher'] ?? '',
        'address' => $livro['publication_place'] ?? '',
        'year' => $livro['publication_year'] ?? '',
        'edition' => conexao_doi_edicao_num($livro) > 1 ? conexao_doi_edicao_num($livro) : '',
        'isbn' => ($livro['isbn_print'] ?? '') ?: ($livro['isbn_ebook'] ?? ''),
        'doi' => $doi,
        'url' => conexao_doi_doi_url($doi),
        'language' => $livro['language'] ?? '',
        'keywords' => implode(', ', (array) ($livro['keywords'] ?? [])),
    ]);
}

function conexao_doi_bibtex_capitulo(array $capitulo, array $livro, array $autores, array $pessoas_livro): string
{
    $doi = (string) ($capitulo['doi'] ?? '');
    $ano = $capitulo['publication_date'] ?? null;

    return conexao_doi_bibtex_monta('incollection', conexao_doi_bibtex_chave($doi, (string) ($capitulo['slug'] ?? '')), [
        'title' => conexao_doi_titulo_completo($capitulo),
        'author' => implode(' and ', array_map('conexao_doi_nome_bibtex', $autores)),
        'booktitle' => conexao_doi_titulo_completo($livro),
        $pessoas_livro['org'] ? 'editor' : 'bookauthor' => implode(' and ', array_map('conexao_doi_nome_bibtex', $pessoas_livro['lista'])),
        'publisher' => $livro['publisher'] ?? '',
        'address' => $livro['publication_place'] ?? '',
        'year' => $ano ? substr((string) $ano, 0, 4) : ($livro['publication_year'] ?? ''),
        'chapter' => $capitulo['number'] ?? '',
        'pages' => conexao_doi_paginas($capitulo, '--'),
        'isbn' => ($livro['isbn_print'] ?? '') ?: ($livro['isbn_ebook'] ?? ''),
        'doi' => $doi,
        'url' => conexao_doi_doi_url($doi),
        'language' => $capitulo['language'] ?? ($livro['language'] ?? ''),
        'keywords' => implode(', ', (array) ($capitulo['keywords'] ?? [])),
    ]);
}

// ---------------------------------------------------------------- RIS

function conexao_doi_ris_monta(array $linhas): string
{
    $saida = [];

    foreach ($linhas as [$tag, $valor]) {
        $valor = trim((string) $valor);

        if ($valor === '') {
            continue;
        }

        $saida[] = $tag.'  - '.str_replace(["\r", "\n"], ' ', $valor);
    }

    $saida[] = 'ER  - ';

    return conexao_doi_e(implode("\n", $saida));
}

function conexao_doi_ris_livro(array $livro, array $pessoas): string
{
    $linhas = [['TY', 'BOOK']];

    foreach ($pessoas['lista'] as $pessoa) {
        $linhas[] = [$pessoas['org'] ? 'ED' : 'AU', conexao_doi_nome_bibtex($pessoa)];
    }

    $linhas[] = ['TI', conexao_doi_titulo_completo($livro)];
    $linhas[] = ['PY', $livro['publication_year'] ?? ''];
    $linhas[] = ['DA', str_replace('-', '/', (string) ($livro['publication_date'] ?? ''))];
    $linhas[] = ['PB', $livro['publisher'] ?? ''];
    $linhas[] = ['CY', $livro['publication_place'] ?? ''];
    $linhas[] = ['ET', conexao_doi_edicao_num($livro) > 1 ? conexao_doi_edicao_num($livro) : ''];
    $linhas[] = ['SN', $livro['isbn_print'] ?? ''];
    $linhas[] = ['SN', $livro['isbn_ebook'] ?? ''];
    $linhas[] = ['DO', $livro['doi'] ?? ''];
    $linhas[] = ['UR', conexao_doi_doi_url((string) ($livro['doi'] ?? ''))];
    $linhas[] = ['LA', $livro['language'] ?? ''];

    foreach ((array) ($livro['keywords'] ?? []) as $kw) {
        $linhas[] = ['KW', $kw];
    }

    $linhas[] = ['AB', $livro['abstract'] ?? ''];

    return conexao_doi_ris_monta($linhas);
}

function conexao_doi_ris_capitulo(array $capitulo, array $livro, array $autores, array $pessoas_livro): string
{
    $linhas = [['TY', 'CHAP']];

    foreach ($autores as $pessoa) {
        $linhas[] = ['AU', conexao_doi_nome_bibtex($pessoa)];
    }

    foreach ($pessoas_livro['lista'] as $pessoa) {
        $linhas[] = [$pessoas_livro['org'] ? 'ED' : 'A2', conexao_doi_nome_bibtex($pessoa)];
    }

    $ano = $capitulo['publication_date'] ?? null;

    $linhas[] = ['TI', conexao_doi_titulo_completo($capitulo)];
    $linhas[] = ['T2', conexao_doi_titulo_completo($livro)];
    $linhas[] = ['PY', $ano ? substr((string) $ano, 0, 4) : ($livro['publication_year'] ?? '')];
    $linhas[] = ['DA', str_replace('-', '/', (string) ($ano ?? ''))];
    $linhas[] = ['PB', $livro['publisher'] ?? ''];
    $linhas[] = ['CY', $livro['publication_place'] ?? ''];
    $linhas[] = ['SP', $capitulo['first_page'] ?? ''];
    $linhas[] = ['EP', $capitulo['last_page'] ?? ''];
    $linhas[] = ['SN', $livro['isbn_print'] ?? ''];
    $linhas[] = ['DO', $capitulo['doi'] ?? ''];
    $linhas[] = ['UR', conexao_doi_doi_url((string) ($capitulo['doi'] ?? ''))];
    $linhas[] = ['LA', $capitulo['language'] ?? ($livro['language'] ?? '')];

    foreach ((array) ($capitulo['keywords'] ?? []) as $kw) {
        $linhas[] = ['KW', $kw];
    }

    $linhas[] = ['AB', $capitulo['abstract'] ?? ''];

    return conexao_doi_ris_monta($linhas);
}
