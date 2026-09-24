<?php
/**
 * Central de ajuda: banner com busca, atalhos por tema e a sanfona dos tópicos.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

the_post();

$topicos = conexao_ajuda_topicos();
$img = get_template_directory_uri().'/assets/img/';
$contato = get_page_by_path('contato');
?>
<div class="container pagina pagina--ajuda">
    <?php conexao_trilha('Ajuda'); ?>

    <section class="ajuda-hero">
        <div class="ajuda-hero__texto">
            <h1>Ajuda</h1>
            <p>Encontre respostas rápidas para suas dúvidas sobre compras, pagamentos, entregas e pedidos.</p>

            <form class="busca ajuda-hero__busca" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <label class="tela-leitor" for="ajuda-busca">Buscar na ajuda</label>
                <input type="search" id="ajuda-busca" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
                       placeholder="Digite sua dúvida aqui...">
                <button type="submit" aria-label="Buscar"><?php conexao_the_icon('lupa', 20); ?></button>
            </form>
        </div>

        <img class="ajuda-hero__arte" src="<?php echo esc_url($img.'ajuda-arte.png'); ?>" alt=""
             aria-hidden="true" width="504" height="378" decoding="async">
    </section>

    <nav class="ajuda-atalhos" aria-label="Temas da ajuda">
        <?php foreach ($topicos as $i => $topico) : ?>
            <a class="ajuda-atalho<?php echo $i === 0 ? ' ajuda-atalho--ativo' : ''; ?>"
               href="#ajuda-<?php echo esc_attr($topico['slug']); ?>">
                <?php conexao_a_svg_ajuda($topico['slug'] === 'central' ? 'central' : $topico['slug'], 18); ?>
                <?php echo esc_html($topico['atalho']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="ajuda-lista">
        <?php foreach ($topicos as $i => $topico) : ?>
            <details class="ajuda-card" id="ajuda-<?php echo esc_attr($topico['slug']); ?>"<?php echo $i === 0 ? ' open' : ''; ?>>
                <summary class="ajuda-card__resumo">
                    <span class="ajuda-card__icone"><?php conexao_a_svg_ajuda($topico['slug'], 30); ?></span>

                    <span class="ajuda-card__texto">
                        <strong><?php echo esc_html(($i + 1).'. '.$topico['titulo']); ?></strong>
                        <span><?php echo esc_html($topico['resumo']); ?></span>
                    </span>

                    <span class="ajuda-card__botao" aria-hidden="true"></span>
                </summary>

                <div class="ajuda-card__painel">
                    <ul>
                        <?php foreach ($topico['perguntas'] as $pergunta) : ?>
                            <li><?php echo esc_html($pergunta); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="ajuda-fale">
            <span class="ajuda-card__icone"><?php conexao_a_svg_ajuda('atendente', 32); ?></span>

            <span class="ajuda-card__texto">
                <strong>Não encontrou o que procura?</strong>
                <span>Nossa equipe está pronta para ajudar você.</span>
            </span>

            <a class="btn btn--escuro" href="<?php echo esc_url($contato ? get_permalink($contato) : home_url('/contato/')); ?>">Fale conosco</a>
        </div>
    </div>
</div>
<?php
get_footer();
