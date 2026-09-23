<?php
/**
 * Chamada para autores que querem publicar pela editora.
 */

if (! defined('ABSPATH')) {
    exit;
}

$img = get_template_directory_uri().'/assets/img/';
$ilustracao = get_template_directory().'/assets/img/publique.png';
$marca = get_template_directory().'/assets/img/marca-dagua.png';
?>
<section class="publique">
    <?php if (file_exists($marca)) : ?>
        <img class="publique__selo publique__selo--esq" src="<?php echo esc_url($img.'marca-dagua.png'); ?>" alt="" aria-hidden="true" loading="lazy">
        <img class="publique__selo publique__selo--dir" src="<?php echo esc_url($img.'marca-dagua.png'); ?>" alt="" aria-hidden="true" loading="lazy">
    <?php endif; ?>

    <div class="container publique__grade">
        <div class="publique__texto">
            <h2>Publique conosco</h2>
            <p>Transformarmos seu manuscrito em um livro de qualidade. Oferecemos apoio editorial completo, revisão, projeto gráfico, diagramação, impressão e distribuição.</p>
            <a class="btn btn--escuro" href="<?php echo esc_url(home_url('/publique-conosco/')); ?>">Saiba mais</a>
        </div>

        <div class="publique__arte">
            <?php if (file_exists($ilustracao)) : ?>
                <img src="<?php echo esc_url($img.'publique.png'); ?>" alt="Etapas do processo editorial: revisão, diagramação, impressão e distribuição" loading="lazy">
            <?php endif; ?>
        </div>
    </div>
</section>
