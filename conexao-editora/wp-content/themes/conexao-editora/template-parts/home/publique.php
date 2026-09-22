<?php
/**
 * Chamada para autores que querem publicar pela editora.
 */

if (! defined('ABSPATH')) {
    exit;
}

$ilustracao = get_template_directory().'/assets/img/publique.png';
?>
<section class="publique">
    <div class="container publique__grade">
        <div class="publique__texto">
            <h2>Publique conosco</h2>
            <p>Transformamos seu manuscrito em um livro de qualidade. Oferecemos apoio editorial completo: revisão, projeto gráfico, diagramação, impressão e distribuição.</p>
            <a class="btn btn--escuro" href="<?php echo esc_url(home_url('/publique-conosco/')); ?>">Saiba mais</a>
        </div>

        <div class="publique__arte">
            <?php if (file_exists($ilustracao)) : ?>
                <img src="<?php echo esc_url(get_template_directory_uri().'/assets/img/publique.png'); ?>" alt="" loading="lazy">
            <?php endif; ?>
        </div>
    </div>
</section>
