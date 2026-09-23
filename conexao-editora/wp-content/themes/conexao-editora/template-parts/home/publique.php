<?php
/**
 * Chamada para autores que querem publicar pela editora.
 * A arte (ilustração e selos) vem como banner exportado do layout; aqui só
 * entra o texto, alinhado com o resto da página.
 */

if (! defined('ABSPATH')) {
    exit;
}

$img = get_template_directory_uri().'/assets/img/';
$banner = get_template_directory().'/assets/img/publique-banner.png';
$selo = get_template_directory().'/assets/img/selo.png';
?>
<section class="publique<?php echo file_exists($banner) ? ' publique--banner' : ''; ?>"
    <?php if (file_exists($banner)) : ?>
        style="background-image: url('<?php echo esc_url($img.'publique-banner.png'); ?>')"
    <?php endif; ?>>
    <?php if (file_exists($selo)) : ?>
        <img class="publique__selo publique__selo--esq" src="<?php echo esc_url($img.'selo.png'); ?>" alt="" aria-hidden="true" loading="lazy">
        <img class="publique__selo publique__selo--dir" src="<?php echo esc_url($img.'selo.png'); ?>" alt="" aria-hidden="true" loading="lazy">
    <?php endif; ?>

    <div class="container publique__grade">
        <div class="publique__texto">
            <h2>Publique conosco</h2>
            <p>Transformarmos seu manuscrito em um livro de qualidade. Oferecemos apoio editorial completo, revisão, projeto gráfico, diagramação, impressão e distribuição.</p>
            <a class="btn btn--escuro" href="<?php echo esc_url(home_url('/publique-conosco/')); ?>">Saiba mais</a>
        </div>
    </div>
</section>
