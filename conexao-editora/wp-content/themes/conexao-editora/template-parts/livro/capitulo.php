<?php
/**
 * Página do capítulo (Figma "Site - Conexão Editora", quadro 2281:568). Recebe
 * em $args o contexto do livro (conexao_livro_contexto) mais o capítulo.
 * A grade é a da página do livro: coluna de 1111 e lateral de 398.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $args */
$c = $args;
$produto = $c['produto'];
$livro = (array) $c['livro'];
$dados = (array) $c['dados'];
$capitulo = (array) $c['capitulo'];
/** @var WP_Post|null $post_livro */
$post_livro = $c['post_livro'];
/** @var WP_Post $post_capitulo */
$post_capitulo = $c['post_capitulo'];
$livro_id = $post_livro ? $post_livro->ID : 0;

$titulo = (string) ($capitulo['title'] ?? get_the_title($post_capitulo));
$titulo_completo = conexao_doi_titulo_completo($capitulo) ?: $titulo;
$doi = (string) ($capitulo['doi'] ?? '');
$numero = (string) (($capitulo['number'] ?? '') ?: ($capitulo['sequence'] ?? ''));
$autores = conexao_doi_lista($capitulo['contributors'] ?? []);
$url = (string) get_permalink($post_capitulo);
$pdf = conexao_doi_documento_url($capitulo);
$livro_url = $post_livro ? (string) get_permalink($post_livro) : '';
$livro_titulo = $livro ? conexao_doi_titulo_completo($livro) : ($post_livro ? $post_livro->post_title : '');
$fora = ($livro_id && conexao_doi_fora_de_catalogo($livro_id))
    || get_post_meta($post_capitulo->ID, CONEXAO_DOI_META_STATUS, true) === 'unpublished';

// capítulos na ordem: o sumário, a lista da lateral e a posição do atual
$todos = (array) ($dados['chapters'] ?? []);
usort($todos, static fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));
$posicao = 0;

foreach ($todos as $i => $item) {
    if ((int) ($item['hub_id'] ?? 0) === (int) ($capitulo['hub_id'] ?? -1)) {
        $posicao = $i;
        break;
    }
}

$sumario = $dados ? conexao_doi_sumario($dados) : [];
$organizadores = $livro ? conexao_doi_organizadores($livro) : [];
$referencias = (array) ($capitulo['references'] ?? []);
$avaliacoes = $produto ? (int) $produto->get_review_count() : 0;
$com_avaliacoes = $produto && (comments_open($produto->get_id()) || $avaliacoes);
$palavras = array_values(array_filter(array_map('strval', (array) ($capitulo['keywords'] ?? [])))) ?: (array) $c['palavras'];

$descricao = trim((string) ($capitulo['abstract'] ?? ''));

$abas = ['descricao' => 'Descrição'];

if ($sumario) {
    $abas['sumario'] = 'Sumário';
}

if ($organizadores || $autores) {
    $abas['autores'] = 'Autores e Organizadores';
}

if ($referencias) {
    $abas['referencias'] = 'Referências';
}

if ($com_avaliacoes) {
    $abas['avaliacoes'] = sprintf('Avaliações <span class="livro-abas__qtd">(%d)</span>', $avaliacoes);
}

// os oito detalhes do livro; o número de páginas passa a ser o do capítulo
$ficha = (array) $c['ficha'];
$primeira = (int) ($capitulo['first_page'] ?? 0);
$ultima = (int) ($capitulo['last_page'] ?? 0);

if ($primeira > 0 && $ultima >= $primeira) {
    foreach ($ficha as &$item) {
        if ($item['icone'] === 'paginas') {
            $item['valor'] = sprintf(_n('%d página', '%d páginas', $ultima - $primeira + 1, 'conexao'), $ultima - $primeira + 1);
        }
    }
    unset($item);
}

// trilha: Home › área (ou categoria da loja) › livro › capítulo
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

$citacoes = $livro ? conexao_doi_citacoes_capitulo($capitulo, $livro) : [];

/** "Autor: Ana" ou "Autores: Ana, Bia" para a lista da lateral. */
$quem_escreveu = static function (array $pessoas): string {
    if (! $pessoas) {
        return '';
    }

    return (count($pessoas) > 1 ? 'Autores: ' : 'Autor: ').implode(', ', array_column($pessoas, 'name'));
};

$orcid_png = get_template_directory_uri().'/assets/img/produto/orcid.png';

get_header();
?>
<div class="container pagina pagina--livro pagina--capitulo">
    <?php if ($fora) : ?>
        <p class="livro-faixa" role="status">Este capítulo está fora de catálogo. A página permanece como registro permanente do DOI.</p>
    <?php endif; ?>

    <div class="livro capitulo">
        <div class="livro__principal">
            <?php conexao_trilha(trim($livro_titulo.' › Capítulo '.$numero, ' ›'), $niveis); ?>

            <header class="capitulo__topo">
                <div class="capitulo__selos">
                    <span class="capitulo__pilula">Capítulo <?php echo esc_html($numero); ?></span>
                    <?php if ($doi !== '') : ?>
                        <a class="capitulo__pilula capitulo__doi" href="<?php echo esc_url('https://doi.org/'.$doi); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($doi); ?></a>
                        <button class="capitulo__copiar" type="button" data-copiar="<?php echo esc_attr('https://doi.org/'.$doi); ?>" aria-label="Copiar o link do DOI" title="Copiar o link do DOI">
                            <?php echo conexao_livro_svg('copiar-cinza'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <span class="doi-copiar__feito livro__copiar-feito" aria-live="polite"></span>
                        </button>
                    <?php endif; ?>
                </div>

                <h1 class="capitulo__titulo"><?php echo esc_html($titulo_completo); ?></h1>

                <?php if ($autores) : ?>
                    <p class="capitulo__autores">
                        <span class="capitulo__autores-rotulo"><?php echo count($autores) > 1 ? 'Autores:' : 'Autor:'; ?></span>
                        <?php foreach ($autores as $i => $pessoa) : ?>
                            <?php $pagina = conexao_doi_pessoa_url($pessoa); ?>
                            <span class="capitulo__autor">
                                <?php if ($pagina !== '') : ?>
                                    <a href="<?php echo esc_url($pagina); ?>"><?php echo esc_html((string) $pessoa['name']); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html((string) $pessoa['name']); ?>
                                <?php endif; ?>
                                <?php if (! empty($pessoa['orcid'])) : ?>
                                    <a class="capitulo__orcid" href="<?php echo esc_url('https://orcid.org/'.$pessoa['orcid']); ?>" target="_blank" rel="noopener noreferrer"
                                       aria-label="<?php echo esc_attr('ORCID de '.$pessoa['name']); ?>"><img src="<?php echo esc_url($orcid_png); ?>" width="24" height="24" alt=""></a>
                                <?php endif; ?><?php echo $i < count($autores) - 1 ? '<span class="capitulo__virgula">,</span>' : ''; ?>
                            </span>
                        <?php endforeach; ?>
                    </p>
                    <a class="capitulo__ver-autores" href="#autores-do-capitulo">Ver informações dos autores</a>
                <?php endif; ?>

                <ul class="capitulo__acoes" aria-label="Ações">
                    <li>
                        <button class="capitulo__acao" type="button" data-favoritar="<?php echo esc_attr($url); ?>" aria-pressed="false">
                            <span class="capitulo__acao-caixa"><?php echo conexao_livro_svg('favoritar'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            <span class="capitulo__acao-rotulo">Favoritar</span>
                        </button>
                    </li>
                    <li>
                        <button class="capitulo__acao compartilhar" type="button" data-compartilhar
                                data-url="<?php echo esc_url($url); ?>" data-titulo="<?php echo esc_attr($titulo_completo); ?>">
                            <span class="capitulo__acao-caixa"><?php echo conexao_livro_svg('compartilhar-acao'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            <span class="capitulo__acao-rotulo">Compartilhar</span>
                        </button>
                    </li>
                    <li>
                        <?php if ($pdf !== '') : ?>
                            <a class="capitulo__acao" href="<?php echo esc_url($pdf); ?>" target="_blank" rel="noopener" data-ficha title="Ficha do capítulo (PDF)">
                                <span class="capitulo__acao-caixa"><?php echo conexao_livro_svg('ficha'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                <span class="capitulo__acao-rotulo">PDF</span>
                            </a>
                        <?php else : ?>
                            <span class="capitulo__acao capitulo__acao--inativa" aria-disabled="true" title="A ficha em PDF ainda não está disponível">
                                <span class="capitulo__acao-caixa"><?php echo conexao_livro_svg('ficha'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                <span class="capitulo__acao-rotulo">PDF</span>
                            </span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <a class="capitulo__acao" href="#como-citar">
                            <span class="capitulo__acao-caixa"><?php echo conexao_livro_svg('citar'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            <span class="capitulo__acao-rotulo">Citar</span>
                        </a>
                    </li>
                </ul>
            </header>

            <?php if ($palavras) : ?>
                <div class="livro__palavras capitulo__palavras">
                    <span class="livro__palavras-rotulo">Palavras-chave:</span>
                    <ul>
                        <?php foreach ($palavras as $palavra) : ?>
                            <li><?php echo esc_html($palavra); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="livro-abas capitulo-abas" data-abas>
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
                    <?php echo $descricao !== '' ? wp_kses_post(wpautop(esc_html($descricao))) : '<p>Resumo em breve.</p>'; ?>
                </div>

                <?php if ($sumario) : ?>
                    <div class="livro-abas__painel" data-painel="sumario" hidden>
                        <?php foreach ($sumario as $secao) : ?>
                            <section class="livro-sumario">
                                <?php if ($secao['section'] !== '') : ?>
                                    <h3 class="livro-sumario__parte"><?php echo esc_html($secao['section']); ?></h3>
                                <?php endif; ?>
                                <ol class="livro-sumario__lista">
                                    <?php foreach ($secao['capitulos'] as $item) : ?>
                                        <?php $atual = (int) ($item['hub_id'] ?? 0) === (int) ($capitulo['hub_id'] ?? -1); ?>
                                        <?php $paginas = conexao_doi_paginas($item, '–'); ?>
                                        <li<?php echo $atual ? ' class="e-atual" aria-current="page"' : ''; ?>>
                                            <span class="livro-sumario__numero"><?php echo esc_html((string) ($item['number'] ?: $item['sequence'])); ?></span>
                                            <span class="livro-sumario__texto">
                                                <a href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $item)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($item)); ?></a>
                                                <?php $quem = conexao_doi_lista($item['contributors'] ?? []); ?>
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

                <?php if (isset($abas['autores'])) : ?>
                    <div class="livro-abas__painel" data-painel="autores" hidden>
                        <?php if ($autores) : ?>
                            <h3 class="livro-secao"><?php echo count($autores) > 1 ? 'Autores do capítulo' : 'Autor do capítulo'; ?></h3>
                            <?php conexao_doi_pessoas_cards($autores); ?>
                        <?php endif; ?>
                        <?php if ($organizadores) : ?>
                            <h3 class="livro-secao"><?php echo esc_html(conexao_doi_rotulo_responsaveis($livro)); ?> do livro</h3>
                            <?php conexao_doi_pessoas_cards($organizadores); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($referencias) : ?>
                    <div class="livro-abas__painel" data-painel="referencias" hidden>
                        <?php conexao_doi_referencias($referencias); ?>
                    </div>
                <?php endif; ?>

                <?php if ($com_avaliacoes) : ?>
                    <div class="livro-abas__painel" data-painel="avaliacoes" hidden>
                        <?php
                        // as avaliações são as do produto do livro
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

            <section class="capitulo-secao" aria-labelledby="capitulo-detalhes-titulo">
                <h2 class="capitulo-secao__titulo" id="capitulo-detalhes-titulo">Detalhes do capítulo</h2>

                <?php if ($ficha) : ?>
                    <ul class="livro__ficha capitulo__ficha">
                        <?php foreach ($ficha as $item) : ?>
                            <li>
                                <span class="livro__ficha-icone"><?php echo conexao_livro_svg($item['icone']); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                <strong><?php echo esc_html($item['rotulo']); ?></strong>
                                <span><?php echo esc_html($item['valor']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($doi !== '') : ?>
                    <div class="capitulo__doi-linha">
                        <img class="capitulo__doi-logo" src="<?php echo esc_url(get_template_directory_uri().'/assets/img/produto/doi-logo.png'); ?>" width="34" height="34" alt="DOI">
                        <a class="capitulo__pilula capitulo__doi capitulo__doi--largo" href="<?php echo esc_url('https://doi.org/'.$doi); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($doi); ?></a>
                        <button class="capitulo__copiar" type="button" data-copiar="<?php echo esc_attr('https://doi.org/'.$doi); ?>" aria-label="Copiar o link do DOI" title="Copiar o link do DOI">
                            <?php echo conexao_livro_svg('copiar-cinza'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <span class="doi-copiar__feito livro__copiar-feito" aria-live="polite"></span>
                        </button>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($citacoes) : ?>
                <section class="capitulo-secao capitulo-citar" id="como-citar" aria-labelledby="capitulo-citar-titulo">
                    <h2 class="capitulo-secao__titulo" id="capitulo-citar-titulo">Como citar esse capítulo</h2>

                    <div class="capitulo-citar__abas" role="tablist" aria-label="Formato da citação">
                        <?php $i = 0; foreach ($citacoes as $chave => $citacao) : ?>
                            <button class="capitulo-citar__aba<?php echo $i === 0 ? ' e-ativa' : ''; ?>" type="button" role="tab"
                                    id="citar-capitulo-aba-<?php echo esc_attr($chave); ?>" aria-controls="citar-capitulo-<?php echo esc_attr($chave); ?>"
                                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" tabindex="<?php echo $i === 0 ? '0' : '-1'; ?>">
                                <?php echo esc_html($citacao['rotulo']); ?>
                            </button>
                        <?php $i++; endforeach; ?>
                    </div>

                    <?php $i = 0; foreach ($citacoes as $chave => $citacao) : ?>
                        <div class="capitulo-citar__painel" id="citar-capitulo-<?php echo esc_attr($chave); ?>" role="tabpanel"
                             aria-labelledby="citar-capitulo-aba-<?php echo esc_attr($chave); ?>" tabindex="0"<?php echo $i > 0 ? ' hidden' : ''; ?>>
                            <?php if ($citacao['bloco']) : ?>
                                <pre class="capitulo-citar__texto capitulo-citar__texto--bloco"><?php echo $citacao['html']; // phpcs:ignore WordPress.Security.EscapeOutput ?></pre>
                            <?php else : ?>
                                <p class="capitulo-citar__texto"><?php echo wp_kses($citacao['html'], ['b' => [], 'i' => []]); ?></p>
                            <?php endif; ?>
                            <button class="capitulo__copiar" type="button" data-copiar="<?php echo esc_attr($citacao['texto']); ?>" aria-label="Copiar a citação" title="Copiar a citação">
                                <?php echo conexao_livro_svg('copiar-cinza'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                <span class="doi-copiar__feito livro__copiar-feito" aria-live="polite"></span>
                            </button>
                        </div>
                    <?php $i++; endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ($autores) : ?>
                <section class="capitulo-secao" id="autores-do-capitulo" aria-labelledby="capitulo-autores-titulo">
                    <h2 class="capitulo-secao__titulo" id="capitulo-autores-titulo">Autores desse capítulo</h2>

                    <ul class="capitulo-autores">
                        <?php foreach ($autores as $pessoa) : ?>
                            <?php
                            $pagina = conexao_doi_pessoa_url($pessoa);
                            $termo = conexao_doi_pessoa_termo($pessoa);
                            $bio = $termo ? trim(wp_strip_all_tags((string) $termo->description)) : '';
                            $afiliacao = (array) ($pessoa['affiliation'] ?? []);
                            $local = implode(', ', array_filter([
                                (string) ($afiliacao['city'] ?? ''),
                                (string) ($afiliacao['state'] ?? ''),
                                ! empty($afiliacao['country']) ? conexao_doi_nome_pais((string) $afiliacao['country']) : '',
                            ]));
                            $abre = $bio !== '' || $pagina !== '';
                            ?>
                            <li class="capitulo-autor">
                                <details class="capitulo-autor__sanfona"<?php echo $abre ? '' : ' data-fechada'; ?>>
                                    <summary class="capitulo-autor__linha"<?php echo $abre ? '' : ' tabindex="-1"'; ?>>
                                        <span class="capitulo-autor__avatar"><?php echo conexao_doi_avatar($pessoa); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                                        <span class="capitulo-autor__nome-bloco">
                                            <span class="capitulo-autor__nome"><?php echo esc_html((string) $pessoa['name']); ?></span>
                                            <?php if (! empty($pessoa['orcid'])) : ?>
                                                <a class="capitulo-autor__orcid" href="<?php echo esc_url('https://orcid.org/'.$pessoa['orcid']); ?>" target="_blank" rel="noopener noreferrer">
                                                    <img src="<?php echo esc_url($orcid_png); ?>" width="17" height="17" alt="ORCID">
                                                    <span><?php echo esc_html('https://orcid.org/'.$pessoa['orcid']); ?></span>
                                                </a>
                                            <?php endif; ?>
                                        </span>
                                        <span class="capitulo-autor__vinculo">
                                            <?php if (! empty($afiliacao['name'])) : ?>
                                                <span>Instituição: <?php echo esc_html((string) $afiliacao['name']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($local !== '') : ?>
                                                <span>Localização: <?php echo esc_html($local); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($abre) : ?>
                                            <svg class="capitulo-autor__seta" width="16" height="9" viewBox="0 0 16 9" fill="none" aria-hidden="true" focusable="false"><path d="M1 1l7 7 7-7" stroke="#777" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <?php endif; ?>
                                    </summary>
                                    <?php if ($abre) : ?>
                                        <div class="capitulo-autor__mais">
                                            <?php if ($bio !== '') : ?>
                                                <p><?php echo esc_html($bio); ?></p>
                                            <?php endif; ?>
                                            <?php if ($pagina !== '') : ?>
                                                <a href="<?php echo esc_url($pagina); ?>">Ver página do autor</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>

        <aside class="livro__lateral capitulo__lateral" aria-label="O livro do capítulo">
            <?php if ($post_livro) : ?>
                <a class="capitulo-livro" href="<?php echo esc_url($livro_url); ?>" aria-label="<?php echo esc_attr('Livro: '.$livro_titulo); ?>">
                    <span class="capitulo__pilula capitulo-livro__selo">Livro</span>
                    <?php echo conexao_doi_capa_html($livro_id, 'large', 'capitulo-livro__capa'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
            <?php endif; ?>

            <?php if ($produto) : ?>
                <?php get_template_part('template-parts/livro/compra', null, ['produto' => $produto, 'frete' => false]); ?>
            <?php elseif ($livro_id && conexao_doi_ofertas($livro_id)) : ?>
                <?php conexao_doi_caixa_compra($livro_id, true, $fora); ?>
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

            <?php if ($todos && $post_livro) : ?>
                <?php
                // cinco capítulos: o atual e os vizinhos
                $inicio = max(0, min($posicao - 2, count($todos) - 5));
                $janela = array_slice($todos, $inicio, 5);
                ?>
                <section class="capitulo-lista" aria-labelledby="capitulo-lista-titulo">
                    <h2 class="livro-biblio__titulo" id="capitulo-lista-titulo">Capítulos do livro</h2>
                    <ol class="capitulo-lista__itens">
                        <?php foreach ($janela as $item) : ?>
                            <?php $atual = (int) ($item['hub_id'] ?? 0) === (int) ($capitulo['hub_id'] ?? -1); ?>
                            <li class="capitulo-lista__item<?php echo $atual ? ' e-atual' : ''; ?>"<?php echo $atual ? ' aria-current="page"' : ''; ?>>
                                <span class="capitulo-lista__numero" aria-hidden="true"><?php echo esc_html((string) ($item['number'] ?: $item['sequence'])); ?></span>
                                <span class="capitulo-lista__texto">
                                    <a href="<?php echo esc_url(conexao_doi_url_capitulo($post_livro, $item)); ?>"><?php echo esc_html(conexao_doi_titulo_completo($item)); ?></a>
                                    <?php $quem = $quem_escreveu(conexao_doi_lista($item['contributors'] ?? [])); ?>
                                    <?php if ($quem !== '') : ?>
                                        <small><?php echo esc_html($quem); ?></small>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <a class="capitulo-lista__todos" href="<?php echo esc_url($livro_url.'#capitulos'); ?>">Ver todos os capítulos</a>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>
<?php
get_footer();
