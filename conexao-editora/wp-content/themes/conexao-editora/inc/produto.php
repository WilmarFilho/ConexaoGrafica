<?php
/**
 * Ficha do livro: campos próprios no editor do produto e a lista que a página
 * do produto mostra.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Campos de livro que a editora preenche no editor do produto. */
function conexao_campos_livro(): array
{
    return [
        '_conexao_idade' => ['rotulo' => 'Idade de leitura', 'icone' => 'idade', 'dica' => 'Ex.: 12 anos e acima'],
        '_conexao_paginas' => ['rotulo' => 'N. de páginas', 'icone' => 'paginas', 'dica' => 'Só o número'],
        '_conexao_idioma' => ['rotulo' => 'Idioma', 'icone' => 'idioma', 'dica' => 'Ex.: Português-BR'],
        '_conexao_isbn10' => ['rotulo' => 'ISBN-10', 'icone' => 'isbn10', 'dica' => ''],
        '_conexao_isbn13' => ['rotulo' => 'ISBN-13', 'icone' => 'isbn13', 'dica' => ''],
    ];
}

// painel no editor do produto, junto dos dados de envio
add_action('woocommerce_product_options_shipping_product_data', function (): void {
    echo '<div class="options_group">';

    foreach (conexao_campos_livro() as $chave => $campo) {
        woocommerce_wp_text_input([
            'id' => $chave,
            'label' => $campo['rotulo'],
            'desc_tip' => (bool) $campo['dica'],
            'description' => $campo['dica'],
        ]);
    }

    echo '</div>';
});

add_action('woocommerce_process_product_meta', function (int $id): void {
    foreach (array_keys(conexao_campos_livro()) as $chave) {
        $valor = isset($_POST[$chave]) ? sanitize_text_field(wp_unslash($_POST[$chave])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ($valor === '') {
            delete_post_meta($id, $chave);
            continue;
        }

        update_post_meta($id, $chave, $valor);
    }
});

/**
 * Ficha técnica mostrada na página do produto. Só entra o que existe: dimensões
 * e data saem do próprio WooCommerce; o resto vem dos campos acima.
 *
 * @return array<int, array{icone: string, rotulo: string, valor: string}>
 */
function conexao_ficha_produto(WC_Product $produto): array
{
    $ficha = [];

    foreach (conexao_campos_livro() as $chave => $campo) {
        $valor = (string) get_post_meta($produto->get_id(), $chave, true);

        if ($chave === '_conexao_idioma' && $valor === '') {
            $valor = (string) apply_filters('conexao_idioma_padrao', 'Português-BR');
        }

        if ($valor === '') {
            continue;
        }

        if ($chave === '_conexao_paginas') {
            $valor = $valor.' páginas';
        }

        $ficha[] = ['icone' => $campo['icone'], 'rotulo' => $campo['rotulo'], 'valor' => $valor];
    }

    if ($produto->has_dimensions()) {
        $ficha[] = [
            'icone' => 'dimensoes',
            'rotulo' => 'Dimensões',
            'valor' => str_replace(' ', '', wc_format_dimensions($produto->get_dimensions(false))),
        ];
    }

    $ficha[] = [
        'icone' => 'editora',
        'rotulo' => 'Editora',
        'valor' => (string) apply_filters('conexao_nome_editora', 'Conexão Editora'),
    ];

    $publicacao = $produto->get_date_created();

    if ($publicacao) {
        $ficha[] = [
            'icone' => 'publicacao',
            'rotulo' => 'Data de publicação',
            'valor' => wp_date('j F Y', $publicacao->getTimestamp()),
        ];
    }

    // a ordem do layout: idade, páginas, idioma, dimensões, editora, data, ISBNs
    $ordem = ['idade', 'paginas', 'idioma', 'dimensoes', 'editora', 'publicacao', 'isbn10', 'isbn13'];

    usort($ficha, static fn ($a, $b) => array_search($a['icone'], $ordem, true) <=> array_search($b['icone'], $ordem, true));

    return apply_filters('conexao_ficha_produto', $ficha, $produto);
}

/**
 * Na página do livro, o seletor de formato só oferece o que dá para comprar:
 * variação existente, publicada e com preço.
 */
add_filter('woocommerce_dropdown_variation_attribute_options_args', function (array $args): array {
    if (($args['attribute'] ?? '') !== 'pa_formato' || empty($args['product'])) {
        return $args;
    }

    $disponiveis = [];

    foreach ($args['product']->get_available_variations() as $variacao) {
        $valor = $variacao['attributes']['attribute_pa_formato'] ?? '';

        if ($valor !== '') {
            $disponiveis[] = $valor;
        }
    }

    if ($disponiveis) {
        $args['options'] = array_values(array_intersect((array) $args['options'], $disponiveis));
    }

    // a ordem do layout não é a alfabética
    $ordem = apply_filters('conexao_ordem_formatos', ['impresso', 'e-book', 'impresso-e-book']);

    usort($args['options'], static function ($a, $b) use ($ordem) {
        $pa = array_search($a, $ordem, true);
        $pb = array_search($b, $ordem, true);

        return ($pa === false ? 99 : $pa) <=> ($pb === false ? 99 : $pb);
    });

    return $args;
});

/**
 * Endereço antigo do e-book (agora rascunho) leva para o produto unificado,
 * com 301, para não perder link nem posição de busca.
 */
add_action('template_redirect', function (): void {
    if (! is_404()) {
        return;
    }

    $caminho = trim((string) wp_parse_url(add_query_arg([]), PHP_URL_PATH), '/');
    $slug = $caminho ? basename($caminho) : '';

    if (! $slug) {
        return;
    }

    $antigo = get_posts([
        'name' => $slug,
        'post_type' => 'product',
        'post_status' => ['draft', 'pending', 'private', 'publish'],
        'numberposts' => 1,
        'meta_key' => '_conexao_unificado_em',
    ]);

    if (! $antigo) {
        return;
    }

    $destino = (int) get_post_meta($antigo[0]->ID, '_conexao_unificado_em', true);

    if ($destino && get_post_status($destino) === 'publish') {
        wp_safe_redirect(get_permalink($destino), 301);
        exit;
    }
});

/** Estrelas da avaliação, com as estrelas do layout. */
function conexao_estrelas_produto(WC_Product $produto): void
{
    $nota = (float) $produto->get_average_rating();
    $quantas = (int) $produto->get_review_count();

    if (! $quantas) {
        return;
    }

    echo '<span class="produto__estrelas" aria-label="'.esc_attr(sprintf('Nota %s de 5', number_format_i18n($nota, 1))).'">';

    for ($i = 1; $i <= 5; $i++) {
        conexao_a_svg_produto($i <= round($nota) ? 'estrela-cheia' : 'estrela-vazia', 16);
    }

    printf(
        '<span class="produto__avaliacoes">(%d %s)</span></span>',
        $quantas,
        $quantas === 1 ? 'avaliação' : 'avaliações'
    );
}

/** "Comprar agora": põe no carrinho e segue direto para o pagamento. */
add_filter('woocommerce_add_to_cart_redirect', function (string $url): string {
    return isset($_REQUEST['conexao_comprar']) ? wc_get_checkout_url() : $url; // phpcs:ignore WordPress.Security.NonceVerification
});

/**
 * Frete do produto para um CEP, sem depender do carrinho: monta um pacote com
 * uma unidade e pergunta às zonas de entrega do WooCommerce.
 */
add_action('wp_ajax_conexao_frete', 'conexao_calcula_frete');
add_action('wp_ajax_nopriv_conexao_frete', 'conexao_calcula_frete');

function conexao_calcula_frete(): void
{
    check_ajax_referer('conexao_frete', 'nonce');

    $id = isset($_POST['produto']) ? absint($_POST['produto']) : 0;
    $cep = isset($_POST['cep']) ? preg_replace('/\D/', '', sanitize_text_field(wp_unslash($_POST['cep']))) : '';
    $produto = $id ? wc_get_product($id) : null;

    if (! $produto || strlen($cep) !== 8) {
        wp_send_json_error(['mensagem' => 'Confira o CEP e tente de novo.']);
    }

    $pacote = [
        'contents' => [
            'conexao' => [
                'data' => $produto,
                'quantity' => 1,
                'line_total' => (float) $produto->get_price(),
                'line_subtotal' => (float) $produto->get_price(),
            ],
        ],
        'contents_cost' => (float) $produto->get_price(),
        'applied_coupons' => [],
        'user' => ['ID' => get_current_user_id()],
        'destination' => [
            'country' => 'BR',
            'state' => '',
            'postcode' => $cep,
            'city' => '',
            'address' => '',
            'address_2' => '',
        ],
    ];

    $calculado = WC()->shipping()->calculate_shipping_for_package($pacote);
    $opcoes = [];

    foreach ($calculado['rates'] ?? [] as $taxa) {
        $opcoes[] = [
            'nome' => $taxa->get_label(),
            // wc_price devolve entidades (&#82;&#36;); o JS escreve como texto
            'valor' => html_entity_decode(wp_strip_all_tags(wc_price((float) $taxa->get_cost() + (float) array_sum($taxa->get_taxes()))), ENT_QUOTES, 'UTF-8'),
        ];
    }

    if (! $opcoes) {
        wp_send_json_error(['mensagem' => 'Não encontramos entrega para esse CEP.']);
    }

    wp_send_json_success(['opcoes' => $opcoes]);
}
