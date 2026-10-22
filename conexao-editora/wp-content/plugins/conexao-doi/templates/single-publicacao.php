<?php
/**
 * Página permanente do livro (destino do DOI), no layout do mockup
 * publico-livro.png: capa, cabeçalho com organizadores e identificadores,
 * abas (Sobre a obra, Sumário, Autores e organizadores, Capítulos, Detalhes,
 * Referências), lateral com compra, compartilhar, informações bibliográficas
 * e "Como citar", e os livros relacionados da mesma área.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

the_post();

$post_livro = get_post();
$livro_id = $post_livro->ID;
$dados = conexao_doi_dados_livro($livro_id);
$livro = $dados['book'] ?? [];
$url = get_permalink($post_livro);
$titulo = (string) ($livro['title'] ?? get_the_title());
$titulo_completo = conexao_doi_titulo_completo($livro);
$doi = (string) ($livro['doi'] ?? '');
$organizadores = conexao_doi_organizadores($livro);
$rotulo_resp = conexao_doi_rotulo_responsaveis($livro);
$sumario = conexao_doi_sumario($dados);
$capitulos = $dados['chapters'] ?? [];
usort($capitulos, static fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));
$fora = conexao_doi_fora_de_catalogo($livro_id);
$citacoes = conexao_doi_citacoes_livro($livro);
$relacionados = conexao_doi_relacionados($livro_id);
$ofertas = conexao_doi_ofertas($livro_id);
$loja = '';

foreach ($ofertas as $oferta) {
    if ($oferta['loja'] !== '') {
        $loja = $oferta['loja'];
        break;
    }
}

// todas as pessoas da obra (organizadores + autores de capítulo), uma vez cada
$todas_pessoas = conexao_doi_pessoas_da_publicacao($dados);
$autores_capitulos = array_values(array_filter($todas_pessoas, static function ($p) use ($organizadores) {
    foreach ($organizadores as $o) {
        if ((int) $o['hub_person_id'] === (int) $p['hub_person_id']) {
            return false;
        }
    }

    return true;
}));

$abas = [
    'sobre' => 'Sobre a obra',
    'sumario' => 'Sumário',
    'autores' => 'Autores e organizadores',
    'capitulos' => 'Capítulos',
    'detalhes' => 'Detalhes',
    'referencias' => 'Referências',
];

$area = (string) ($livro['subject_area'] ?? '');
$subarea = (string) ($livro['subject_subarea'] ?? '');
$shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/loja/');
$trilha = [['url' => $shop, 'texto' => 'Livros']];

if ($area !== '') {
    $trilha[] = ['url' => add_query_arg('s', $area, $shop), 'texto' => $area];
}

$detalhes = array_filter([
    'ISBN (impresso)' => $livro['isbn_print'] ?? '',
    'ISBN (e-book)' => $livro['isbn_ebook'] ?? '',
    'DOI' => $doi,
    'Ano de publicação' => $livro['publication_year'] ?? '',
    'Data de publicação' => conexao_doi_data_br($livro['publication_date'] ?? null),
    'Edição' => conexao_doi_edicao_texto($livro),
    'Páginas' => $livro['pages'] ?? '',
    'Formato' => conexao_doi_rotulo_formato((string) ($livro['format'] ?? '')),
    'Idioma' => conexao_doi_nome_idioma((string) ($livro['language'] ?? 'pt')),
    'Área' => trim($area.($subarea !== '' ? ' / '.$subarea : '')),
    'Público-alvo' => $livro['audience'] ?? '',
    'Editora' => $livro['publisher'] ?? '',
    'Local' => $livro['publication_place'] ?? '',
], static fn ($v) => (string) $v !== '');
?>
<div class="container doi-pagina doi-pagina--livro">
    <?php
    if (function_exists('conexao_trilha')) {
        conexao_trilha($titulo, $trilha);
    }
    ?>

    <?php if ($fora) : ?>
        <p class="doi-faixa" role="status"><?php conexao_doi_icone('info', 18); ?> Esta obra está fora de catálogo. A página permanece como registro permanente do DOI.</p>
    <?php endif; ?>

    <article class="doi-livro">
        <div class="doi-principal">
            <figure class="doi-capa">
                <?php echo conexao_doi_capa_html($livro_id, 'large', 'doi-capa__imagem'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php // sem "Ver na loja": a página do produto agora redireciona para esta (inc/loja.php) ?>
            </figure>

            <header class="doi-cabecalho">
                <p class="doi-etiqueta"><?php echo esc_html(conexao_doi_rotulo_tipo((string) ($livro['type'] ?? ''))); ?></p>
                <h1 class="doi-titulo"><?php echo esc_html($titulo); ?></h1>
                <?php if (! empty($livro['subtitle'])) : ?>
                    <p class="doi-subtitulo"><?php echo esc_html((string) $livro['subtitle']); ?></p>
                <?php endif; ?>
                <?php if (! empty($livro['alt_title'])) : ?>
                    <p class="doi-titulo-alt" lang="<?php echo esc_attr((string) ($livro['alt_title_lang'] ?? 'en')); ?>"><?php echo esc_html((string) $livro['alt_title']); ?></p>
                <?php endif; ?>

                <?php if ($organizadores) : ?>
                    <div class="doi-responsaveis">
                        <h2 class="doi-responsaveis__titulo"><?php echo esc_html($rotulo_resp); ?></h2>
                        <?php conexao_doi_pessoas_inline($organizadores, false); ?>
                    </div>
                <?php endif; ?>

                <ul class="doi-metricas">
                    <?php if (! empty($livro['publication_year'])) : ?>
                        <li><?php conexao_doi_icone('calendario', 22); ?><span><strong><?php echo esc_html((string) $livro['publication_year']); ?></strong><small>Ano de publicação</small></span></li>
                    <?php endif; ?>
                    <?php if (! empty($livro['pages'])) : ?>
                        <li><?php conexao_doi_icone('paginas', 22); ?><span><strong><?php echo (int) $livro['pages']; ?></strong><small>Páginas</small></span></li>
                    <?php endif; ?>
                    <?php if (! empty($livro['format'])) : ?>
                        <li><?php conexao_doi_icone('formato', 22); ?><span><strong><?php echo esc_html(conexao_doi_rotulo_formato((string) $livro['format'])); ?></strong><small>Formato</small></span></li>
                    <?php endif; ?>
                    <?php if ($area !== '') : ?>
                        <li><?php conexao_doi_icone('area', 22); ?><span><strong><?php echo esc_html($area.($subarea !== '' ? ' / '.$subarea : '')); ?></strong><small>Área</small></span></li>
                    <?php endif; ?>
                </ul>

                <dl class="doi-ids">
                    <?php if ($doi !== '') : ?>
                        <div class="doi-ids__item doi-ids__item--doi">
                            <dt>DOI do livro</dt>
                            <dd><?php conexao_doi_doi_html($doi); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($livro['isbn_print'])) : ?>
                        <div class="doi-ids__item"><dt>ISBN (impresso)</dt><dd><?php echo esc_html((string) $livro['isbn_print']); ?></dd></div>
                    <?php endif; ?>
                    <?php if (! empty($livro['isbn_ebook'])) : ?>
                        <div class="doi-ids__item"><dt>ISBN (e-book)</dt><dd><?php echo esc_html((string) $livro['isbn_ebook']); ?></dd></div>
                    <?php endif; ?>
                </dl>

                <?php conexao_doi_palavras_chave((array) ($livro['keywords'] ?? [])); ?>
            </header>

            <div class="doi-abas" data-doi-abas data-doi-abas-principal>
                <?php conexao_doi_abas_botoes($abas, 'livro'); ?>

                <?php conexao_doi_aba_abre('sobre', 'livro', true); ?>
                    <h2 class="doi-secao__titulo">Sobre a obra</h2>
                    <div class="doi-texto">
                        <?php if (! empty($livro['synopsis'])) : ?>
                            <p class="doi-texto__destaque"><?php echo esc_html((string) $livro['synopsis']); ?></p>
                        <?php endif; ?>
                        <?php if (! empty($livro['abstract'])) : ?>
                            <?php echo wpautop(esc_html((string) $livro['abstract'])); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <?php endif; ?>
                        <?php if (! empty($livro['abstract_en'])) : ?>
                            <details class="doi-abstract-en">
                                <summary>Abstract (English)</summary>
                                <?php echo wpautop(esc_html((string) $livro['abstract_en'])); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </details>
                        <?php endif; ?>
                        <?php if (empty($livro['synopsis']) && empty($livro['abstract'])) : ?>
                            <p class="doi-vazio">Resumo em breve.</p>
                        <?php endif; ?>
                    </div>

                    <?php if (! empty($livro['audience'])) : ?>
                        <p class="doi-publico"><?php conexao_doi_icone('pessoa', 18); ?> <strong>Público-alvo:</strong> <?php echo esc_html((string) $livro['audience']); ?></p>
                    <?php endif; ?>

                    <?php if ($sumario) : ?>
                        <div class="doi-secao">
                            <div class="doi-secao__topo">
                                <h2 class="doi-secao__titulo">Sumário</h2>
                                <a class="doi-secao__mais" href="#sumario" data-doi-ir="sumario">Ver sumário completo <?php conexao_doi_icone('seta-direita', 16); ?></a>
                            </div>
                            <ul class="doi-sumario-resumo">
                                <?php foreach ($sumario as $n => $secao) : ?>
                                    <?php $quantos = count($secao['capitulos']); ?>
                                    <li>
                                        <details>
                                            <summary>
                                                <span class="doi-sumario-resumo__parte"><?php echo esc_html($secao['section'] !== '' ? $secao['section'] : 'Capítulos'); ?></span>
                                                <span class="doi-sumario-resumo__qtd"><?php echo $quantos; ?> <?php echo $quantos === 1 ? 'capítulo' : 'capítulos'; ?></span>
                                            </summary>
                                            <ol class="doi-sumario-resumo__lista">
                                                <?php foreach ($secao['capitulos'] as $capitulo) : ?>
                                                    <li><a href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $capitulo)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($capitulo)); ?></a></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </details>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($organizadores) : ?>
                        <div class="doi-secao">
                            <div class="doi-secao__topo">
                                <h2 class="doi-secao__titulo"><?php echo esc_html($rotulo_resp); ?></h2>
                                <?php if ($autores_capitulos) : ?>
                                    <a class="doi-secao__mais" href="#autores" data-doi-ir="autores">Ver todos os autores <?php conexao_doi_icone('seta-direita', 16); ?></a>
                                <?php endif; ?>
                            </div>
                            <?php conexao_doi_pessoas_cards($organizadores); ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php conexao_doi_aba_abre('sumario', 'livro', false); ?>
                    <h2 class="doi-secao__titulo">Sumário</h2>
                    <?php if (! $sumario) : ?>
                        <p class="doi-vazio">O sumário ainda não foi informado.</p>
                    <?php endif; ?>
                    <?php foreach ($sumario as $secao) : ?>
                        <section class="doi-sumario__secao">
                            <?php if ($secao['section'] !== '') : ?>
                                <h3 class="doi-sumario__parte"><?php echo esc_html($secao['section']); ?></h3>
                            <?php endif; ?>
                            <ol class="doi-sumario__lista">
                                <?php foreach ($secao['capitulos'] as $capitulo) : ?>
                                    <?php $autores = conexao_doi_lista($capitulo['contributors'] ?? []); ?>
                                    <li class="doi-sumario__item">
                                        <span class="doi-sumario__numero" aria-hidden="true"><?php echo esc_html((string) ($capitulo['number'] ?: $capitulo['sequence'])); ?></span>
                                        <div class="doi-sumario__texto">
                                            <a class="doi-sumario__titulo" href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $capitulo)); ?>">
                                                <span class="tela-leitor">Capítulo <?php echo esc_html((string) ($capitulo['number'] ?: $capitulo['sequence'])); ?>: </span><?php echo esc_html(conexao_doi_titulo_completo($capitulo)); ?>
                                            </a>
                                            <?php if ($autores) : ?>
                                                <span class="doi-sumario__autores"><?php echo esc_html(implode(', ', array_column($autores, 'name'))); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php $paginas = conexao_doi_paginas($capitulo, '–'); ?>
                                        <?php if ($paginas !== '') : ?>
                                            <span class="doi-sumario__paginas">p. <?php echo esc_html($paginas); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endforeach; ?>
                </section>

                <?php conexao_doi_aba_abre('autores', 'livro', false); ?>
                    <?php if ($organizadores) : ?>
                        <h2 class="doi-secao__titulo"><?php echo esc_html($rotulo_resp); ?></h2>
                        <?php conexao_doi_pessoas_cards($organizadores); ?>
                    <?php endif; ?>
                    <?php if ($autores_capitulos) : ?>
                        <h2 class="doi-secao__titulo">Autores dos capítulos</h2>
                        <?php conexao_doi_pessoas_cards($autores_capitulos); ?>
                    <?php endif; ?>
                    <?php if (! $organizadores && ! $autores_capitulos) : ?>
                        <p class="doi-vazio">Sem autores informados.</p>
                    <?php endif; ?>
                </section>

                <?php conexao_doi_aba_abre('capitulos', 'livro', false); ?>
                    <h2 class="doi-secao__titulo">Capítulos</h2>
                    <?php if (! $capitulos) : ?>
                        <p class="doi-vazio">Os capítulos ainda não foram publicados.</p>
                    <?php endif; ?>
                    <ol class="doi-capitulos">
                        <?php foreach ($capitulos as $capitulo) : ?>
                            <?php $autores = conexao_doi_lista($capitulo['contributors'] ?? []); ?>
                            <li class="doi-capitulos__item">
                                <span class="doi-capitulos__numero" aria-hidden="true"><?php echo esc_html((string) ($capitulo['number'] ?: $capitulo['sequence'])); ?></span>
                                <div class="doi-capitulos__texto">
                                    <h3 class="doi-capitulos__titulo">
                                        <a href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $capitulo)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($capitulo)); ?></a>
                                    </h3>
                                    <?php if ($autores) : ?>
                                        <p class="doi-capitulos__autores"><?php echo esc_html(implode(', ', array_column($autores, 'name'))); ?></p>
                                    <?php endif; ?>
                                    <p class="doi-capitulos__meta">
                                        <?php $paginas = conexao_doi_paginas($capitulo, '–'); ?>
                                        <?php if ($paginas !== '') : ?><span>p. <?php echo esc_html($paginas); ?></span><?php endif; ?>
                                        <?php if (! empty($capitulo['doi'])) : ?>
                                            <span>DOI: <a href="<?php echo esc_url('https://doi.org/'.$capitulo['doi']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) $capitulo['doi']); ?></a></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <a class="doi-capitulos__ir" href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $capitulo)); ?>" aria-label="Abrir o capítulo <?php echo esc_attr((string) ($capitulo['number'] ?: $capitulo['sequence'])); ?>"><?php conexao_doi_icone('chevron', 18); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>

                <?php conexao_doi_aba_abre('detalhes', 'livro', false); ?>
                    <h2 class="doi-secao__titulo">Detalhes</h2>
                    <dl class="doi-detalhes">
                        <?php foreach ($detalhes as $rotulo => $valor) : ?>
                            <div class="doi-detalhes__item">
                                <dt><?php echo esc_html($rotulo); ?></dt>
                                <dd>
                                    <?php if ($rotulo === 'DOI') : ?>
                                        <?php conexao_doi_doi_html($doi); ?>
                                    <?php else : ?>
                                        <?php echo esc_html((string) $valor); ?>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                        <?php if (! empty($livro['series']['title'])) : ?>
                            <div class="doi-detalhes__item"><dt>Série</dt><dd><?php echo esc_html((string) $livro['series']['title']); ?><?php echo ! empty($livro['series']['issn']) ? ' (ISSN '.esc_html((string) $livro['series']['issn']).')' : ''; ?></dd></div>
                        <?php endif; ?>
                        <?php if (! empty($livro['volume'])) : ?>
                            <div class="doi-detalhes__item"><dt>Volume</dt><dd><?php echo esc_html((string) $livro['volume']); ?></dd></div>
                        <?php endif; ?>
                    </dl>
                    <?php conexao_doi_palavras_chave((array) ($livro['keywords_en'] ?? []), 'Keywords'); ?>
                </section>

                <?php conexao_doi_aba_abre('referencias', 'livro', false); ?>
                    <h2 class="doi-secao__titulo">Referências</h2>
                    <?php conexao_doi_referencias((array) ($livro['references'] ?? [])); ?>
                </section>
            </div>

            <?php if ($relacionados) : ?>
                <section class="doi-relacionados" aria-labelledby="doi-relacionados-titulo">
                    <div class="doi-secao__topo">
                        <h2 class="doi-secao__titulo" id="doi-relacionados-titulo">Livros relacionados</h2>
                        <?php if ($area !== '') : ?>
                            <a class="doi-secao__mais" href="<?php echo esc_url(add_query_arg('s', $area, $shop)); ?>">Ver mais livros <?php conexao_doi_icone('seta-direita', 16); ?></a>
                        <?php endif; ?>
                    </div>
                    <ul class="doi-relacionados__lista">
                        <?php foreach ($relacionados as $outro) : ?>
                            <?php $dados_outro = conexao_doi_livro($outro->ID); ?>
                            <li class="doi-relacionado">
                                <a class="doi-relacionado__capa" href="<?php echo esc_url(get_permalink($outro)); ?>" tabindex="-1" aria-hidden="true">
                                    <?php echo conexao_doi_capa_html($outro->ID, 'medium', 'doi-relacionado__imagem'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                </a>
                                <div>
                                    <h3 class="doi-relacionado__titulo"><a href="<?php echo esc_url(get_permalink($outro)); ?>"><?php echo esc_html((string) ($dados_outro['title'] ?? $outro->post_title)); ?></a></h3>
                                    <?php $resp = conexao_doi_organizadores($dados_outro); ?>
                                    <?php if ($resp) : ?>
                                        <p class="doi-relacionado__autores"><?php echo esc_html(implode(', ', array_slice(array_column($resp, 'name'), 0, 2))); ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>

        <aside class="doi-lateral" aria-label="Compra e citação">
            <?php conexao_doi_caixa_compra($livro_id); ?>

            <section class="doi-cartao doi-cartao--compartilhar" aria-labelledby="doi-compartilhar-titulo">
                <h2 class="doi-cartao__titulo" id="doi-compartilhar-titulo">Compartilhe esta obra</h2>
                <?php conexao_doi_compartilhar($url, $titulo_completo); ?>
            </section>

            <section class="doi-cartao" aria-labelledby="doi-biblio-titulo">
                <h2 class="doi-cartao__titulo" id="doi-biblio-titulo">Informações bibliográficas</h2>
                <dl class="doi-biblio">
                    <?php foreach ($detalhes as $rotulo => $valor) : ?>
                        <?php if (in_array($rotulo, ['Editora', 'Local', 'Idioma', 'Data de publicação'], true)) { continue; } ?>
                        <div class="doi-biblio__item">
                            <dt><?php echo esc_html($rotulo); ?></dt>
                            <dd><?php echo esc_html((string) $valor); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="doi-cartao" aria-labelledby="doi-citar-titulo" id="como-citar">
                <h2 class="doi-cartao__titulo" id="doi-citar-titulo">Como citar este livro</h2>
                <?php conexao_doi_bloco_citacoes($citacoes, 'citar-livro'); ?>
            </section>
        </aside>
    </article>
</div>
<?php
get_footer();
