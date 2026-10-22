<?php
/**
 * Peças que os dois templates repetem: ícones, estilos, pessoas (nome, ORCID,
 * afiliação com sobrescrito), compartilhar, bloco "como citar", sumário em
 * seções, rótulos legíveis dos enums do Hub.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function (): void {
    if (! conexao_doi_e_pagina()) {
        return;
    }

    wp_enqueue_style('conexao-doi', CONEXAO_DOI_URL.'assets/conexao-doi.css', ['conexao'], conexao_doi_versao_arquivo('/assets/conexao-doi.css'));
    wp_enqueue_script('conexao-doi', CONEXAO_DOI_URL.'assets/conexao-doi.js', [], conexao_doi_versao_arquivo('/assets/conexao-doi.js'), true);
}, 30);

// ---------------------------------------------------------------- ícones

/** Ícone inline (traço de 1.8, cor do texto). */
function conexao_doi_icone(string $nome, int $tamanho = 20): void
{
    $cheios = [
        'orcid' => '<circle cx="12" cy="12" r="12" fill="#A6CE39"/><path fill="#fff" d="M7.4 6.7a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8zM6.7 9.4h1.4v7.9H6.7zM10 9.4h3.4c3.2 0 4.6 2.3 4.6 3.95 0 2-1.6 3.95-4.6 3.95H10zm1.4 1.3v5.3h1.9c2.4 0 3.2-1.6 3.2-2.65 0-1.4-.9-2.65-3.3-2.65z"/>',
        'linkedin' => '<path fill="currentColor" d="M6.9 8.6H4V20h2.9zM5.5 4a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4zM20 13.4c0-3.2-1.7-4.9-4-4.9-1.7 0-2.6.9-3.1 1.6V8.6H10c0 .8 0 11.4 0 11.4h2.9v-6.4c0-.3 0-.7.1-.9.3-.7.9-1.4 1.9-1.4 1.3 0 1.9 1 1.9 2.5V20H20z"/>',
        'x' => '<path fill="currentColor" d="M17.8 3h3l-6.7 7.7L22 21h-6.2l-4.8-6.3L5.4 21h-3l7.2-8.2L2 3h6.3l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z"/>',
        'facebook' => '<path fill="currentColor" d="M14.5 21v-8h2.6l.5-3h-3.1V8.2c0-.9.3-1.5 1.6-1.5h1.6V4c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1V10H9v3h2.3v8z"/>',
        'whatsapp' => '<path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.1-.3 0-.5l-.7-1.7c-.2-.4-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.6 4 5 5 0 0 0 2.9.6 2.5 2.5 0 0 0 1.6-1.2 2 2 0 0 0 .1-1.2c0-.1-.2-.2-.4-.3z"/>',
    ];

    $tracos = [
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'carrinho' => '<circle cx="9.5" cy="19" r="1.6"/><circle cx="17" cy="19" r="1.6"/><path d="M3 4h2.2l2.4 11h11l2-8H6.2"/>',
        'check' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.2l2.4 2.4 4.6-4.9"/>',
        'caminhao' => '<path d="M3 6h11v9H3z"/><path d="M14 9h3.5L21 12v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'download' => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M4 19h16"/>',
        'cadeado' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.2 10.5V8a3.8 3.8 0 0 1 7.6 0v2.5"/>',
        'cartao' => '<rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19M6 14.5h4"/>',
        'livro' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 5.5v15"/>',
        'livro-aberto' => '<path d="M12 6.5c-1.6-1.4-4-2-8-2v14c4 0 6.4.6 8 2 1.6-1.4 4-2 8-2v-14c-4 0-6.4.6-8 2z"/><path d="M12 6.5v14"/>',
        'ebook' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 10.5h6M9 14h4"/>',
        'copiar' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
        'envelope' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 7l9 6.5L21 7"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.3 1.3"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.3-1.3"/>',
        'pdf' => '<path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9 13h1.5a1.5 1.5 0 0 1 0 3H9zM9 13v5"/>',
        'citar' => '<path d="M7.5 7.5a3.5 3.5 0 0 0-3.5 3.5v5h5v-5h-3a2 2 0 0 1 1.5-1.9zM17.5 7.5a3.5 3.5 0 0 0-3.5 3.5v5h5v-5h-3a2 2 0 0 1 1.5-1.9z" fill="currentColor" stroke="none"/>',
        'compartilhar' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
        'seta-direita' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'seta-esquerda' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'chevron' => '<path d="M9.5 5.5 16 12l-6.5 6.5"/>',
        'calendario' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3.5v3M16 3.5v3"/>',
        'paginas' => '<path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9 12h6M9 16h6"/>',
        'formato' => '<rect x="3" y="5" width="12" height="14" rx="1.5"/><path d="M15 8h3.5a1.5 1.5 0 0 1 1.5 1.5V19M7 9h4M7 12.5h4"/>',
        'area' => '<path d="M4 5.5h7l2 2h7v11H4z"/>',
        'idioma' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/>',
        'isbn' => '<path d="M4 6v12M8 6v12M11 6v12M15 6v12M20 6v12M17.5 6v12"/>',
        'doi' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.3 1.3"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.3-1.3"/>',
        'pessoa' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.6 3.1-5.5 7-5.5s7 1.9 7 5.5"/>',
        'predio' => '<path d="M4 21V4h10v17"/><path d="M14 9h6v12"/><path d="M7 7.5h1.5M7 11h1.5M7 14.5h1.5M11 7.5h1.5M11 11h1.5M11 14.5h1.5M17 12.5h1M17 16h1"/>',
        'tag' => '<path d="M3.5 12.5v-8a1 1 0 0 1 1-1h8l8 8-9 9z"/><circle cx="8" cy="8" r="1.3"/>',
        'olho' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.8"/>',
    ];

    if (isset($cheios[$nome])) {
        printf('<svg class="doi-icone doi-icone--%s" width="%d" height="%d" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%s</svg>', esc_attr($nome), $tamanho, $tamanho, $cheios[$nome]);

        return;
    }

    printf(
        '<svg class="doi-icone doi-icone--%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        esc_attr($nome),
        $tamanho,
        $tamanho,
        $tracos[$nome] ?? $tracos['info']
    );
}

// ---------------------------------------------------------------- rótulos

function conexao_doi_rotulo_formato(string $formato): string
{
    return ['print' => 'Impresso', 'ebook' => 'E-book', 'both' => 'Impresso e e-book'][$formato] ?? ucfirst($formato);
}

function conexao_doi_rotulo_tipo(string $tipo): string
{
    return ['edited_book' => 'Obra organizada', 'monograph' => 'Monografia', 'reference' => 'Obra de referência', 'other' => 'Livro'][$tipo] ?? 'Livro';
}

function conexao_doi_nome_idioma(string $codigo): string
{
    $nomes = ['pt' => 'Português', 'en' => 'Inglês', 'es' => 'Espanhol', 'fr' => 'Francês', 'it' => 'Italiano', 'de' => 'Alemão'];

    return $nomes[strtolower(substr($codigo, 0, 2))] ?? strtoupper($codigo);
}

function conexao_doi_nome_pais(string $codigo): string
{
    $nomes = [
        'BR' => 'Brasil', 'PT' => 'Portugal', 'US' => 'Estados Unidos', 'AR' => 'Argentina', 'ES' => 'Espanha', 'FR' => 'França',
        'IT' => 'Itália', 'DE' => 'Alemanha', 'GB' => 'Reino Unido', 'UK' => 'Reino Unido', 'MX' => 'México', 'CL' => 'Chile',
        'CO' => 'Colômbia', 'UY' => 'Uruguai', 'PY' => 'Paraguai', 'CA' => 'Canadá', 'PE' => 'Peru', 'BO' => 'Bolívia',
    ];

    return $nomes[strtoupper($codigo)] ?? strtoupper($codigo);
}

/** "15/03/2026" a partir do ISO; vazio se não houver data. */
function conexao_doi_data_br(?string $iso): string
{
    if (! $iso || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
        return '';
    }

    return $m[3].'/'.$m[2].'/'.$m[1];
}

function conexao_doi_edicao_texto(array $livro): string
{
    $n = (int) ($livro['edition_number'] ?? 0);

    return $n > 0 ? $n.'ª edição' : '';
}

// ---------------------------------------------------------------- pessoas

/** "Universidade Federal de Goiás, Goiânia, GO, Brasil". */
function conexao_doi_afiliacao_texto(?array $afiliacao): string
{
    if (! $afiliacao || empty($afiliacao['name'])) {
        return '';
    }

    $partes = [(string) $afiliacao['name']];

    if (! empty($afiliacao['city'])) {
        $partes[] = (string) $afiliacao['city'];
    }

    if (! empty($afiliacao['state'])) {
        $partes[] = (string) $afiliacao['state'];
    }

    if (! empty($afiliacao['country'])) {
        $partes[] = conexao_doi_nome_pais((string) $afiliacao['country']);
    }

    return implode(', ', $partes);
}

/**
 * Afiliações numeradas na ordem em que aparecem: [lista de textos,
 * mapa índice-da-pessoa → número]. Pessoa sem afiliação fica sem número.
 */
function conexao_doi_afiliacoes_numeradas(array $pessoas): array
{
    $textos = [];
    $numeros = [];

    foreach ($pessoas as $i => $pessoa) {
        $texto = conexao_doi_afiliacao_texto($pessoa['affiliation'] ?? null);

        if ($texto === '') {
            continue;
        }

        $posicao = array_search($texto, $textos, true);

        if ($posicao === false) {
            $textos[] = $texto;
            $posicao = count($textos) - 1;
        }

        $numeros[$i] = $posicao + 1;
    }

    return [$textos, $numeros];
}

function conexao_doi_pessoa_termo(array $pessoa): ?WP_Term
{
    return conexao_doi_termo_por_hub_id((int) ($pessoa['hub_person_id'] ?? 0));
}

/** Endereço da página do autor no site, quando o termo existe. */
function conexao_doi_pessoa_url(array $pessoa): string
{
    $termo = conexao_doi_pessoa_termo($pessoa);
    $link = $termo ? get_term_link($termo) : null;

    return (is_string($link)) ? $link : '';
}

/** Foto do autor (cadastro do tema) ou as iniciais. */
function conexao_doi_avatar(array $pessoa): string
{
    $termo = conexao_doi_pessoa_termo($pessoa);

    if ($termo && function_exists('conexao_foto_autor')) {
        return conexao_foto_autor($termo, 48);
    }

    $partes = preg_split('/\s+/', trim(remove_accents((string) $pessoa['name']))) ?: [];
    $partes = array_values(array_filter($partes, static fn ($p) => mb_strlen($p) > 2 || count($partes) === 1));
    $iniciais = strtoupper(substr($partes[0] ?? 'A', 0, 1).(count($partes) > 1 ? substr(end($partes), 0, 1) : ''));

    return sprintf('<span class="autor-card__iniciais" aria-hidden="true">%s</span>', esc_html($iniciais));
}

/** Selo do ORCID com link; nada quando a pessoa não tem ORCID. */
function conexao_doi_orcid_html(array $pessoa, bool $com_texto = false): string
{
    $orcid = trim((string) ($pessoa['orcid'] ?? ''));

    if ($orcid === '') {
        return '';
    }

    $url = 'https://orcid.org/'.$orcid;
    $icone = '<svg class="doi-icone doi-icone--orcid" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="12" fill="#A6CE39"/><path fill="#fff" d="M7.4 6.7a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8zM6.7 9.4h1.4v7.9H6.7zM10 9.4h3.4c3.2 0 4.6 2.3 4.6 3.95 0 2-1.6 3.95-4.6 3.95H10zm1.4 1.3v5.3h1.9c2.4 0 3.2-1.6 3.2-2.65 0-1.4-.9-2.65-3.3-2.65z"/></svg>';

    return sprintf(
        '<a class="doi-orcid%s" href="%s" target="_blank" rel="noopener noreferrer" aria-label="ORCID de %s: %s">%s%s</a>',
        $com_texto ? ' doi-orcid--texto' : '',
        esc_url($url),
        esc_attr((string) $pessoa['name']),
        esc_attr($orcid),
        $icone,
        $com_texto ? '<span>'.esc_html($url).'</span>' : ''
    );
}

/**
 * Nomes em linha ("Ana Rocha¹ [ORCID], Maria Silva¹") e, abaixo, as
 * afiliações numeradas. É o detalhe pedido pelo cliente para o capítulo.
 */
function conexao_doi_pessoas_inline(array $pessoas, bool $com_afiliacoes = true): void
{
    if (! $pessoas) {
        return;
    }

    [$afiliacoes, $numeros] = conexao_doi_afiliacoes_numeradas($pessoas);
    ?>
    <ul class="doi-autores-linha">
        <?php foreach ($pessoas as $i => $pessoa) : ?>
            <li>
                <?php $url = conexao_doi_pessoa_url($pessoa); ?>
                <?php if ($url) : ?>
                    <a class="doi-autores-linha__nome" href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) $pessoa['name']); ?></a>
                <?php else : ?>
                    <span class="doi-autores-linha__nome"><?php echo esc_html((string) $pessoa['name']); ?></span>
                <?php endif; ?>
                <?php if ($com_afiliacoes && isset($numeros[$i])) : ?>
                    <sup class="doi-autores-linha__num"><a href="#doi-afiliacao-<?php echo (int) $numeros[$i]; ?>" aria-label="Afiliação <?php echo (int) $numeros[$i]; ?>"><?php echo (int) $numeros[$i]; ?></a></sup>
                <?php endif; ?>
                <?php echo conexao_doi_orcid_html($pessoa); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($com_afiliacoes && $afiliacoes) : ?>
        <ol class="doi-afiliacoes" aria-label="Afiliações">
            <?php foreach ($afiliacoes as $n => $texto) : ?>
                <li id="doi-afiliacao-<?php echo $n + 1; ?>"><sup><?php echo $n + 1; ?></sup> <?php echo esc_html($texto); ?></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php
}

/** Lista em cartões: avatar, nome, ORCID e afiliação (como no mockup). */
function conexao_doi_pessoas_cards(array $pessoas, string $titulo_nivel = 'h3'): void
{
    if (! $pessoas) {
        echo '<p class="doi-vazio">Sem autores informados.</p>';

        return;
    }

    $nivel = in_array($titulo_nivel, ['h2', 'h3', 'h4'], true) ? $titulo_nivel : 'h3';
    ?>
    <ul class="doi-pessoas">
        <?php foreach ($pessoas as $pessoa) : ?>
            <?php $url = conexao_doi_pessoa_url($pessoa); ?>
            <li class="doi-pessoa">
                <span class="doi-pessoa__foto"><?php echo conexao_doi_avatar($pessoa); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div class="doi-pessoa__texto">
                    <<?php echo $nivel; ?> class="doi-pessoa__nome">
                        <?php if ($url) : ?>
                            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) $pessoa['name']); ?></a>
                        <?php else : ?>
                            <?php echo esc_html((string) $pessoa['name']); ?>
                        <?php endif; ?>
                    </<?php echo $nivel; ?>>
                    <?php echo conexao_doi_orcid_html($pessoa, true); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
                <p class="doi-pessoa__afiliacao"><?php echo esc_html(conexao_doi_afiliacao_texto($pessoa['affiliation'] ?? null)); ?></p>
                <?php if ($url) : ?>
                    <a class="doi-pessoa__ir" href="<?php echo esc_url($url); ?>" aria-label="Página de <?php echo esc_attr((string) $pessoa['name']); ?>"><?php conexao_doi_icone('chevron', 18); ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/** Nomes separados por " • " (lateral do capítulo). */
function conexao_doi_nomes_pontuados(array $pessoas): string
{
    return implode(' <span aria-hidden="true">•</span> ', array_map(static fn ($p) => esc_html((string) $p['name']), $pessoas));
}

function conexao_doi_organizadores(array $livro): array
{
    $todos = conexao_doi_lista($livro['contributors'] ?? []);
    $editores = array_values(array_filter($todos, static fn ($c) => ($c['role'] ?? '') === 'editor'));

    return $editores ?: $todos;
}

function conexao_doi_rotulo_responsaveis(array $livro): string
{
    $todos = conexao_doi_lista($livro['contributors'] ?? []);
    $editores = array_filter($todos, static fn ($c) => ($c['role'] ?? '') === 'editor');

    if ($editores) {
        return count($editores) > 1 ? 'Organizadores' : 'Organizador';
    }

    return count($todos) > 1 ? 'Autores' : 'Autor';
}

// ---------------------------------------------------------------- botões

/** Botão que copia um texto (JS); o texto fica no data-copiar. */
function conexao_doi_botao_copiar(string $texto, string $rotulo = 'Copiar', string $classe = 'doi-copiar', bool $so_icone = false): void
{
    printf(
        '<button class="%s" type="button" data-copiar="%s" aria-label="%s">%s%s<span class="doi-copiar__feito" aria-live="polite"></span></button>',
        esc_attr($classe),
        esc_attr($texto),
        esc_attr($rotulo),
        conexao_doi_icone_str('copiar', 16),
        $so_icone ? '' : '<span class="doi-copiar__rotulo">'.esc_html($rotulo).'</span>'
    );
}

function conexao_doi_icone_str(string $nome, int $tamanho = 20): string
{
    ob_start();
    conexao_doi_icone($nome, $tamanho);

    return (string) ob_get_clean();
}

/** DOI com link para o resolvedor e botão de copiar. */
function conexao_doi_doi_html(string $doi, string $classe = 'doi-doi'): void
{
    if ($doi === '') {
        return;
    }
    ?>
    <span class="<?php echo esc_attr($classe); ?>">
        <a href="<?php echo esc_url('https://doi.org/'.$doi); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($doi); ?></a>
        <?php conexao_doi_botao_copiar('https://doi.org/'.$doi, 'Copiar DOI', 'doi-copiar doi-copiar--icone', true); ?>
    </span>
    <?php
}

/** Ícones de compartilhar: LinkedIn, X, Facebook, WhatsApp, e-mail, copiar link. */
function conexao_doi_compartilhar(string $url, string $titulo): void
{
    $u = rawurlencode($url);
    $t = rawurlencode($titulo);
    $redes = [
        ['LinkedIn', 'linkedin', 'https://www.linkedin.com/sharing/share-offsite/?url='.$u],
        ['X', 'x', 'https://twitter.com/intent/tweet?url='.$u.'&text='.$t],
        ['Facebook', 'facebook', 'https://www.facebook.com/sharer/sharer.php?u='.$u],
        ['WhatsApp', 'whatsapp', 'https://wa.me/?text='.$t.'%20'.$u],
        ['E-mail', 'envelope', 'mailto:?subject='.$t.'&body='.$u],
    ];
    ?>
    <ul class="doi-compartilhar">
        <?php foreach ($redes as [$nome, $icone, $link]) : ?>
            <li>
                <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no <?php echo esc_attr($nome); ?>" title="<?php echo esc_attr($nome); ?>">
                    <?php conexao_doi_icone($icone, 18); ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li>
            <?php conexao_doi_botao_copiar($url, 'Copiar link', 'doi-copiar doi-copiar--redondo', true); ?>
        </li>
    </ul>
    <?php
}

/** Caixa "Como citar": abas ABNT/APA/Vancouver/BibTeX/RIS com botão copiar. */
function conexao_doi_bloco_citacoes(array $citacoes, string $prefixo): void
{
    ?>
    <div class="doi-citacoes" data-doi-abas>
        <div class="doi-citacoes__abas" role="tablist" aria-label="Formato da citação">
            <?php $i = 0; foreach ($citacoes as $chave => $citacao) : ?>
                <button class="doi-citacoes__aba<?php echo $i === 0 ? ' e-ativa' : ''; ?>" type="button" role="tab"
                        id="<?php echo esc_attr($prefixo.'-aba-'.$chave); ?>" aria-controls="<?php echo esc_attr($prefixo.'-'.$chave); ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" tabindex="<?php echo $i === 0 ? '0' : '-1'; ?>">
                    <?php echo esc_html($citacao['rotulo']); ?>
                </button>
            <?php $i++; endforeach; ?>
        </div>

        <?php $i = 0; foreach ($citacoes as $chave => $citacao) : ?>
            <div class="doi-citacoes__painel" id="<?php echo esc_attr($prefixo.'-'.$chave); ?>" role="tabpanel"
                 aria-labelledby="<?php echo esc_attr($prefixo.'-aba-'.$chave); ?>" tabindex="0"<?php echo $i > 0 ? ' hidden' : ''; ?>>
                <?php if ($citacao['bloco']) : ?>
                    <pre class="doi-citacoes__texto doi-citacoes__texto--bloco"><?php echo $citacao['html']; // phpcs:ignore WordPress.Security.EscapeOutput ?></pre>
                <?php else : ?>
                    <p class="doi-citacoes__texto"><?php echo wp_kses($citacao['html'], ['b' => [], 'i' => []]); ?></p>
                <?php endif; ?>
                <?php conexao_doi_botao_copiar($citacao['texto'], 'Copiar citação', 'btn btn--contorno btn--bloco doi-copiar'); ?>
            </div>
        <?php $i++; endforeach; ?>
    </div>
    <?php
}

// ---------------------------------------------------------------- sumário

/** Endereço do capítulo a partir do slug do livro e da sequência. */
function conexao_doi_url_capitulo(WP_Post $livro, array $capitulo): string
{
    return home_url('/livro/'.$livro->post_name.'/capitulo/'.conexao_doi_numero_url((int) ($capitulo['sequence'] ?? 0)).'/');
}

/**
 * Sumário em seções, pela ordem de `toc[]`. Capítulo fora do toc entra numa
 * seção sem nome no fim. Cada item: ['section' => nome, 'capitulos' => [...]].
 */
function conexao_doi_sumario(array $dados): array
{
    $capitulos = [];

    foreach ($dados['chapters'] ?? [] as $capitulo) {
        if (is_array($capitulo) && ! empty($capitulo['hub_id'])) {
            $capitulos[(int) $capitulo['hub_id']] = $capitulo;
        }
    }

    uasort($capitulos, static fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));

    $secoes = [];
    $usados = [];

    foreach ((array) ($dados['book']['toc'] ?? []) as $item) {
        if (! is_array($item)) {
            continue;
        }

        $lista = [];

        foreach ((array) ($item['chapters'] ?? []) as $hub_id) {
            if (isset($capitulos[(int) $hub_id])) {
                $lista[] = $capitulos[(int) $hub_id];
                $usados[(int) $hub_id] = true;
            }
        }

        if ($lista) {
            $secoes[] = ['section' => (string) ($item['section'] ?? ''), 'capitulos' => $lista];
        }
    }

    // o que sobrou: agrupado pelo campo `section` do próprio capítulo
    $restantes = [];

    foreach ($capitulos as $hub_id => $capitulo) {
        if (! isset($usados[$hub_id])) {
            $restantes[(string) ($capitulo['section'] ?? '')][] = $capitulo;
        }
    }

    foreach ($restantes as $nome => $lista) {
        $secoes[] = ['section' => $nome, 'capitulos' => $lista];
    }

    return $secoes;
}

/** Livros da mesma área (outras publicações com DOI), mais recentes primeiro. */
function conexao_doi_relacionados(int $livro_id, int $quantos = 4): array
{
    $area = (string) get_post_meta($livro_id, CONEXAO_DOI_META_AREA, true);

    if ($area === '') {
        return [];
    }

    return get_posts([
        'post_type' => 'publicacao',
        'post_status' => 'publish',
        'numberposts' => $quantos,
        'exclude' => [$livro_id],
        'meta_key' => CONEXAO_DOI_META_AREA,
        'meta_value' => $area,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

/** Lista de referências, com link quando há DOI. */
function conexao_doi_referencias(array $referencias): void
{
    $referencias = array_values(array_filter($referencias, static fn ($r) => is_array($r) && ! empty($r['text'])));

    if (! $referencias) {
        echo '<p class="doi-vazio">Sem referências informadas.</p>';

        return;
    }

    echo '<ol class="doi-referencias">';

    foreach ($referencias as $referencia) {
        echo '<li>'.esc_html((string) $referencia['text']);

        if (! empty($referencia['doi'])) {
            printf(' <a href="%s" target="_blank" rel="noopener noreferrer">https://doi.org/%s</a>', esc_url('https://doi.org/'.$referencia['doi']), esc_html((string) $referencia['doi']));
        }

        echo '</li>';
    }

    echo '</ol>';
}

/** Palavras-chave em etiquetas. */
function conexao_doi_palavras_chave(array $palavras, string $titulo = 'Palavras-chave'): void
{
    $palavras = array_values(array_filter(array_map('strval', $palavras), static fn ($p) => trim($p) !== ''));

    if (! $palavras) {
        return;
    }
    ?>
    <div class="doi-palavras">
        <h2 class="doi-palavras__titulo"><?php echo esc_html($titulo); ?></h2>
        <ul class="doi-palavras__lista">
            <?php foreach ($palavras as $palavra) : ?>
                <li><?php echo esc_html($palavra); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/** Botões das abas principais da página (tablist) a partir de [chave => rótulo]. */
function conexao_doi_abas_botoes(array $abas, string $prefixo): void
{
    echo '<div class="doi-abas__botoes" role="tablist" aria-label="Seções da página">';
    $i = 0;

    foreach ($abas as $chave => $rotulo) {
        printf(
            '<button class="doi-abas__botao%s" type="button" role="tab" id="%s" aria-controls="%s" aria-selected="%s" tabindex="%s">%s</button>',
            $i === 0 ? ' e-ativa' : '',
            esc_attr($prefixo.'-aba-'.$chave),
            esc_attr($chave),
            $i === 0 ? 'true' : 'false',
            $i === 0 ? '0' : '-1',
            esc_html($rotulo)
        );
        $i++;
    }

    echo '</div>';
}

/** Abertura de um painel de aba (o fechamento é um </section>). */
function conexao_doi_aba_abre(string $chave, string $prefixo, bool $ativa): void
{
    printf(
        '<section class="doi-abas__painel" id="%s" role="tabpanel" aria-labelledby="%s" tabindex="0"%s>',
        esc_attr($chave),
        esc_attr($prefixo.'-aba-'.$chave),
        $ativa ? '' : ' hidden'
    );
}
