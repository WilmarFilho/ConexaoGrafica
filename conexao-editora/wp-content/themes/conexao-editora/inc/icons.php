<?php
/**
 * Ícones em SVG usados pelo tema. Todos herdam a cor do texto (currentColor).
 */

if (! defined('ABSPATH')) {
    exit;
}

function conexao_icon(string $nome, int $tamanho = 20): string
{
    $svgs = [
        'caminhao' => '<path d="M3 6h11v9H3z"/><path d="M14 9h3.5L21 12v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'pin' => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'lupa' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'usuario' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.6 3.1-5.5 7-5.5s7 1.9 7 5.5"/>',
        'carrinho' => '<circle cx="9.5" cy="19" r="1.6"/><circle cx="17" cy="19" r="1.6"/><path d="M3 4h2.2l2.4 11h11l2-8H6.2"/>',
        'coracao' => '<path d="M12 20s-7.5-4.6-7.5-9.4A4.1 4.1 0 0 1 12 8a4.1 4.1 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>',
        'seta-baixo' => '<path d="M6 9.5l6 5 6-5"/>',
        'calendario' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3.5v3M16 3.5v3"/>',
        'mais' => '<path d="M12 5.5v13M5.5 12h13"/>',
        'envelope' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 7l9 6.5L21 7"/>',
        'predio' => '<path d="M4 21V4h10v17"/><path d="M14 9h6v12"/><path d="M7 7.5h1.5M7 11h1.5M7 14.5h1.5M11 7.5h1.5M11 11h1.5M11 14.5h1.5M17 12.5h1M17 16h1"/><path d="M9 21v-3.5h2.5V21"/>',
        'check' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.2l2.4 2.4 4.6-4.9"/>',
        'cadeado' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.2 10.5V8a3.8 3.8 0 0 1 7.6 0v2.5"/>',
        'topo' => '<path d="M6 14l6-6 6 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'fechar' => '<path d="M6 6l12 12M18 6L6 18"/>',
        // categorias
        'biografia' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.6 3.1-5.5 7-5.5s7 1.9 7 5.5"/>',
        'cronica' => '<path d="M16.8 3.9l3.3 3.3L9.4 17.9l-4.3 1 1-4.3z"/><path d="M14.6 6.1l3.3 3.3"/>',
        'direito' => '<path d="M12 4v16M6.5 20h11M4 8.5h16M12 4.5L4 8.5M12 4.5l8 4"/><path d="M4 8.5L1.8 14h4.4zM20 8.5L17.8 14h4.4z"/>',
        'familia' => '<circle cx="7" cy="7" r="2.2"/><circle cx="16.5" cy="6.5" r="2.5"/><circle cx="11.8" cy="13.5" r="1.8"/><path d="M3.5 20v-4.5a3.5 3.5 0 0 1 7 0V20M13.5 20v-5a3 3 0 0 1 6 0v5M9.4 20v-2.2a2.4 2.4 0 0 1 4.8 0V20"/>',
        'historia' => '<path d="M4 12a8 8 0 1 1 2.6 5.9"/><path d="M4 7.5V12h4.5"/><path d="M12 8.5V12l2.6 1.6"/>',
        'medicina' => '<path d="M3 12.5h3.5L9 7l3 10.5 2.4-5h6.6"/>',
        'literatura' => '<path d="M6 4h12v16l-6-3.2L6 20z"/>',
        'religiao' => '<path d="M12 4v16M6.5 9.5h11"/>',
        'livro' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 5.5v15"/>',
    ];

    if (! isset($svgs[$nome])) {
        return '';
    }

    return sprintf(
        '<svg class="icone icone--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
        esc_attr($nome),
        $tamanho,
        $svgs[$nome]
    );
}

function conexao_the_icon(string $nome, int $tamanho = 20): void
{
    echo conexao_icon($nome, $tamanho); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Ícone da categoria enviado pela editora (assets/img/categorias/<slug>.svg).
 * A cor vem do CSS, então o ícone acompanha o cartão quando ele fica azul.
 */
function conexao_svg_categoria(string $slug): string
{
    $caminho = get_template_directory().'/assets/img/categorias/'.sanitize_file_name($slug).'.svg';

    if (! file_exists($caminho)) {
        return '';
    }

    $svg = (string) file_get_contents($caminho);

    return str_replace('<svg ', '<svg class="icone icone--categoria" aria-hidden="true" focusable="false" ', $svg);
}

/**
 * Ícone de cada categoria de livro, pelo slug. Sem correspondência, usa o livro.
 */
function conexao_icone_categoria(string $slug): string
{
    $mapa = [
        'biografia' => 'biografia',
        'cronica' => 'cronica',
        'cronicas' => 'cronica',
        'direito' => 'direito',
        'educacao-familiar' => 'familia',
        'historia' => 'historia',
        'medicina' => 'medicina',
        'saude' => 'medicina',
        'literatura' => 'literatura',
        'religiao' => 'religiao',
    ];

    return $mapa[$slug] ?? 'livro';
}
