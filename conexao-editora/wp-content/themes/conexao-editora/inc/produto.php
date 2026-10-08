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
        '_conexao_editora' => ['rotulo' => 'Editora', 'icone' => 'editora', 'dica' => 'Em branco: Conexão Editora'],
        '_conexao_publicacao' => ['rotulo' => 'Data de publicação', 'icone' => 'publicacao', 'dica' => 'Ano ou data. Em branco: data de cadastro do produto'],
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

    $presentes = array_column($ficha, 'icone');

    if (! in_array('editora', $presentes, true)) {
        $ficha[] = [
            'icone' => 'editora',
            'rotulo' => 'Editora',
            'valor' => (string) apply_filters('conexao_nome_editora', 'Conexão Editora'),
        ];
    }

    $publicacao = in_array('publicacao', $presentes, true) ? null : $produto->get_date_created();

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

/** Formato de uma variação (impresso, e-book ou impresso-e-book). */
function conexao_formato_variacao(WC_Product $variacao): string
{
    return (string) ($variacao->get_attributes()['pa_formato'] ?? '');
}

/**
 * O impresso deste livro está à venda? Zerar o estoque do impresso (ou marcar
 * "Fora de estoque") tira dele também o combo, que depende do livro físico.
 */
function conexao_impresso_a_venda(int $produto_id): bool
{
    static $memoria = [];

    if (isset($memoria[$produto_id])) {
        return $memoria[$produto_id];
    }

    $produto = wc_get_product($produto_id);
    $a_venda = false;

    foreach ($produto ? $produto->get_children() : [] as $filho) {
        $variacao = wc_get_product($filho);

        if ($variacao && conexao_formato_variacao($variacao) === 'impresso') {
            $a_venda = $variacao->get_status() === 'publish'
                && $variacao->get_price() !== ''
                && $variacao->is_in_stock();
            break;
        }
    }

    return $memoria[$produto_id] = $a_venda;
}

add_filter('woocommerce_variation_is_purchasable', function (bool $pode, $variacao): bool {
    if ($pode && $variacao instanceof WC_Product_Variation && conexao_formato_variacao($variacao) === 'impresso-e-book') {
        return conexao_impresso_a_venda($variacao->get_parent_id());
    }

    return $pode;
}, 10, 2);

/**
 * Na página do livro, o seletor de formato só oferece o que dá para comprar:
 * variação existente, publicada, com preço e em estoque. O que sobra aparece
 * apagado (theme.js).
 */
add_filter('woocommerce_dropdown_variation_attribute_options_args', function (array $args): array {
    if (($args['attribute'] ?? '') !== 'pa_formato' || empty($args['product'])) {
        return $args;
    }

    $disponiveis = [];

    foreach ($args['product']->get_available_variations() as $variacao) {
        $valor = $variacao['attributes']['attribute_pa_formato'] ?? '';

        if ($valor !== '' && ! empty($variacao['is_in_stock']) && ! empty($variacao['is_purchasable'])) {
            $disponiveis[] = $valor;
        }
    }

    $args['options'] = array_values(array_intersect((array) $args['options'], $disponiveis));

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
 * Endereço antigo do e-book leva para o produto unificado, com 301, para não
 * perder link nem posição de busca. Os e-books antigos foram removidos; o mapa
 * endereço → livro ficou na opção conexao_enderecos_antigos. Enquanto algum
 * ainda existir como rascunho, ele também vale.
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

    $mapa = (array) get_option('conexao_enderecos_antigos', []);
    $destino = (int) ($mapa[$slug] ?? 0);

    if ($destino && get_post_status($destino) === 'publish') {
        wp_safe_redirect(get_permalink($destino), 301);
        exit;
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

/**
 * Venda externa: o livro é vendido em outro site (Amazon, o site do autor…).
 * Com o link preenchido no produto, o botão principal deixa de ir para o
 * carrinho e abre o endereço em outra aba.
 */
add_action('woocommerce_product_options_general_product_data', function (): void {
    echo '<div class="options_group">';

    woocommerce_wp_checkbox([
        'id' => '_conexao_pre_venda',
        'label' => 'Livro em pré-venda',
        'description' => 'Aparece em Livros → Pré-vendas e no filtro "Destaques" do catálogo.',
    ]);

    woocommerce_wp_checkbox([
        'id' => '_conexao_lancamento',
        'label' => 'Lançamento',
        'description' => 'Aparece em Livros → Lançamentos. Enquanto nenhum livro estiver marcado, valem os 15 mais recentes.',
    ]);

    woocommerce_wp_text_input([
        'id' => '_conexao_link_externo',
        'label' => 'Link de venda externa',
        'type' => 'url',
        'placeholder' => 'https://',
        'desc_tip' => true,
        'description' => 'Em branco, o livro é vendido pela loja. Preenchido, o botão principal abre este endereço em outra aba.',
    ]);

    woocommerce_wp_text_input([
        'id' => '_conexao_texto_externo',
        'label' => 'Texto do botão externo',
        'placeholder' => 'Comprar no site oficial',
        'desc_tip' => true,
        'description' => 'Só vale com o link acima. Em branco: "Comprar no site oficial".',
    ]);

    echo '</div>';
});

add_action('woocommerce_process_product_meta', function (int $id): void {
    foreach (['_conexao_pre_venda', '_conexao_lancamento'] as $chave) {
        if (isset($_POST[$chave])) { // phpcs:ignore WordPress.Security.NonceVerification
            update_post_meta($id, $chave, 'yes');
        } else {
            delete_post_meta($id, $chave);
        }
    }
});

add_action('woocommerce_process_product_meta', function (int $id): void {
    $link = isset($_POST['_conexao_link_externo']) ? esc_url_raw(trim(wp_unslash($_POST['_conexao_link_externo'])), ['http', 'https']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $texto = isset($_POST['_conexao_texto_externo']) ? sanitize_text_field(wp_unslash($_POST['_conexao_texto_externo'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

    foreach (['_conexao_link_externo' => $link, '_conexao_texto_externo' => $link ? $texto : ''] as $chave => $valor) {
        if ($valor === '') {
            delete_post_meta($id, $chave);
        } else {
            update_post_meta($id, $chave, $valor);
        }
    }
});

/** @return array{url: string, texto: string}|null */
function conexao_venda_externa(WC_Product $produto): ?array
{
    $id = $produto->get_parent_id() ?: $produto->get_id();
    $url = (string) get_post_meta($id, '_conexao_link_externo', true);

    if ($url === '') {
        return null;
    }

    $texto = (string) get_post_meta($id, '_conexao_texto_externo', true);

    return ['url' => $url, 'texto' => $texto !== '' ? $texto : 'Comprar no site oficial'];
}

/**
 * O botão principal da compra: "Comprar agora" (vai direto ao pagamento) ou,
 * na venda externa, o link para o site oficial numa aba nova.
 */
function conexao_botao_compra_principal(WC_Product $produto, bool $habilitado = true): void
{
    $externa = conexao_venda_externa($produto);

    if ($externa) {
        printf(
            '<a class="btn btn--azul btn--bloco compra__principal compra__principal--externo" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($externa['url']),
            esc_html($externa['texto'])
        );

        return;
    }

    printf(
        '<button class="btn btn--azul btn--bloco compra__principal" type="submit" name="conexao_comprar" value="1"%s>Comprar agora</button>',
        $habilitado ? '' : ' disabled'
    );
}

/**
 * Livro com formatos usa o formulário do WooCommerce: o botão principal entra
 * depois do "Adicionar ao carrinho" e a ordem visual é acertada pelo CSS. Nasce
 * desabilitado, até um formato à venda ser escolhido.
 */
add_action('woocommerce_after_add_to_cart_button', function (): void {
    global $product;

    if ($product instanceof WC_Product && $product->is_type('variable')) {
        conexao_botao_compra_principal($product, false);
    }
});

// o carrinho vazio do tema já diz que está vazio: sem o aviso repetido do WooCommerce
remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);

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

/**
 * Capa e páginas de amostra (a galeria do produto), para a faixa de miniaturas.
 *
 * @return array<int, array{id: int, grande: string, mini: string, rotulo: string}>
 */
function conexao_amostras_produto(WC_Product $produto): array
{
    $galeria = array_filter(array_map('intval', $produto->get_gallery_image_ids()));

    if (! $galeria || ! $produto->get_image_id()) {
        return [];
    }

    $itens = [];

    foreach (array_merge([(int) $produto->get_image_id()], $galeria) as $i => $id) {
        $grande = wp_get_attachment_image_url($id, 'woocommerce_single');

        if (! $grande) {
            continue;
        }

        $itens[] = [
            'id' => $id,
            'grande' => $grande,
            'mini' => (string) wp_get_attachment_image_url($id, 'thumbnail'),
            'rotulo' => $i === 0 ? 'Capa' : 'Amostra: página '.$i,
        ];
    }

    return count($itens) > 1 ? $itens : [];
}
