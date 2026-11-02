<?php
/**
 * Página do livro (Figma "Site - Conexão Editora", quadro 2197:568), com ou sem
 * DOI. Recebe em $args o contexto montado por conexao_livro_contexto().
 */

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $args */
$c = $args;
$produto = $c['produto'];
$publicacao_id = (int) $c['publicacao_id'];
$livro = (array) $c['livro'];
$dados = (array) $c['dados'];
$tem_doi = $publicacao_id > 0;
$post_livro = $tem_doi ? get_post($publicacao_id) : null;

$sumario = $tem_doi ? conexao_doi_sumario($dados) : [];
$capitulos = $tem_doi ? (array) ($dados['chapters'] ?? []) : [];
usort($capitulos, static fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));

$organizadores = $tem_doi ? conexao_doi_organizadores($livro) : [];
$autores_capitulos = [];

if ($tem_doi) {
    foreach (conexao_doi_pessoas_da_publicacao($dados) as $pessoa) {
        if (! in_array((int) $pessoa['hub_person_id'], array_map('intval', array_column($organizadores, 'hub_person_id')), true)) {
            $autores_capitulos[] = $pessoa;
        }
    }
}

$autores_termos = [];

if ($produto) {
    $termos = get_the_terms($produto->get_id(), 'autor');
    $autores_termos = (! is_wp_error($termos) && $termos) ? $termos : [];
}

$referencias = (array) ($livro['references'] ?? []);
$avaliacoes = $produto ? (int) $produto->get_review_count() : 0;
$com_avaliacoes = $produto && (comments_open($produto->get_id()) || $avaliacoes);

$abas = ['descricao' => 'Descrição'];

if ($sumario) {
    $abas['sumario'] = 'Sumário';
}

if ($capitulos) {
    $abas['capitulos'] = 'Capítulos';
}

if ($organizadores || $autores_capitulos || $autores_termos) {
    $abas['autores'] = 'Autores e Organizadores';
}

$abas['detalhes'] = 'Detalhes';

if ($referencias) {
    $abas['referencias'] = 'Referências';
}

if ($com_avaliacoes) {
    $abas['avaliacoes'] = sprintf('Avaliações <span class="livro-abas__qtd">(%d)</span>', $avaliacoes);
}

// trilha: Home › área (ou categoria da loja) › livro
$niveis = [];

if ($produto && function_exists('conexao_categoria_do_livro')) {
    [$categoria, $categoria_principal] = conexao_categoria_do_livro($produto->get_id());

    foreach (array_unique(array_filter([$categoria_principal, $categoria]), SORT_REGULAR) as $nivel) {
        $niveis[] = ['url' => get_term_link($nivel), 'texto' => $nivel->name];
    }
} elseif (! empty($livro['subject_area'])) {
    $loja = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    $niveis[] = ['url' => add_query_arg('s', $livro['subject_area'], $loja), 'texto' => (string) $livro['subject_area']];
}

$amostras = $produto && function_exists('conexao_amostras_produto') ? conexao_amostras_produto($produto) : [];
$capa = $produto && $produto->get_image_id()
    ? wp_get_attachment_image($produto->get_image_id(), 'large', false, ['class' => 'livro__capa-imagem', 'data-amostras-grande' => ''])
    : ($tem_doi ? conexao_doi_capa_html($publicacao_id, 'large', 'livro__capa-imagem') : '');

get_header();
?>
<div class="container pagina pagina--livro">
    <?php conexao_trilha((string) $c['titulo'], $niveis); ?>

    <?php if ($tem_doi && conexao_doi_fora_de_catalogo($publicacao_id)) : ?>
        <p class="livro-faixa" role="status">Esta obra está fora de catálogo. A página permanece como registro permanente do DOI.</p>
    <?php endif; ?>

    <div class="livro">
        <div class="livro__principal">
            <div class="livro__topo">
                <div class="livro__capa-coluna">
                    <figure class="livro__capa"<?php echo $amostras ? ' data-amostras' : ''; ?>>
                        <div class="livro__capa-moldura"><?php echo $capa; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

                        <?php if ($amostras) : ?>
                            <figcaption class="livro__amostras">
                                <span class="livro__amostras-titulo">Leia uma amostra</span>
                                <div class="livro__amostras-trilho">
                                    <?php foreach ($amostras as $i => $amostra) : ?>
                                        <button type="button" class="produto__amostra<?php echo $i === 0 ? ' e-atual' : ''; ?>"
                                            data-grande="<?php echo esc_url($amostra['grande']); ?>"
                                            aria-label="<?php echo esc_attr($amostra['rotulo']); ?>"
                                            aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                                            <img src="<?php echo esc_url($amostra['mini']); ?>" alt="" loading="lazy" width="56" height="78">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                </div>

                <div class="livro__texto">
                    <h1 class="livro__titulo"><?php echo esc_html((string) $c['titulo']); ?></h1>

                    <?php if ($c['resumo'] !== '') : ?>
                        <p class="livro__resumo"><?php echo esc_html((string) $c['resumo']); ?></p>
                    <?php endif; ?>

                    <div class="livro__meta">
                        <?php if ($c['autores'] !== '') : ?>
                            <p class="livro__autores"><span class="livro__autores-rotulo"><?php echo esc_html((string) $c['rotulo_autores']); ?>:</span> <?php echo esc_html((string) $c['autores']); ?></p>
                        <?php endif; ?>

                        <?php if ($produto) : ?>
                            <?php $nota = (float) $produto->get_average_rating(); ?>
                            <span class="livro__estrelas" role="img" aria-label="<?php echo esc_attr(sprintf('Nota %s de 5', number_format_i18n($nota, 1))); ?>">
                                <?php for ($i = 1; $i <= 5; $i++) : ?>
                                    <?php echo conexao_livro_svg($i <= round($nota) ? 'estrela-cheia' : 'estrela-vazia'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                <?php endfor; ?>
                            </span>
                            <span class="livro__avaliacoes">(<?php echo esc_html(sprintf(_n('%d avaliação', '%d avaliações', $avaliacoes, 'conexao'), $avaliacoes)); ?>)</span>
                        <?php endif; ?>

                        <span class="livro__acoes">
                            <?php if ($c['ficha_post']) : ?>
                                <button class="livro__acao" type="button" data-ficha="<?php echo (int) $c['ficha_post']; ?>" data-ficha-tipo="livro"
                                        aria-label="Abrir a ficha do livro em PDF" title="Ficha do livro (PDF)">
                                    <?php echo conexao_livro_svg('ficha'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                </button>
                            <?php endif; ?>
                            <button class="livro__acao compartilhar" type="button" data-compartilhar
                                    data-url="<?php echo esc_url((string) $c['url']); ?>"
                                    data-titulo="<?php echo esc_attr((string) $c['titulo']); ?>"
                                    aria-label="Compartilhar este livro" title="Compartilhar">
                                <?php echo conexao_livro_svg('compartilhar'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </button>
                        </span>
                    </div>

                    <?php if ($c['ficha']) : ?>
                        <ul class="livro__ficha">
                            <?php foreach ($c['ficha'] as $item) : ?>
                                <li>
                                    <span class="livro__ficha-icone"><?php echo conexao_livro_svg($item['icone']); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                    <strong><?php echo esc_html($item['rotulo']); ?></strong>
                                    <span><?php echo esc_html($item['valor']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($c['doi'] !== '' || $c['isbn_impresso'] !== '' || $c['isbn_ebook'] !== '') : ?>
                <div class="livro__ids">
                    <?php if ($c['doi'] !== '') : ?>
                        <span class="livro__ids-rotulo">DOI do livro:</span>
                        <a class="livro__doi" href="<?php echo esc_url('https://doi.org/'.$c['doi']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) $c['doi']); ?></a>
                        <button class="livro__copiar" type="button" data-copiar="<?php echo esc_attr('https://doi.org/'.$c['doi']); ?>" aria-label="Copiar o link do DOI" title="Copiar o link do DOI">
                            <?php echo conexao_livro_svg('copiar'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <span class="doi-copiar__feito livro__copiar-feito" aria-live="polite"></span>
                        </button>
                    <?php endif; ?>

                    <?php if ($c['isbn_impresso'] !== '') : ?>
                        <span class="livro__ids-separador" aria-hidden="true"></span>
                        <p class="livro__isbn"><?php echo $tem_doi ? 'ISBN impresso:' : 'ISBN:'; ?><br><strong><?php echo esc_html((string) $c['isbn_impresso']); ?></strong></p>
                    <?php endif; ?>

                    <?php if ($c['isbn_ebook'] !== '') : ?>
                        <span class="livro__ids-separador" aria-hidden="true"></span>
                        <p class="livro__isbn">ISBN e-book:<br><strong><?php echo esc_html((string) $c['isbn_ebook']); ?></strong></p>
                    <?php endif; ?>

                    <?php if ($c['doi'] !== '') : ?>
                        <a class="livro__crossref" href="<?php echo esc_url('https://search.crossref.org/?q='.rawurlencode((string) $c['doi'])); ?>" target="_blank" rel="noopener noreferrer" title="DOI registrado na Crossref">
                            <img src="<?php echo esc_url(get_template_directory_uri().'/assets/img/produto/crossref.png'); ?>" width="119" height="59" alt="Crossref">
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($c['palavras']) : ?>
                <div class="livro__palavras">
                    <span class="livro__palavras-rotulo">Palavras-chave:</span>
                    <ul>
                        <?php foreach ($c['palavras'] as $palavra) : ?>
                            <li><?php echo esc_html($palavra); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="livro-abas" data-abas>
                <div class="livro-abas__botoes" role="tablist">
                    <?php foreach ($abas as $chave => $rotulo) : ?>
                        <button class="livro-abas__botao<?php echo $chave === 'descricao' ? ' abas__botao--ativa' : ''; ?>"
                                type="button" role="tab" data-aba="<?php echo esc_attr($chave); ?>"
                                aria-selected="<?php echo $chave === 'descricao' ? 'true' : 'false'; ?>">
                            <?php echo wp_kses($rotulo, ['span' => ['class' => []]]); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="livro-abas__painel livro-texto" data-painel="descricao">
                    <?php echo $c['descricao'] !== '' ? wp_kses_post(wpautop((string) $c['descricao'])) : '<p>Descrição em breve.</p>'; ?>
                </div>

                <?php if ($sumario) : ?>
                    <div class="livro-abas__painel" data-painel="sumario" hidden>
                        <?php foreach ($sumario as $secao) : ?>
                            <section class="livro-sumario">
                                <?php if ($secao['section'] !== '') : ?>
                                    <h3 class="livro-sumario__parte"><?php echo esc_html($secao['section']); ?></h3>
                                <?php endif; ?>
                                <ol class="livro-sumario__lista">
                                    <?php foreach ($secao['capitulos'] as $capitulo) : ?>
                                        <?php $paginas = conexao_doi_paginas($capitulo, '–'); ?>
                                        <li>
                                            <span class="livro-sumario__numero"><?php echo esc_html((string) ($capitulo['number'] ?: $capitulo['sequence'])); ?></span>
                                            <span class="livro-sumario__texto">
                                                <a href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $capitulo)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($capitulo)); ?></a>
                                                <?php $quem = conexao_doi_lista($capitulo['contributors'] ?? []); ?>
                                                <?php if ($quem) : ?>
                                                    <span class="livro-sumario__autores"><?php echo esc_html(implode(', ', array_column($quem, 'name'))); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if ($paginas !== '') : ?>
                                                <span class="livro-sumario__paginas">p. <?php echo esc_html($paginas); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($capitulos) : ?>
                    <div class="livro-abas__painel" data-painel="capitulos" hidden>
                        <ol class="livro-capitulos">
                            <?php foreach ($capitulos as $capitulo) : ?>
                                <?php $quem = conexao_doi_lista($capitulo['contributors'] ?? []); ?>
                                <li class="livro-capitulos__item">
                                    <span class="livro-capitulos__numero"><?php echo esc_html((string) ($capitulo['number'] ?: $capitulo['sequence'])); ?></span>
                                    <div class="livro-capitulos__texto">
                                        <h3><a href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $capitulo)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($capitulo)); ?></a></h3>
                                        <?php if ($quem) : ?>
                                            <p class="livro-capitulos__autores"><?php echo esc_html(implode(', ', array_column($quem, 'name'))); ?></p>
                                        <?php endif; ?>
                                        <p class="livro-capitulos__meta">
                                            <?php $paginas = conexao_doi_paginas($capitulo, '–'); ?>
                                            <?php if ($paginas !== '') : ?><span>p. <?php echo esc_html($paginas); ?></span><?php endif; ?>
                                            <?php if (! empty($capitulo['doi'])) : ?>
                                                <span>DOI: <a href="<?php echo esc_url('https://doi.org/'.$capitulo['doi']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) $capitulo['doi']); ?></a></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>

                <?php if (isset($abas['autores'])) : ?>
                    <div class="livro-abas__painel" data-painel="autores" hidden>
                        <?php if ($organizadores) : ?>
                            <h3 class="livro-secao"><?php echo esc_html(conexao_doi_rotulo_responsaveis($livro)); ?></h3>
                            <?php conexao_doi_pessoas_cards($organizadores); ?>
                        <?php endif; ?>
                        <?php if ($autores_capitulos) : ?>
                            <h3 class="livro-secao">Autores dos capítulos</h3>
                            <?php conexao_doi_pessoas_cards($autores_capitulos); ?>
                        <?php endif; ?>
                        <?php if (! $tem_doi) : ?>
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
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="livro-abas__painel" data-painel="detalhes" hidden>
                    <dl class="livro-detalhes">
                        <?php foreach ($c['ficha'] as $item) : ?>
                            <div><dt><?php echo esc_html($item['rotulo']); ?></dt><dd><?php echo esc_html($item['valor']); ?></dd></div>
                        <?php endforeach; ?>
                        <?php foreach ($c['biblio'] as $rotulo => $valor) : ?>
                            <?php if (in_array($rotulo, ['Páginas'], true)) { continue; } ?>
                            <div><dt><?php echo esc_html($rotulo); ?></dt><dd><?php echo esc_html((string) $valor); ?></dd></div>
                        <?php endforeach; ?>
                        <?php if (! empty($livro['series']['title'])) : ?>
                            <div><dt>Série</dt><dd><?php echo esc_html((string) $livro['series']['title']); ?><?php echo ! empty($livro['series']['issn']) ? ' (ISSN '.esc_html((string) $livro['series']['issn']).')' : ''; ?></dd></div>
                        <?php endif; ?>
                        <?php if ($produto && $produto->get_weight()) : ?>
                            <div><dt>Peso</dt><dd><?php echo esc_html(wc_format_weight((float) $produto->get_weight())); ?></dd></div>
                        <?php endif; ?>
                    </dl>

                    <?php if ($c['licencas']) : ?>
                        <h3 class="livro-secao">Direitos autorais</h3>
                        <ul class="livro-licencas">
                            <?php foreach ($c['licencas'] as $licenca) : ?>
                                <li class="livro-licenca">
                                    <span class="livro-licenca__selos" aria-hidden="true">
                                        <?php foreach ((array) ($licenca['elements'] ?? []) as $elemento) : ?>
                                            <span class="livro-licenca__selo"><?php echo esc_html($elemento === 'zero' ? '0' : strtoupper((string) $elemento)); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                    <span class="livro-licenca__texto">
                                        <strong><a href="<?php echo esc_url((string) ($licenca['url'] ?? '')); ?>" target="_blank" rel="license noopener"><?php echo esc_html((string) ($licenca['name'] ?? '')); ?></a></strong>
                                        <?php echo esc_html((string) ($licenca['statement'] ?? '')); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($tem_doi) : ?>
                        <h3 class="livro-secao" id="como-citar">Como citar este livro</h3>
                        <?php conexao_doi_bloco_citacoes(conexao_doi_citacoes_livro($livro), 'citar-livro'); ?>
                    <?php endif; ?>
                </div>

                <?php if ($referencias) : ?>
                    <div class="livro-abas__painel" data-painel="referencias" hidden>
                        <?php conexao_doi_referencias($referencias); ?>
                    </div>
                <?php endif; ?>

                <?php if ($com_avaliacoes) : ?>
                    <div class="livro-abas__painel" data-painel="avaliacoes" hidden>
                        <?php
                        // as avaliações são do produto da loja, também na página do DOI
                        global $post;
                        $post_original = $post;
                        $post = get_post($produto->get_id()); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
                        setup_postdata($post);
                        comments_template();
                        $post = $post_original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
                        wp_reset_postdata();
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="livro__lateral" aria-label="Compra e informações bibliográficas">
            <?php if ($produto) : ?>
                <?php get_template_part('template-parts/livro/compra', null, ['produto' => $produto]); ?>
            <?php elseif ($tem_doi && conexao_doi_ofertas($publicacao_id)) : ?>
                <?php conexao_doi_caixa_compra($publicacao_id); ?>
            <?php endif; ?>

            <?php if ($c['biblio']) : ?>
                <section class="livro-biblio" aria-labelledby="livro-biblio-titulo">
                    <h2 class="livro-biblio__titulo" id="livro-biblio-titulo">Informações bibliográficas</h2>
                    <dl>
                        <?php foreach ($c['biblio'] as $rotulo => $valor) : ?>
                            <div><dt><?php echo esc_html($rotulo); ?>:</dt><dd><?php echo esc_html((string) $valor); ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            <?php endif; ?>
        </aside>
    </div>

    <?php get_template_part('template-parts/livro/sugestoes', null, ['produto' => $produto]); ?>

    <?php if ($c['ficha_post']) : ?>
        <?php get_template_part('template-parts/livro/ficha-modal'); ?>
    <?php endif; ?>
</div>
<?php
get_footer();
