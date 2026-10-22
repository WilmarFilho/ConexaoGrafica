<?php
/**
 * Página permanente do capítulo (destino do DOI), no layout do mockup
 * publico-capitulo.png: número e DOI, título, autores com sobrescrito de
 * afiliação e ORCID, ações (PDF da ficha, Citar, Compartilhar), abas (Resumo,
 * Detalhes, Como citar, Autores, Referências, Conteúdo do livro), lateral com
 * capa e compra do livro e a lista de capítulos; navegação anterior/próximo.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

the_post();

$post_capitulo = get_post();
$capitulo = conexao_doi_capitulo($post_capitulo->ID);
$post_livro = conexao_doi_livro_do_capitulo($post_capitulo);
$dados = $post_livro ? conexao_doi_dados_livro($post_livro->ID) : ['book' => [], 'chapters' => []];
$livro = $dados['book'] ?? [];
$livro_id = $post_livro ? $post_livro->ID : 0;
$url = get_permalink($post_capitulo);
$titulo = (string) ($capitulo['title'] ?? get_the_title());
$titulo_completo = conexao_doi_titulo_completo($capitulo);
$doi = (string) ($capitulo['doi'] ?? '');
$numero = (string) (($capitulo['number'] ?? '') ?: ($capitulo['sequence'] ?? ''));
$autores = conexao_doi_lista($capitulo['contributors'] ?? []);
$organizadores = conexao_doi_organizadores($livro);
$citacoes = conexao_doi_citacoes_capitulo($capitulo, $livro);
$pdf = conexao_doi_documento_url($capitulo);
$sumario = conexao_doi_sumario($dados);
$fora_livro = $livro_id ? conexao_doi_fora_de_catalogo($livro_id) : false;
// capítulo que saiu da edição depois do depósito: a página fica, sem compra
$fora_capitulo = get_post_meta($post_capitulo->ID, CONEXAO_DOI_META_STATUS, true) === 'unpublished';
$fora = $fora_livro || $fora_capitulo;
$area = (string) ($livro['subject_area'] ?? '');
$subarea = (string) ($livro['subject_subarea'] ?? '');

// capítulos na ordem, para a lateral e a navegação
$todos = $dados['chapters'] ?? [];
usort($todos, static fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));
$posicao = null;

foreach ($todos as $i => $c) {
    if ((int) ($c['hub_id'] ?? 0) === (int) ($capitulo['hub_id'] ?? -1)) {
        $posicao = $i;
        break;
    }
}

$anterior = ($posicao !== null && $posicao > 0) ? $todos[$posicao - 1] : null;
$proximo = ($posicao !== null && isset($todos[$posicao + 1])) ? $todos[$posicao + 1] : null;

$abas = [
    'resumo' => 'Resumo',
    'detalhes' => 'Detalhes',
    'como-citar' => 'Como citar',
    'autores' => 'Autores',
    'referencias' => 'Referências',
    'conteudo' => 'Conteúdo do livro',
];

$shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/loja/');
$trilha = [['url' => $shop, 'texto' => 'Livros']];

if ($post_livro) {
    $trilha[] = ['url' => get_permalink($post_livro), 'texto' => (string) ($livro['title'] ?? $post_livro->post_title)];
}

$detalhes = array_filter([
    'Páginas' => ['paginas', conexao_doi_paginas($capitulo, '–')],
    'DOI' => ['doi', $doi],
    'Data de publicação' => ['calendario', conexao_doi_data_br($capitulo['publication_date'] ?? $livro['publication_date'] ?? null) ?: (string) ($livro['publication_year'] ?? '')],
    'Idioma' => ['idioma', conexao_doi_nome_idioma((string) ($capitulo['language'] ?? $livro['language'] ?? 'pt'))],
    'Formato' => ['formato', 'Capítulo de livro'],
    'ISBN da obra' => ['isbn', (string) (($livro['isbn_print'] ?? '') ?: ($livro['isbn_ebook'] ?? ''))],
    'Área de conhecimento' => ['area', $area],
    'Subárea' => ['tag', $subarea],
], static fn ($v) => $v[1] !== '');
?>
<div class="container doi-pagina doi-pagina--capitulo">
    <?php
    if (function_exists('conexao_trilha')) {
        conexao_trilha('Capítulo '.$numero, $trilha);
    }
    ?>

    <?php if ($fora) : ?>
        <p class="doi-faixa" role="status"><?php conexao_doi_icone('info', 18); ?> <?php echo $fora_livro ? 'A obra está fora de catálogo.' : 'Este capítulo não faz mais parte da edição em catálogo.'; ?> A página permanece como registro permanente do DOI.</p>
    <?php endif; ?>

    <article class="doi-capitulo">
        <div class="doi-principal">
            <header class="doi-cabecalho doi-cabecalho--capitulo">
                <div class="doi-cabecalho__linha">
                    <div class="doi-selos">
                        <span class="doi-etiqueta doi-etiqueta--forte">Capítulo <?php echo esc_html($numero); ?></span>
                        <?php if ($doi !== '') : ?>
                            <span class="doi-etiqueta doi-etiqueta--doi">DOI: <?php conexao_doi_doi_html($doi, 'doi-doi doi-doi--inline'); ?></span>
                        <?php endif; ?>
                    </div>

                    <ul class="doi-acoes" aria-label="Ações">
                        <li>
                            <?php if ($pdf !== '') : ?>
                                <a class="doi-acao" href="<?php echo esc_url($pdf); ?>" target="_blank" rel="noopener noreferrer"><?php conexao_doi_icone('pdf', 22); ?><span>PDF</span></a>
                            <?php else : ?>
                                <span class="doi-acao doi-acao--inativa" title="A ficha em PDF ainda não está disponível" aria-disabled="true"><?php conexao_doi_icone('pdf', 22); ?><span>PDF</span></span>
                            <?php endif; ?>
                        </li>
                        <li><a class="doi-acao" href="#como-citar" data-doi-ir="como-citar"><?php conexao_doi_icone('citar', 22); ?><span>Citar</span></a></li>
                        <li><a class="doi-acao" href="#compartilhar"><?php conexao_doi_icone('compartilhar', 22); ?><span>Compartilhar</span></a></li>
                    </ul>
                </div>

                <h1 class="doi-titulo"><?php echo esc_html($titulo); ?></h1>
                <?php if (! empty($capitulo['subtitle'])) : ?>
                    <p class="doi-subtitulo"><?php echo esc_html((string) $capitulo['subtitle']); ?></p>
                <?php endif; ?>
                <?php if (! empty($capitulo['title_en'])) : ?>
                    <p class="doi-titulo-alt" lang="en"><?php echo esc_html((string) $capitulo['title_en']); ?></p>
                <?php endif; ?>

                <?php if ($autores) : ?>
                    <div class="doi-autoria">
                        <h2 class="tela-leitor">Autores</h2>
                        <?php conexao_doi_pessoas_inline($autores, true); ?>
                        <a class="doi-autoria__mais" href="#autores" data-doi-ir="autores">Ver informações dos autores</a>
                    </div>
                <?php endif; ?>
            </header>

            <div class="doi-abas" data-doi-abas data-doi-abas-principal>
                <?php conexao_doi_abas_botoes($abas, 'capitulo'); ?>

                <?php conexao_doi_aba_abre('resumo', 'capitulo', true); ?>
                    <h2 class="doi-secao__titulo">Resumo</h2>
                    <div class="doi-texto">
                        <?php if (! empty($capitulo['abstract'])) : ?>
                            <?php echo wpautop(esc_html((string) $capitulo['abstract'])); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <?php else : ?>
                            <p class="doi-vazio">Resumo em breve.</p>
                        <?php endif; ?>
                    </div>
                    <?php conexao_doi_palavras_chave((array) ($capitulo['keywords'] ?? [])); ?>

                    <div class="doi-secao">
                        <h2 class="doi-secao__titulo">Detalhes do capítulo</h2>
                        <ul class="doi-ficha">
                            <?php foreach ($detalhes as $rotulo => [$icone, $valor]) : ?>
                                <li>
                                    <span class="doi-ficha__icone"><?php conexao_doi_icone($icone, 20); ?></span>
                                    <span class="doi-ficha__texto">
                                        <small><?php echo esc_html($rotulo); ?></small>
                                        <?php if ($rotulo === 'DOI') : ?>
                                            <?php conexao_doi_doi_html($doi); ?>
                                        <?php else : ?>
                                            <strong><?php echo esc_html($valor); ?></strong>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>

                <?php conexao_doi_aba_abre('detalhes', 'capitulo', false); ?>
                    <h2 class="doi-secao__titulo">Detalhes</h2>
                    <dl class="doi-detalhes">
                        <?php foreach ($detalhes as $rotulo => [$icone, $valor]) : ?>
                            <div class="doi-detalhes__item">
                                <dt><?php echo esc_html($rotulo); ?></dt>
                                <dd><?php if ($rotulo === 'DOI') { conexao_doi_doi_html($doi); } else { echo esc_html($valor); } ?></dd>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($numero !== '') : ?>
                            <div class="doi-detalhes__item"><dt>Número do capítulo</dt><dd><?php echo esc_html($numero); ?></dd></div>
                        <?php endif; ?>
                        <?php if (! empty($capitulo['section'])) : ?>
                            <div class="doi-detalhes__item"><dt>Seção do livro</dt><dd><?php echo esc_html((string) $capitulo['section']); ?></dd></div>
                        <?php endif; ?>
                        <?php if ($post_livro) : ?>
                            <div class="doi-detalhes__item"><dt>Obra</dt><dd><a href="<?php echo esc_url(get_permalink($post_livro)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($livro)); ?></a></dd></div>
                        <?php endif; ?>
                        <?php if (! empty($livro['doi'])) : ?>
                            <div class="doi-detalhes__item"><dt>DOI da obra</dt><dd><?php conexao_doi_doi_html((string) $livro['doi']); ?></dd></div>
                        <?php endif; ?>
                        <?php if (! empty($livro['publisher'])) : ?>
                            <div class="doi-detalhes__item"><dt>Editora</dt><dd><?php echo esc_html((string) $livro['publisher']); ?><?php echo ! empty($livro['publication_place']) ? ', '.esc_html((string) $livro['publication_place']) : ''; ?></dd></div>
                        <?php endif; ?>
                    </dl>
                </section>

                <?php conexao_doi_aba_abre('como-citar', 'capitulo', false); ?>
                    <h2 class="doi-secao__titulo">Como citar este capítulo</h2>
                    <?php conexao_doi_bloco_citacoes($citacoes, 'citar-capitulo'); ?>
                </section>

                <?php conexao_doi_aba_abre('autores', 'capitulo', false); ?>
                    <h2 class="doi-secao__titulo">Autores deste capítulo</h2>
                    <?php conexao_doi_pessoas_cards($autores); ?>
                </section>

                <?php conexao_doi_aba_abre('referencias', 'capitulo', false); ?>
                    <h2 class="doi-secao__titulo">Referências</h2>
                    <?php conexao_doi_referencias((array) ($capitulo['references'] ?? [])); ?>
                </section>

                <?php conexao_doi_aba_abre('conteudo', 'capitulo', false); ?>
                    <h2 class="doi-secao__titulo">Conteúdo do livro</h2>
                    <?php if (! $sumario || ! $post_livro) : ?>
                        <p class="doi-vazio">O sumário ainda não foi informado.</p>
                    <?php endif; ?>
                    <?php foreach ($sumario as $secao) : ?>
                        <section class="doi-sumario__secao">
                            <?php if ($secao['section'] !== '') : ?>
                                <h3 class="doi-sumario__parte"><?php echo esc_html($secao['section']); ?></h3>
                            <?php endif; ?>
                            <ol class="doi-sumario__lista">
                                <?php foreach ($secao['capitulos'] as $c) : ?>
                                    <?php $atual = (int) ($c['hub_id'] ?? 0) === (int) ($capitulo['hub_id'] ?? -1); ?>
                                    <li class="doi-sumario__item<?php echo $atual ? ' e-atual' : ''; ?>"<?php echo $atual ? ' aria-current="page"' : ''; ?>>
                                        <span class="doi-sumario__numero" aria-hidden="true"><?php echo esc_html((string) ($c['number'] ?: $c['sequence'])); ?></span>
                                        <div class="doi-sumario__texto">
                                            <a class="doi-sumario__titulo" href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $c)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($c)); ?></a>
                                            <?php $a = conexao_doi_lista($c['contributors'] ?? []); ?>
                                            <?php if ($a) : ?>
                                                <span class="doi-sumario__autores"><?php echo esc_html(implode(', ', array_column($a, 'name'))); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php $paginas = conexao_doi_paginas($c, '–'); ?>
                                        <?php if ($paginas !== '') : ?>
                                            <span class="doi-sumario__paginas">p. <?php echo esc_html($paginas); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endforeach; ?>
                </section>
            </div>

            <section class="doi-cartao doi-cartao--compartilhar doi-cartao--largo" id="compartilhar" aria-labelledby="doi-compartilhar-titulo">
                <h2 class="doi-cartao__titulo" id="doi-compartilhar-titulo">Compartilhe este capítulo</h2>
                <?php conexao_doi_compartilhar($url, $titulo_completo); ?>
            </section>

            <?php if ($post_livro && ($anterior || $proximo)) : ?>
                <nav class="doi-navegacao" aria-label="Capítulos vizinhos">
                    <?php if ($anterior) : ?>
                        <a class="doi-navegacao__link doi-navegacao__link--anterior" href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $anterior)); ?>" rel="prev">
                            <?php conexao_doi_icone('seta-esquerda', 18); ?>
                            <span><small>Capítulo anterior</small><strong><?php echo esc_html(conexao_doi_titulo_completo($anterior)); ?></strong></span>
                        </a>
                    <?php else : ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if ($proximo) : ?>
                        <a class="doi-navegacao__link doi-navegacao__link--proximo" href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $proximo)); ?>" rel="next">
                            <span><small>Próximo capítulo</small><strong><?php echo esc_html(conexao_doi_titulo_completo($proximo)); ?></strong></span>
                            <?php conexao_doi_icone('seta-direita', 18); ?>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>

        <aside class="doi-lateral" aria-label="Livro e capítulos">
            <?php if ($post_livro) : ?>
                <section class="doi-cartao doi-cartao--livro" aria-labelledby="doi-livro-titulo">
                    <p class="doi-etiqueta">Livro</p>
                    <a class="doi-cartao__capa" href="<?php echo esc_url(get_permalink($post_livro)); ?>" tabindex="-1" aria-hidden="true">
                        <?php echo conexao_doi_capa_html($livro_id, 'large', 'doi-cartao__imagem'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </a>
                    <h2 class="doi-cartao__livro" id="doi-livro-titulo"><a href="<?php echo esc_url(get_permalink($post_livro)); ?>"><?php echo esc_html((string) ($livro['title'] ?? $post_livro->post_title)); ?></a></h2>
                    <?php if (! empty($livro['subtitle'])) : ?>
                        <p class="doi-cartao__subtitulo"><?php echo esc_html((string) $livro['subtitle']); ?></p>
                    <?php endif; ?>
                    <?php if ($organizadores) : ?>
                        <p class="doi-cartao__rotulo"><?php echo esc_html(conexao_doi_rotulo_responsaveis($livro)); ?></p>
                        <p class="doi-cartao__nomes"><?php echo conexao_doi_nomes_pontuados($organizadores); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
                    <?php endif; ?>

                    <?php conexao_doi_caixa_compra($livro_id, true, $fora_capitulo); ?>

                    <dl class="doi-biblio">
                        <?php
                        $biblio = array_filter([
                            'ISBN (impresso)' => $livro['isbn_print'] ?? '',
                            'ISBN (e-book)' => $livro['isbn_ebook'] ?? '',
                            'Ano de publicação' => $livro['publication_year'] ?? '',
                            'Edição' => conexao_doi_edicao_texto($livro),
                            'Páginas' => $livro['pages'] ?? '',
                            'Área' => trim($area.($subarea !== '' ? ' / '.$subarea : '')),
                        ], static fn ($v) => (string) $v !== '');
                        ?>
                        <?php foreach ($biblio as $rotulo => $valor) : ?>
                            <div class="doi-biblio__item"><dt><?php echo esc_html($rotulo); ?></dt><dd><?php echo esc_html((string) $valor); ?></dd></div>
                        <?php endforeach; ?>
                    </dl>

                    <a class="doi-cartao__link" href="<?php echo esc_url(get_permalink($post_livro)); ?>">Ver página completa do livro <?php conexao_doi_icone('seta-direita', 16); ?></a>
                </section>

                <?php if ($todos) : ?>
                    <section class="doi-cartao doi-cartao--capitulos" aria-labelledby="doi-capitulos-titulo">
                        <h2 class="doi-cartao__titulo" id="doi-capitulos-titulo">Capítulos do livro</h2>
                        <?php
                        // a janela: o atual e os vizinhos, até 7 itens
                        $inicio = max(0, min($posicao ?? 0, count($todos) - 7));
                        $janela = array_slice($todos, $inicio, 7, true);
                        ?>
                        <ol class="doi-lista-capitulos">
                            <?php foreach ($janela as $c) : ?>
                                <?php $atual = (int) ($c['hub_id'] ?? 0) === (int) ($capitulo['hub_id'] ?? -1); ?>
                                <?php $a = conexao_doi_lista($c['contributors'] ?? []); ?>
                                <li class="doi-lista-capitulos__item<?php echo $atual ? ' e-atual' : ''; ?>"<?php echo $atual ? ' aria-current="page"' : ''; ?>>
                                    <span class="doi-lista-capitulos__numero" aria-hidden="true"><?php echo esc_html((string) ($c['number'] ?: $c['sequence'])); ?></span>
                                    <div>
                                        <a href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $c)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($c)); ?></a>
                                        <?php if ($a) : ?>
                                            <small><?php echo esc_html(implode(', ', array_column($a, 'name'))); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <a class="btn btn--contorno btn--bloco" href="<?php echo esc_url(get_permalink($post_livro).'#capitulos'); ?>">Ver todos os capítulos <?php conexao_doi_icone('seta-direita', 16); ?></a>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </aside>
    </article>
</div>
<?php
get_footer();
