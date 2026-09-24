<?php
/**
 * Abas do livro: descrição, detalhes, autores e avaliações. São botões de
 * rádio escondidos, para funcionarem sem JavaScript.
 */

if (! defined('ABSPATH')) {
    exit;
}

global $product;

$id = $product->get_id();
$autores_termos = get_the_terms($id, 'autor');
$autores_termos = (! is_wp_error($autores_termos) && $autores_termos) ? $autores_termos : [];
$ficha = conexao_ficha_produto($product);
$avaliacoes = (int) $product->get_review_count();

$abas = [
    'descricao' => 'Descrição',
    'detalhes' => 'Detalhes do produto',
];

if ($autores_termos) {
    $abas['autores'] = 'Autores';
}

if (comments_open() || $avaliacoes) {
    $abas['avaliacoes'] = sprintf('Avaliações (%d)', $avaliacoes);
}
?>
<div class="abas" data-abas>
    <div class="abas__botoes" role="tablist">
        <?php foreach ($abas as $chave => $rotulo) : ?>
            <button class="abas__botao<?php echo $chave === 'descricao' ? ' abas__botao--ativa' : ''; ?>"
                    type="button" role="tab" data-aba="<?php echo esc_attr($chave); ?>"
                    aria-selected="<?php echo $chave === 'descricao' ? 'true' : 'false'; ?>">
                <?php echo esc_html($rotulo); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="abas__painel texto" data-painel="descricao">
        <?php echo $product->get_description() ? wp_kses_post(wpautop($product->get_description())) : '<p>Descrição em breve.</p>'; ?>
    </div>

    <div class="abas__painel texto" data-painel="detalhes" hidden>
        <ul class="produto__detalhes">
            <?php foreach ($ficha as $item) : ?>
                <li><strong><?php echo esc_html($item['rotulo']); ?>:</strong> <?php echo esc_html($item['valor']); ?></li>
            <?php endforeach; ?>

            <?php if ($product->get_weight()) : ?>
                <li><strong>Peso:</strong> <?php echo esc_html(wc_format_weight((float) $product->get_weight())); ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if ($autores_termos) : ?>
        <div class="abas__painel texto" data-painel="autores" hidden>
            <?php foreach ($autores_termos as $autor) : ?>
                <?php $foto = (int) get_term_meta($autor->term_id, 'conexao_foto', true); ?>
                <article class="produto-autor">
                    <?php if ($foto) : ?>
                        <?php echo wp_get_attachment_image($foto, 'thumbnail', false, ['class' => 'produto-autor__foto', 'alt' => '']); ?>
                    <?php endif; ?>

                    <div>
                        <h3><a href="<?php echo esc_url(get_term_link($autor)); ?>"><?php echo esc_html($autor->name); ?></a></h3>
                        <?php if ($autor->description) : ?>
                            <p><?php echo esc_html($autor->description); ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($abas['avaliacoes'])) : ?>
        <div class="abas__painel" data-painel="avaliacoes" hidden>
            <?php comments_template(); ?>
        </div>
    <?php endif; ?>
</div>
