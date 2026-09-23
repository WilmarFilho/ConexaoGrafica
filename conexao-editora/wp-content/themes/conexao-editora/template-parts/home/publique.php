<?php
/**
 * Chamada para autores que querem publicar pela editora.
 * A arte (ilustração e selos) vem como banner exportado do layout; aqui só
 * entra o texto, alinhado com o resto da página.
 */

if (! defined('ABSPATH')) {
    exit;
}

$banner = get_template_directory().'/assets/img/publique-banner.png';
?>
<section class="publique<?php echo file_exists($banner) ? ' publique--banner' : ''; ?>"
    <?php if (file_exists($banner)) : ?>
        style="background-image: url('<?php echo esc_url(get_template_directory_uri().'/assets/img/publique-banner.png'); ?>')"
    <?php endif; ?>>
    <div class="container publique__grade">
        <div class="publique__texto">
            <h2>Publique conosco</h2>
            <p>Transformarmos seu manuscrito em um livro de qualidade. Oferecemos apoio editorial completo, revisão, projeto gráfico, diagramação, impressão e distribuição.</p>
            <a class="btn btn--escuro" href="<?php echo esc_url(home_url('/publique-conosco/')); ?>">Saiba mais</a>
        </div>
    </div>
</section>
