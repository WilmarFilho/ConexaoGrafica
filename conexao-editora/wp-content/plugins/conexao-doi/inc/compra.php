<?php
/**
 * Caixa de compra: cada `commercial[]` habilitado aponta para um produto do
 * WooCommerce (produto simples, variação, ou o produto variável — aí a
 * variação é escolhida pela modalidade: impresso, e-book, impresso-e-book).
 * Preço e estoque são os reais; o botão adiciona ao carrinho. Sem produto, ou
 * livro despublicado, a página fica sem botão e avisa "fora de catálogo".
 * O capítulo herda a caixa do livro.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Modalidade do Hub → termo do atributo pa_formato da loja. */
function conexao_doi_formato_da_modalidade(string $modalidade): string
{
    return ['print' => 'impresso', 'ebook' => 'e-book', 'combo' => 'impresso-e-book'][$modalidade] ?? '';
}

/** Rótulo curto da modalidade, para a aba da caixa. */
function conexao_doi_rotulo_modalidade(string $modalidade): string
{
    return ['print' => 'Impresso', 'ebook' => 'E-book', 'combo' => 'Impresso + e-book'][$modalidade] ?? ucfirst($modalidade);
}

/**
 * O produto que responde por um link comercial. Devolve [produto à venda,
 * produto pai] ou [null, null].
 */
function conexao_doi_produto_do_link(array $link): array
{
    $id = (int) ($link['woo_product_id'] ?? 0);

    if ($id < 1 || ! function_exists('wc_get_product')) {
        return [null, null];
    }

    $produto = wc_get_product($id);

    if (! $produto || $produto->get_status() !== 'publish' && $produto->get_parent_id() === 0) {
        return [null, null];
    }

    if ($produto->is_type('variation')) {
        $pai = wc_get_product($produto->get_parent_id());

        return ($pai && $pai->get_status() === 'publish') ? [$produto, $pai] : [null, null];
    }

    if ($produto->is_type('variable')) {
        $formato = conexao_doi_formato_da_modalidade((string) ($link['modality'] ?? ''));

        foreach ($produto->get_children() as $filho_id) {
            $variacao = wc_get_product($filho_id);

            if ($variacao && ($variacao->get_attributes()['pa_formato'] ?? '') === $formato) {
                return [$variacao, $produto];
            }
        }

        // sem variação para a modalidade: a caixa manda para a página da loja
        return [null, $produto];
    }

    return [$produto, $produto];
}

/**
 * Ofertas do livro, na ordem de `sort_order`, só as habilitadas.
 *
 * @return array<int, array{modality: string, label: string, produto: ?WC_Product, pai: ?WC_Product,
 *                          preco_html: string, preco: float, disponivel: bool, url: string, externo: bool, loja: string}>
 */
function conexao_doi_ofertas(int $livro_id): array
{
    static $memoria = [];

    if (isset($memoria[$livro_id])) {
        return $memoria[$livro_id];
    }

    $livro = conexao_doi_livro($livro_id);
    $links = array_values(array_filter((array) ($livro['commercial'] ?? []), static fn ($l) => is_array($l) && ! empty($l['enabled'])));

    usort($links, static fn ($a, $b) => (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0));

    $ofertas = [];

    foreach ($links as $link) {
        [$produto, $pai] = conexao_doi_produto_do_link($link);
        $modalidade = (string) ($link['modality'] ?? '');
        $oferta = [
            'modality' => $modalidade,
            'label' => (string) ($link['label'] ?: 'Comprar '.strtolower(conexao_doi_rotulo_modalidade($modalidade))),
            'produto' => $produto,
            'pai' => $pai,
            'preco_html' => '',
            'preco' => 0.0,
            'disponivel' => false,
            'url' => '',
            'externo' => false,
            'loja' => $pai ? (string) $pai->get_permalink() : '',
        ];

        if ($produto) {
            $oferta['preco_html'] = (string) $produto->get_price_html();
            $oferta['preco'] = (float) $produto->get_price();
            $oferta['disponivel'] = $produto->is_purchasable() && $produto->is_in_stock();
            $oferta['url'] = conexao_doi_url_carrinho($produto, $pai);
        } elseif (! empty($link['url']) && conexao_doi_url_externa_valida((string) $link['url'])) {
            $oferta['url'] = (string) $link['url'];
            $oferta['externo'] = true;
            $oferta['disponivel'] = true;
        }

        if ($oferta['url'] !== '' || $oferta['loja'] !== '') {
            $ofertas[] = $oferta;
        }
    }

    return $memoria[$livro_id] = $ofertas;
}

/** Link externo só em HTTPS e no domínio da editora. */
function conexao_doi_url_externa_valida(string $url): bool
{
    $partes = wp_parse_url($url);
    $host = strtolower((string) ($partes['host'] ?? ''));

    return ($partes['scheme'] ?? '') === 'https' && ($host === 'conexaoeditora.com.br' || str_ends_with($host, '.conexaoeditora.com.br'));
}

/** Endereço que põe o produto no carrinho e abre o carrinho. */
function conexao_doi_url_carrinho(WC_Product $produto, ?WC_Product $pai): string
{
    $carrinho = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/carrinho/');

    if ($produto->is_type('variation') && $pai) {
        $args = ['add-to-cart' => $pai->get_id(), 'variation_id' => $produto->get_id()];

        foreach ($produto->get_attributes() as $nome => $valor) {
            $args['attribute_'.$nome] = $valor;
        }

        return add_query_arg($args, $carrinho);
    }

    return add_query_arg('add-to-cart', $produto->get_id(), $carrinho);
}

/** Id da imagem de capa: a do produto Woo ligado (a capa pública vem da loja). */
function conexao_doi_capa_id(int $livro_id): int
{
    foreach (conexao_doi_ofertas($livro_id) as $oferta) {
        foreach ([$oferta['pai'], $oferta['produto']] as $produto) {
            if ($produto && $produto->get_image_id()) {
                return (int) $produto->get_image_id();
            }
        }
    }

    return 0;
}

/** URL da capa em um tamanho; vazio quando não há produto com imagem. */
function conexao_doi_capa_url(int $livro_id, string $tamanho = 'large'): string
{
    $id = conexao_doi_capa_id($livro_id);

    return $id ? (string) wp_get_attachment_image_url($id, $tamanho) : '';
}

/**
 * Capa e páginas de amostra da galeria do produto ligado, no formato da faixa
 * "Leia uma amostra" do tema (conexao_amostras_produto); vazio sem galeria.
 *
 * @return array<int, array{id: int, grande: string, mini: string, rotulo: string}>
 */
function conexao_doi_amostras(int $livro_id): array
{
    if (! function_exists('conexao_amostras_produto')) {
        return [];
    }

    foreach (conexao_doi_ofertas($livro_id) as $oferta) {
        foreach ([$oferta['pai'], $oferta['produto']] as $produto) {
            if ($produto instanceof WC_Product && ($amostras = conexao_amostras_produto($produto))) {
                return $amostras;
            }
        }
    }

    return [];
}

/** A capa pronta para a página (ou o placeholder do tema). */
function conexao_doi_capa_html(int $livro_id, string $tamanho = 'large', string $classe = ''): string
{
    $id = conexao_doi_capa_id($livro_id);
    $alt = 'Capa de '.get_the_title($livro_id);

    if ($id) {
        return (string) wp_get_attachment_image($id, $tamanho, false, ['class' => $classe, 'alt' => $alt]);
    }

    $placeholder = get_template_directory_uri().'/assets/img/capa-placeholder.png';

    return sprintf('<img class="%s" src="%s" alt="%s" width="400" height="560" loading="lazy">', esc_attr($classe), esc_url($placeholder), esc_attr($alt));
}

/** Caixa de compra do livro (também usada na lateral do capítulo, compacta). */
function conexao_doi_caixa_compra(int $livro_id, bool $compacta = false, bool $forcar_fora = false): void
{
    $ofertas = conexao_doi_ofertas($livro_id);
    $fora = $forcar_fora || conexao_doi_fora_de_catalogo($livro_id);
    $compraveis = array_values(array_filter($ofertas, static fn ($o) => $o['disponivel'] && $o['url'] !== ''));
    ?>
    <section class="doi-compra<?php echo $compacta ? ' doi-compra--compacta' : ''; ?>" aria-labelledby="doi-compra-titulo-<?php echo (int) $compacta; ?>">
        <h2 class="tela-leitor" id="doi-compra-titulo-<?php echo (int) $compacta; ?>">Comprar</h2>

        <?php if ($fora || ! $compraveis) : ?>
            <p class="doi-compra__aviso" role="status">
                <?php conexao_doi_icone('info', 18); ?>
                <span>
                    <strong>Fora de catálogo.</strong>
                    <?php echo $fora ? 'Esta obra não está mais à venda; a página permanece como registro permanente do DOI.' : 'Esta obra não está à venda no momento.'; ?>
                </span>
            </p>
            <?php // sem "Ver na loja": a página do produto redireciona para a do livro (inc/loja.php) ?>
        <?php elseif ($compacta) : ?>
            <div class="doi-compra__botoes">
                <?php foreach ($compraveis as $i => $oferta) : ?>
                    <a class="btn <?php echo $i === 0 ? 'btn--azul' : 'btn--contorno'; ?> btn--bloco doi-compra__botao"
                       href="<?php echo esc_url($oferta['url']); ?>"<?php echo $oferta['externo'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <?php conexao_doi_icone('carrinho', 18); ?>
                        <?php echo esc_html($oferta['label']); ?>
                        <?php if ($oferta['preco_html']) : ?>
                            <span class="doi-compra__preco-mini"><?php echo wp_kses_post($oferta['preco_html']); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <?php $principal = $compraveis[0]; ?>
            <?php if (count($compraveis) > 1) : ?>
                <div class="doi-compra__abas" role="tablist" aria-label="Formato">
                    <?php foreach ($compraveis as $i => $oferta) : ?>
                        <button class="doi-compra__aba<?php echo $i === 0 ? ' e-ativa' : ''; ?>" type="button" role="tab"
                                id="doi-oferta-aba-<?php echo $i; ?>" aria-controls="doi-oferta-<?php echo $i; ?>"
                                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" tabindex="<?php echo $i === 0 ? '0' : '-1'; ?>">
                            <?php echo esc_html(conexao_doi_rotulo_modalidade($oferta['modality'])); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($compraveis as $i => $oferta) : ?>
                <div class="doi-oferta" id="doi-oferta-<?php echo $i; ?>" role="<?php echo count($compraveis) > 1 ? 'tabpanel' : 'group'; ?>"
                     <?php echo count($compraveis) > 1 ? 'aria-labelledby="doi-oferta-aba-'.$i.'"' : ''; ?><?php echo $i > 0 ? ' hidden' : ''; ?>>
                    <?php if ($oferta['preco_html']) : ?>
                        <p class="doi-oferta__preco">
                            <span class="doi-oferta__valor"><?php echo wp_kses_post($oferta['preco_html']); ?></span>
                            <span class="doi-oferta__estoque"><?php conexao_doi_icone('check', 16); ?> Em estoque</span>
                        </p>
                    <?php endif; ?>

                    <a class="btn btn--azul btn--bloco doi-compra__botao" href="<?php echo esc_url($oferta['url']); ?>"<?php echo $oferta['externo'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <?php conexao_doi_icone('carrinho', 18); ?>
                        <?php echo esc_html($oferta['label']); ?>
                    </a>

                    <ul class="doi-compra__vantagens">
                        <?php if ($oferta['modality'] !== 'ebook') : ?>
                            <li><?php conexao_doi_icone('caminhao', 18); ?> Entrega para todo o Brasil</li>
                        <?php else : ?>
                            <li><?php conexao_doi_icone('download', 18); ?> Leitura na sua biblioteca digital</li>
                        <?php endif; ?>
                        <li><?php conexao_doi_icone('cadeado', 18); ?> Compra segura</li>
                        <?php if ($oferta['preco'] > 0) : ?>
                            <li><?php conexao_doi_icone('cartao', 18); ?> Parcele em até 6x sem juros</li>
                        <?php endif; ?>
                    </ul>

                    <?php foreach ($compraveis as $j => $outra) : ?>
                        <?php if ($j === $i) { continue; } ?>
                        <a class="doi-compra__secundaria" href="<?php echo esc_url($outra['url']); ?>"<?php echo $outra['externo'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                            <?php conexao_doi_icone($outra['modality'] === 'ebook' ? 'ebook' : 'livro', 20); ?>
                            <span>
                                <strong><?php echo esc_html($outra['label']); ?></strong>
                                <?php if ($outra['preco_html']) : ?>
                                    <span><?php echo wp_kses_post($outra['preco_html']); ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php
}
