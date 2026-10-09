<?php
/**
 * Coluna de filtros do catálogo. Um formulário GET: o estado do catálogo mora
 * na URL, e por isso funciona sem JavaScript e pode ser compartilhado.
 */

if (! defined('ABSPATH')) {
    exit;
}

$faixa = conexao_faixa_precos();
$min_atual = isset($_GET['min_price']) ? (float) $_GET['min_price'] : $faixa['min']; // phpcs:ignore WordPress.Security.NonceVerification
$max_atual = isset($_GET['max_price']) ? (float) $_GET['max_price'] : $faixa['max']; // phpcs:ignore WordPress.Security.NonceVerification
$estoque_atual = conexao_filtro_selecionado('estoque');
$destaques_atuais = conexao_destaques_selecionados();
$mostrar = 8;
?>
<?php /* no celular a coluna vira uma sanfona; no desktop o resumo fica escondido */ ?>
<details class="filtros-caixa" open>
    <summary class="filtros-caixa__resumo" aria-label="Filtros"><?php conexao_the_icon('funil', 20); ?></summary>

<form class="filtros" method="get" action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
    <?php foreach (conexao_filtros_taxonomia() as $chave => $grupo) : ?>
        <?php
        if (! taxonomy_exists($grupo['taxonomia'])) {
            continue;
        }

        // categorias em árvore (principal e, recuadas, as subcategorias); o resto pela quantidade
        if ($grupo['taxonomia'] === 'product_cat') {
            $arvore = conexao_categorias_em_arvore();
            $termos = array_column($arvore, 0);
            $niveis = array_column($arvore, 1);
        } else {
            $termos = get_terms([
                'taxonomy' => $grupo['taxonomia'],
                'hide_empty' => true,
                'orderby' => 'count',
                'order' => 'DESC',
            ]);
            $niveis = [];
        }

        if (is_wp_error($termos) || ! $termos) {
            continue;
        }

        $selecionados = conexao_filtro_selecionado($chave);
        // na árvore, "ver todas" esconde depois da oitava categoria principal, não no meio de um grupo
        $limite = $niveis ? (array_keys(array_filter($niveis, static fn ($n) => $n === 0))[$mostrar] ?? PHP_INT_MAX) : $mostrar;
        ?>
        <section class="filtros__grupo" data-filtro>
            <h2><?php echo esc_html($grupo['titulo']); ?></h2>

            <?php if (! empty($grupo['busca'])) : ?>
                <p class="filtros__busca">
                    <label class="tela-leitor" for="filtro-busca-<?php echo esc_attr($chave); ?>">Procure por <?php echo esc_html(strtolower($grupo['titulo'])); ?></label>
                    <input type="search" id="filtro-busca-<?php echo esc_attr($chave); ?>" data-filtro-busca
                           placeholder="Procure por <?php echo esc_attr(strtolower($grupo['titulo'])); ?>...">
                    <?php conexao_the_icon('lupa', 16); ?>
                </p>
            <?php endif; ?>

            <ul class="filtros__lista">
                <?php foreach ($termos as $i => $termo) : ?>
                    <li<?php echo ! empty($niveis[$i]) ? ' class="filtros__sub"' : ''; ?><?php echo $i >= $limite ? ' hidden data-extra' : ''; ?>>
                        <label class="caixa caixa--quadrada">
                            <input type="checkbox" name="<?php echo esc_attr($chave); ?>[]"
                                   value="<?php echo esc_attr($termo->slug); ?>"
                                   <?php checked(in_array($termo->slug, $selecionados, true)); ?>>
                            <span><?php echo esc_html($termo->name); ?></span>
                            <span class="filtros__conta">(<?php echo esc_html((string) $termo->count); ?>)</span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (count($termos) > $limite) : ?>
                <button class="filtros__mais" type="button" data-filtro-mais>Ver todas</button>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="filtros__grupo">
        <h2>Preço</h2>

        <div class="filtros__preco">
            <span>R$ <?php echo esc_html((string) (int) $faixa['min']); ?></span>
            <span>R$ <?php echo esc_html((string) (int) $faixa['max']); ?>+</span>
        </div>

        <label class="tela-leitor" for="filtro-preco">Preço máximo</label>
        <input class="filtros__faixa" type="range" id="filtro-preco" name="max_price"
               min="<?php echo esc_attr((string) (int) $faixa['min']); ?>"
               max="<?php echo esc_attr((string) (int) $faixa['max']); ?>"
               step="10" value="<?php echo esc_attr((string) (int) $max_atual); ?>">
        <input type="hidden" name="min_price" value="<?php echo esc_attr((string) (int) $min_atual); ?>">
    </section>

    <section class="filtros__grupo">
        <h2>Destaques</h2>

        <ul class="filtros__lista">
            <?php foreach (conexao_destaques_catalogo() as $valor => $rotulo) : ?>
                <li>
                    <label class="caixa caixa--quadrada">
                        <input type="checkbox" name="destaque[]" value="<?php echo esc_attr($valor); ?>"
                               <?php checked(in_array($valor, $destaques_atuais, true)); ?>>
                        <span><?php echo esc_html($rotulo); ?></span>
                        <span class="filtros__conta">(<?php echo esc_html((string) count(conexao_ids_destaque($valor))); ?>)</span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="filtros__grupo">
        <h2>Disponibilidade</h2>

        <ul class="filtros__lista">
            <?php
            $situacoes = [
                'instock' => 'Em estoque',
                'onbackorder' => 'Sob encomenda',
            ];

            foreach ($situacoes as $valor => $rotulo) :
                ?>
                <li>
                    <label class="caixa caixa--quadrada">
                        <input type="checkbox" name="estoque[]" value="<?php echo esc_attr($valor); ?>"
                               <?php checked(in_array($valor, $estoque_atual, true)); ?>>
                        <span><?php echo esc_html($rotulo); ?></span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="filtros__acoes">
        <a class="btn btn--contorno-cinza btn--bloco" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Limpar filtros</a>
        <button class="btn btn--azul btn--bloco" type="submit">Aplicar filtros</button>
    </div>
</form>
</details>
