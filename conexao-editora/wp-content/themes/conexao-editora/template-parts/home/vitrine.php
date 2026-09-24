<?php
/**
 * Vitrine de livros em linha (Lançamentos, Mais vendidos).
 *
 * @var array $args titulo, tipo e url do "ver todos".
 */

if (! defined('ABSPATH')) {
    exit;
}

$titulo = $args['titulo'] ?? 'Livros';
$tipo = $args['tipo'] ?? 'lancamentos';
$url = $args['url'] ?? '';
$produtos = conexao_produtos($tipo, 10);

if (! $produtos) {
    return;
}
?>
<section class="vitrine vitrine--<?php echo esc_attr($tipo); ?>">
    <div class="container">
        <?php conexao_secao_titulo($titulo, $url); ?>

        <div class="vitrine__trilho" data-arrastavel>
            <?php foreach ($produtos as $produto) : ?>
                <?php conexao_card_produto($produto); ?>
            <?php endforeach; ?>
        </div>

        <?php conexao_secao_rodape($url); ?>
    </div>
</section>
