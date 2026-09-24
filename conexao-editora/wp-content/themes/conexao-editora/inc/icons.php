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
        'coracao' => '<path d="M12 20.4S3.8 15.4 3.8 9.9A4.6 4.6 0 0 1 12 7.2a4.6 4.6 0 0 1 8.2 2.7c0 5.5-8.2 10.5-8.2 10.5z" fill="currentColor" stroke="none"/>',
        'seta-baixo' => '<path d="M6 9.5l6 5 6-5"/>',
        'seta-esquerda' => '<path d="M14.5 5.5L8 12l6.5 6.5"/>',
        'seta-direita' => '<path d="M9.5 5.5L16 12l-6.5 6.5"/>',
        'calendario' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3.5v3M16 3.5v3"/>',
        'mais' => '<path d="M12 5.5v13M5.5 12h13"/>',
        'envelope' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 7l9 6.5L21 7"/>',
        'predio' => '<path d="M4 21V4h10v17"/><path d="M14 9h6v12"/><path d="M7 7.5h1.5M7 11h1.5M7 14.5h1.5M11 7.5h1.5M11 11h1.5M11 14.5h1.5M17 12.5h1M17 16h1"/><path d="M9 21v-3.5h2.5V21"/>',
        'check' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.2l2.4 2.4 4.6-4.9"/>',
        'cadeado' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.2 10.5V8a3.8 3.8 0 0 1 7.6 0v2.5"/>',
        'topo' => '<path d="M6 14l6-6 6 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'funil' => '<path d="M3.5 5.5h17l-6.6 7.6V20l-3.8-2.2v-4.7z"/>',
        'grade' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'lista' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1.2" fill="currentColor" stroke="none"/>',
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
        'compartilhar' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
        'whatsapp' => '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2z"/><path d="M8.6 8.2c.2-.5.4-.5.6-.5h.5c.2 0 .4 0 .6.4l.8 1.9c0 .2 0 .3-.1.4l-.4.5c-.1.2-.2.3-.1.5.5 1 1.6 1.9 2.8 2.4.2.1.4.1.5-.1l.6-.7c.2-.2.3-.2.5-.1l1.7.8c.2.1.3.2.3.3v.5c-.1.4-.5.9-1.2 1.1-.6.2-1.3.1-2-.1a9.6 9.6 0 0 1-5.5-5.1c-.3-.7-.4-1.4-.2-2.1z"/>',
        'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'lixeira' => '<path d="M4 6.5h16M9.5 6.5V4.8c0-.7.6-1.3 1.3-1.3h2.4c.7 0 1.3.6 1.3 1.3v1.7"/><path d="M6.5 6.5l.9 12.2c.1 1 .9 1.8 1.9 1.8h5.4c1 0 1.8-.8 1.9-1.8l.9-12.2"/><path d="M10.5 10.5v6M13.5 10.5v6"/>',
        'cupom' => '<path d="M3 8.5A1.5 1.5 0 0 1 4.5 7h15A1.5 1.5 0 0 1 21 8.5v2a2 2 0 0 0 0 3.9v2a1.5 1.5 0 0 1-1.5 1.6h-15A1.5 1.5 0 0 1 3 16.4v-2a2 2 0 0 0 0-3.9z"/><path d="M14 9.5v1.2M14 13.3v1.2"/>',
        // pagamento
        'cartao' => '<rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19M6 14.5h4"/>',
        'pix' => '<path d="M12 3.2l3.6 3.6-3.6 3.6-3.6-3.6z"/><path d="M12 13.6l3.6 3.6-3.6 3.6-3.6-3.6z"/><path d="M6.8 8.4L3.2 12l3.6 3.6L10.4 12z"/><path d="M17.2 8.4L20.8 12l-3.6 3.6L13.6 12z"/>',
        'boleto' => '<path d="M4 5.5v13M7 5.5v13M10 5.5v13M13.5 5.5v13M16.5 5.5v13M20 5.5v13"/>',
        'google' => '<path d="M21.6 12.2c0-.7-.1-1.3-.2-1.9H12v3.6h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.2z" fill="#4285F4" stroke="none"/><path d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.8-1.7-5.6-4.1H3.1v2.6A10 10 0 0 0 12 22z" fill="#34A853" stroke="none"/><path d="M6.4 14c-.2-.6-.3-1.3-.3-2s.1-1.4.3-2V7.4H3.1a10 10 0 0 0 0 9.2z" fill="#FBBC05" stroke="none"/><path d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.8-2.8A10 10 0 0 0 3.1 7.4L6.4 10c.8-2.4 3-4.1 5.6-4.1z" fill="#EA4335" stroke="none"/>',
        // redes sociais
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><circle cx="17" cy="7" r="1.1" fill="currentColor" stroke="none"/>',
        'facebook' => '<path d="M14.5 21v-8h2.6l.5-3h-3.1V8.2c0-.9.3-1.5 1.6-1.5h1.6V4c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1V10H9v3h2.3v8z" fill="currentColor" stroke="none"/>',
        'youtube' => '<path d="M21.6 8.2a2.5 2.5 0 0 0-1.8-1.8C18.2 6 12 6 12 6s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 8.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 3.8 2.5 2.5 0 0 0 1.8 1.8C5.8 18 12 18 12 18s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-3.8z" fill="currentColor" stroke="none"/><path d="M10.2 14.6V9.4L14.6 12z" fill="#fff" stroke="none"/>',
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

/**
 * Ícones desenhados pela editora, com proporção própria.
 * A altura manda; a largura acompanha a proporção original.
 */
function conexao_icone_arte(string $nome, int $altura = 20): string
{
    $artes = [
    'coracao' => ['0 0 31 27', '<path d="M27.9765 1.84328C24.6603 -0.966783 19.7284 -0.461332 16.6845 2.66163L15.4924 3.88314L14.3003 2.66163C11.2624 -0.461332 6.32448 -0.966783 3.0083 1.84328C-0.791994 5.06854 -0.991691 10.8572 2.40921 14.3532L14.1187 26.3757C14.8751 27.1519 16.1036 27.1519 16.86 26.3757L28.5695 14.3532C31.9765 10.8572 31.7768 5.06854 27.9765 1.84328Z" fill="currentColor"/>'],
    'cesta' => ['0 0 24 21', '<path d="M22.8972 6.53538H20.2095L16.1752 0.484837C16.0147 0.244492 15.7652 0.0776812 15.4816 0.0210995C15.198 -0.0354822 14.9035 0.0228007 14.6629 0.183127C14.4223 0.343453 14.2553 0.592689 14.1987 0.876006C14.1421 1.15932 14.2004 1.45351 14.3609 1.69386L17.5894 6.53538H6.39812L9.6299 1.69386C9.79039 1.45351 9.84873 1.15932 9.79209 0.876006C9.73545 0.592689 9.56847 0.343453 9.32787 0.183127C9.08728 0.0228007 8.79278 -0.0354822 8.50917 0.0210995C8.22556 0.0776812 7.97606 0.244492 7.81557 0.484837L3.77803 6.53538H1.09034C0.801165 6.53538 0.523832 6.65014 0.319354 6.8544C0.114875 7.05867 0 7.33571 0 7.62459C0 7.91346 0.114875 8.19051 0.319354 8.39477C0.523832 8.59904 0.801165 8.7138 1.09034 8.7138H2.18068V17.4275C2.18068 18.2941 2.52531 19.1252 3.13874 19.738C3.75218 20.3508 4.58418 20.6951 5.45171 20.6951H18.5358C19.4033 20.6951 20.2353 20.3508 20.8488 19.738C21.4622 19.1252 21.8068 18.2941 21.8068 17.4275V8.7138H22.8972C23.1863 8.7138 23.4637 8.59904 23.6682 8.39477C23.8726 8.19051 23.9875 7.91346 23.9875 7.62459C23.9875 7.33571 23.8726 7.05867 23.6682 6.8544C23.4637 6.65014 23.1863 6.53538 22.8972 6.53538ZM19.6261 17.4275C19.6261 17.7163 19.5113 17.9934 19.3068 18.1976C19.1023 18.4019 18.825 18.5167 18.5358 18.5167H5.45171C5.16253 18.5167 4.8852 18.4019 4.68072 18.1976C4.47624 17.9934 4.36137 17.7163 4.36137 17.4275V8.7138H19.6261V17.4275Z" fill="currentColor"/> <path d="M7.63233 16.8829C7.92151 16.8829 8.19884 16.7681 8.40332 16.5639C8.6078 16.3596 8.72267 16.0826 8.72267 15.7937V11.4369C8.72267 11.148 8.6078 10.8709 8.40332 10.6667C8.19884 10.4624 7.92151 10.3477 7.63233 10.3477C7.34316 10.3477 7.06582 10.4624 6.86135 10.6667C6.65687 10.8709 6.54199 11.148 6.54199 11.4369V15.7937C6.54199 16.0826 6.65687 16.3596 6.86135 16.5639C7.06582 16.7681 7.34316 16.8829 7.63233 16.8829Z" fill="currentColor"/> <path d="M11.9937 16.8829C12.2828 16.8829 12.5602 16.7681 12.7647 16.5639C12.9691 16.3596 13.084 16.0826 13.084 15.7937V11.4369C13.084 11.148 12.9691 10.8709 12.7647 10.6667C12.5602 10.4624 12.2828 10.3477 11.9937 10.3477C11.7045 10.3477 11.4272 10.4624 11.2227 10.6667C11.0182 10.8709 10.9033 11.148 10.9033 11.4369V15.7937C10.9033 16.0826 11.0182 16.3596 11.2227 16.5639C11.4272 16.7681 11.7045 16.8829 11.9937 16.8829Z" fill="currentColor"/> <path d="M16.355 16.8829C16.6442 16.8829 16.9215 16.7681 17.126 16.5639C17.3305 16.3596 17.4453 16.0826 17.4453 15.7937V11.4369C17.4453 11.148 17.3305 10.8709 17.126 10.6667C16.9215 10.4624 16.6442 10.3477 16.355 10.3477C16.0658 10.3477 15.7885 10.4624 15.584 10.6667C15.3795 10.8709 15.2646 11.148 15.2646 11.4369V15.7937C15.2646 16.0826 15.3795 16.3596 15.584 16.5639C15.7885 16.7681 16.0658 16.8829 16.355 16.8829Z" fill="currentColor"/>'],
    // contato: SVGs do layout, com a cor vindo do CSS
    'zap' => ['0 0 29 28', '<path fill-rule="evenodd" clip-rule="evenodd" d="M3.37212 14C3.37212 7.74076 8.50523 2.66666 14.8372 2.66666C21.1692 2.66666 26.3023 7.74076 26.3023 14C26.3023 20.2592 21.1692 25.3333 14.8372 25.3333C12.5817 25.3333 10.4822 24.6909 8.71077 23.5814C8.3811 23.375 7.97713 23.3198 7.60318 23.4301L3.71968 24.5758L5.18187 21.1937C5.3547 20.794 5.31953 20.3361 5.08764 19.9668C4.00025 18.2346 3.37212 16.1918 3.37212 14ZM14.8372 0C7.01534 0 0.674448 6.268 0.674448 14C0.674448 16.4508 1.31264 18.7577 2.43418 20.7636L0.108543 26.1426C-0.0970495 26.6182 -0.00591947 27.1683 0.342364 27.5542C0.690647 27.9402 1.23327 28.0922 1.73459 27.9442L7.80358 26.1539C9.87679 27.3286 12.2794 27.9999 14.8372 27.9999C22.6591 27.9999 29 21.732 29 14C29 6.268 22.6591 0 14.8372 0ZM17.9294 16.9098L16.1568 18.1441C15.3266 17.6766 14.4087 17.0241 13.488 16.114C12.5308 15.1678 11.8212 14.191 11.298 13.295L12.4245 12.3498C12.908 11.9442 13.0403 11.2604 12.7425 10.707L11.3071 8.04036C11.1138 7.68128 10.7653 7.42905 10.3606 7.35537C9.95595 7.2817 9.5394 7.39465 9.22938 7.6621L8.80381 8.02925C7.7804 8.91217 7.17514 10.363 7.67679 11.8321C8.19686 13.355 9.30675 15.752 11.5805 17.9996C14.0267 20.4177 16.5115 21.37 17.9646 21.7398C19.1354 22.0377 20.2433 21.6384 21.0241 21.0096L21.8219 20.3669C22.1632 20.0921 22.3473 19.6698 22.3152 19.2358C22.2829 18.8018 22.0385 18.4106 21.6603 18.1878L19.3966 16.8545C18.9393 16.5853 18.3647 16.6069 17.9294 16.9098Z" fill="currentColor"/>'],
    'email' => ['0 0 26 21', '<path d="M2.73047 3.96051L10.3575 9.68069C11.6697 10.6648 13.4738 10.6648 14.786 9.68069L22.413 3.96045" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M21.1825 1.5H3.96032C2.60152 1.5 1.5 2.60152 1.5 3.96032V16.2619C1.5 17.6207 2.60152 18.7222 3.96032 18.7222H21.1825C22.5413 18.7222 23.6429 17.6207 23.6429 16.2619V3.96032C23.6429 2.60152 22.5413 1.5 21.1825 1.5Z" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'],
    'horario' => ['0 0 25 25', '<path d="M12.5 6.38889V12.5L15.5556 10.6667M23.5 12.5C23.5 18.5752 18.5752 23.5 12.5 23.5C6.42487 23.5 1.5 18.5752 1.5 12.5C1.5 6.42487 6.42487 1.5 12.5 1.5C18.5752 1.5 23.5 6.42487 23.5 12.5Z" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'],
    'estrela' => ['0 0 15 13', '<path d="M6.50065 0.451868L4.72305 3.81056L0.745898 4.35089C0.0326795 4.44729 -0.253153 5.26667 0.264067 5.73597L3.14144 8.34885L2.46089 12.0399C2.33839 12.707 3.09244 13.2068 3.724 12.8947L7.28192 11.152L10.8399 12.8947C11.4714 13.2042 12.2255 12.707 12.103 12.0399L11.4224 8.34885L14.2998 5.73597C14.817 5.26667 14.5312 4.44729 13.818 4.35089L9.8408 3.81056L8.0632 0.451868C7.7447 -0.146811 6.82187 -0.154422 6.50065 0.451868Z" fill="currentColor"/>'],
    ];

    if (! isset($artes[$nome])) {
        return '';
    }

    [$caixa, $corpo] = $artes[$nome];
    [, , $largura_caixa, $altura_caixa] = array_map('floatval', explode(' ', $caixa));
    $largura = (int) round($altura * ($largura_caixa / $altura_caixa));

    return sprintf(
        '<svg class="icone icone--%1$s" width="%2$d" height="%3$d" viewBox="%4$s" fill="none" aria-hidden="true" focusable="false">%5$s</svg>',
        esc_attr($nome),
        $largura,
        $altura,
        esc_attr($caixa),
        $corpo
    );
}

function conexao_a_icone_arte(string $nome, int $altura = 20): void
{
    echo conexao_icone_arte($nome, $altura); // phpcs:ignore WordPress.Security.EscapeOutput
}

function conexao_the_icon(string $nome, int $tamanho = 20): void
{
    echo conexao_icon($nome, $tamanho); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Ícone da categoria enviado pela editora (assets/img/categorias/<slug>.svg).
 * A cor vem do CSS, então o ícone acompanha o cartão quando ele fica azul.
 */
/**
 * SVGs da central de ajuda, como vieram do layout. A cor sai do arquivo e passa
 * a vir do CSS, para o mesmo desenho servir ao atalho ativo (branco) e ao
 * quadrinho do cartão (azul escuro).
 */
/**
 * @param string[] $cores cores do arquivo que passam a sair do CSS
 */
function conexao_svg_pasta(string $pasta, string $nome, int $altura = 30, array $cores = ['#00448B', '#0197D4', '#0095D3']): string
{
    $caminho = get_template_directory().'/assets/img/'.$pasta.'/'.sanitize_file_name($nome).'.svg';

    if (! file_exists($caminho)) {
        return '';
    }

    $svg = (string) file_get_contents($caminho);
    $svg = str_ireplace($cores, 'currentColor', $svg);

    // sem width/height próprios a largura acompanha o viewBox
    $svg = preg_replace('/\s(?:width|height)="[^"]*"/i', '', $svg, 2);

    return str_replace(
        '<svg ',
        sprintf('<svg class="icone icone--%s" height="%d" aria-hidden="true" focusable="false" ', esc_attr($pasta), $altura),
        $svg
    );
}

function conexao_svg_ajuda(string $nome, int $altura = 30): string
{
    return conexao_svg_pasta('ajuda', $nome, $altura);
}

function conexao_a_svg_ajuda(string $nome, int $altura = 30): void
{
    echo conexao_svg_ajuda($nome, $altura); // phpcs:ignore WordPress.Security.EscapeOutput
}

function conexao_a_svg_editora(string $nome, int $altura = 30): void
{
    echo conexao_svg_pasta('editora', $nome, $altura); // phpcs:ignore WordPress.Security.EscapeOutput
}

function conexao_a_svg_publique(string $nome, int $altura = 30): void
{
    echo conexao_svg_pasta('publique', $nome, $altura); // phpcs:ignore WordPress.Security.EscapeOutput
}

function conexao_a_svg_carrinho(string $nome, int $altura = 20): void
{
    echo conexao_svg_pasta('carrinho', $nome, $altura, ['white', '#FFFFFF', '#FFF']); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Ícones da tela de conta. Só o cinza dos campos vira currentColor: as marcas
 * do Google e do Facebook mantêm a cor que vieram do layout.
 */
function conexao_a_svg_conta(string $nome, int $altura = 20): void
{
    echo conexao_svg_pasta('conta', $nome, $altura, ['#CCCCCC']); // phpcs:ignore WordPress.Security.EscapeOutput
}

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
