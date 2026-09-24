<?php
/**
 * Perguntas frequentes: busca no topo e a sanfona numerada do layout.
 * O conteúdo da página, se houver, entra como texto de apoio antes das
 * perguntas; sem ele, usa a chamada padrão.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

the_post();

$intro = trim(wp_strip_all_tags(get_the_content()));
$itens = conexao_faq_itens();
?>
<article class="container pagina pagina--faq">
    <?php conexao_trilha('F.A.Q'); ?>

    <header class="pagina__topo pagina__topo--regua">
        <h1><?php the_title(); ?></h1>
    </header>

    <p class="pagina__intro">
        <?php echo esc_html($intro ?: 'Tire suas dúvidas sobre publicação, compras, pedidos e nossos serviços editoriais.'); ?>
    </p>

    <?php get_search_form(); ?>

    <?php if ($itens) : ?>
        <?php conexao_secao_titulo('Perguntas Frequentes'); ?>

        <div class="faq">
            <?php foreach ($itens as $i => $item) : ?>
                <details class="faq__item"<?php echo $i === 0 ? ' open' : ''; ?>>
                    <summary class="faq__pergunta">
                        <span class="faq__numero"><?php echo esc_html($i + 1); ?>.</span>
                        <span class="faq__texto"><?php echo esc_html($item['pergunta']); ?></span>
                        <?php conexao_the_icon('seta-baixo', 20); ?>
                    </summary>

                    <div class="faq__resposta">
                        <p><?php echo esc_html($item['resposta']); ?></p>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
<?php
get_footer();
