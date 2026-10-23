<?php
/**
 * Painel (index.php): sai o que é ruído (configuração e status do WooCommerce,
 * Rank Math, Cookie Notice, diagnóstico, rascunho rápido, notícias do
 * WordPress) e entram os widgets da Conexão: Loja hoje, Hub Editora,
 * Atividade, Avaliações recentes e Agora. Cabeçalho próprio no lugar do h1.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_ADMIN_WIDGETS_FORA = [
    'wc_admin_dashboard_setup',
    'woocommerce_dashboard_status',
    'woocommerce_dashboard_recent_reviews',
    'rank_math_dashboard_widget',
    'cn_dashboard_stats',
    'dashboard_site_health',
    'dashboard_right_now',
    'dashboard_activity',
    'dashboard_quick_press',
    'dashboard_primary',
];

// o núcleo pendura o painel de boas-vindas depois dos plugins: tira na hora de abrir o Painel
add_action('load-index.php', function (): void {
    remove_action('welcome_panel', 'wp_welcome_panel');
});

add_action('wp_dashboard_setup', function (): void {
    foreach (CONEXAO_ADMIN_WIDGETS_FORA as $id) {
        foreach (['normal', 'side', 'column3', 'column4'] as $contexto) {
            remove_meta_box($id, 'dashboard', $contexto);
        }
    }

    $widgets = [
        'cxa_loja' => ['Loja hoje', 'conexao_admin_widget_loja', 'Loja hoje', 'edit_shop_orders'],
        'cxa_hub' => ['Hub Editora', 'conexao_admin_widget_hub', 'Hub Editora', 'edit_products'],
        'cxa_atividade' => ['Atividade', 'conexao_admin_widget_atividade', 'Atividade', 'edit_posts'],
        'cxa_avaliacoes' => ['Avaliações recentes', 'conexao_admin_widget_avaliacoes', 'Avaliações recentes', 'moderate_comments'],
        'cxa_agora' => ['Agora', 'conexao_admin_widget_agora', 'Agora', 'edit_posts'],
    ];

    foreach ($widgets as $id => [$titulo, $funcao, $nome, $permissao]) {
        if (! current_user_can($permissao)) {
            continue;
        }

        if ($id === 'cxa_hub') {
            $titulo .= conexao_admin_selo_hub();
        } elseif ($id === 'cxa_avaliacoes') {
            $pendentes = conexao_admin_avaliacoes_pendentes();
            $titulo .= $pendentes ? sprintf(' <span class="cxa-selo cxa-selo--aviso">%d %s</span>', $pendentes, $pendentes === 1 ? 'pendente' : 'pendentes') : '';
        }

        wp_add_dashboard_widget($id, $titulo, $funcao);
        conexao_admin_nome_simples('dashboard', $id, $nome);
    }
}, PHP_INT_MAX);

/** O título com selo é HTML; em "Opções de tela" vale o nome simples. */
function conexao_admin_nome_simples(string $tela, string $id, string $nome): void
{
    global $wp_meta_boxes;

    foreach ((array) ($wp_meta_boxes[$tela] ?? []) as $contexto => $prioridades) {
        foreach ((array) $prioridades as $prioridade => $caixas) {
            if (! empty($caixas[$id]) && is_array($caixas[$id])) {
                $wp_meta_boxes[$tela][$contexto][$prioridade][$id]['args'] = array_merge((array) ($caixas[$id]['args'] ?? []), ['__widget_basename' => $nome]);
            }
        }
    }
}

function conexao_admin_avaliacoes_pendentes(): int
{
    return (int) get_comments(['type' => 'review', 'status' => 'hold', 'count' => true]);
}

// cabeçalho do Painel: fica antes dos avisos; o h1 do núcleo some pelo CSS
add_action('admin_notices', function (): void {
    $tela = get_current_screen();

    if (! $tela || $tela->id !== 'dashboard') {
        return;
    }

    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $partes = [ucfirst(wp_date('l, j \d\e F \d\e Y'))];

    if (defined('WC_VERSION')) {
        $partes[] = get_option('woocommerce_coming_soon') === 'yes' ? 'loja em modo "em breve"' : 'loja no ar';
        $partes[] = 'WooCommerce '.implode('.', array_slice(explode('.', WC_VERSION), 0, 2));
    }

    $hub = conexao_admin_dados_hub();
    ?>
    <div class="cxa-cabecalho">
        <div>
            <div class="cxa-eb cxa-eb--acento"><?php echo esc_html($host); ?></div>
            <h1 class="cxa-titulo">Painel</h1>
            <div class="cxa-sub"><?php echo esc_html(implode(' · ', $partes)); ?></div>
        </div>
        <div class="cxa-cabecalho__acoes">
            <a class="button" href="<?php echo esc_url($hub['url']); ?>" target="_blank" rel="noopener">Abrir Hub Editora ↗</a>
            <?php if (current_user_can('edit_products')) : ?>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>">+ Novo produto</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}, 1);

// ---- Widgets -------------------------------------------------------------------

function conexao_admin_widget_loja(): void
{
    $d = conexao_admin_dados_loja();
    $pedidos = $d['vendas_pedidos'];
    $max = max(1, max($d['serie']));
    $largura = 50;
    $total_pagos = array_sum($d['serie']);
    ?>
    <div class="cxa-kpis">
        <a class="cxa-kpi" href="<?php echo esc_url(admin_url('admin.php?page=wc-orders')); ?>">
            <span class="cxa-eb">Vendas hoje</span>
            <span class="cxa-kpi__n"><?php echo wp_kses_post(function_exists('wc_price') ? wc_price($d['vendas_total']) : number_format_i18n($d['vendas_total'], 2)); ?></span>
            <span class="cxa-sub"><?php echo esc_html(sprintf(_n('%d pedido pago', '%d pedidos pagos', $pedidos, 'conexao-admin'), $pedidos)); ?></span>
        </a>
        <a class="cxa-kpi" href="<?php echo esc_url(admin_url('admin.php?page=wc-orders&status=wc-processing')); ?>">
            <span class="cxa-eb">Pedidos em processamento</span>
            <span class="cxa-kpi__n <?php echo $d['processando'] ? 'cxa-cor-aviso' : ''; ?>"><?php echo esc_html(number_format_i18n($d['processando'])); ?></span>
            <span class="cxa-sub">a expedir</span>
        </a>
        <a class="cxa-kpi" href="<?php echo esc_url(admin_url('admin.php?page=wc-admin&path=/analytics/stock&type=lowstock')); ?>">
            <span class="cxa-eb">Estoque baixo</span>
            <span class="cxa-kpi__n <?php echo $d['estoque_baixo'] ? 'cxa-cor-perigo' : ''; ?>"><?php echo esc_html(number_format_i18n($d['estoque_baixo'])); ?></span>
            <span class="cxa-sub">até <?php echo esc_html((string) $d['limite_estoque']); ?> un.</span>
        </a>
        <?php $pendentes = conexao_admin_avaliacoes_pendentes(); ?>
        <a class="cxa-kpi" href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=product-reviews&review_status=moderated')); ?>">
            <span class="cxa-eb">Avaliações pendentes</span>
            <span class="cxa-kpi__n"><?php echo esc_html(number_format_i18n($pendentes)); ?></span>
            <span class="cxa-sub"><?php echo $pendentes ? 'moderar' : 'nada a moderar'; ?></span>
        </a>
    </div>
    <div class="cxa-grafico">
        <div class="cxa-grafico__legenda"><span>Pedidos pagos · últimos 14 dias</span><span class="cxa-mono"><?php echo esc_html(number_format_i18n($total_pagos)); ?> no período</span></div>
        <svg viewBox="0 0 <?php echo esc_attr((string) (14 * $largura)); ?> 90" width="100%" height="90" preserveAspectRatio="none" role="img" aria-label="Pedidos pagos por dia nos últimos 14 dias">
            <?php $i = 0; foreach ($d['serie'] as $dia => $n) : ?>
                <?php $altura = $n ? max(3, round(86 * $n / $max)) : 1; ?>
                <rect class="<?php echo $i === 13 ? 'cxa-barra cxa-barra--hoje' : 'cxa-barra'; ?>" x="<?php echo esc_attr((string) ($i * $largura + 7)); ?>" y="<?php echo esc_attr((string) (90 - $altura)); ?>" width="36" height="<?php echo esc_attr((string) $altura); ?>"><title><?php echo esc_html(wp_date('d/m', strtotime($dia)).': '.$n); ?></title></rect>
            <?php $i++; endforeach; ?>
        </svg>
    </div>
    <?php
}

function conexao_admin_selo_hub(): string
{
    $d = conexao_admin_dados_hub();
    $ativos = array_filter($d['webhooks'], static fn ($w) => $w['estado'] === 'active');
    $recente = $d['ultimo_acesso'] !== '' && strtotime(get_gmt_from_date($d['ultimo_acesso'])) > time() - 2 * DAY_IN_SECONDS;

    if ($d['webhooks'] && count($ativos) === count($d['webhooks']) && $recente) {
        return ' <span class="cxa-selo cxa-selo--ok">conectado</span>';
    }

    return ' <span class="cxa-selo cxa-selo--aviso">conferir</span>';
}

function conexao_admin_widget_hub(): void
{
    $d = conexao_admin_dados_hub();
    $linhas = [];

    if (! $d['webhooks']) {
        $linhas[] = ['aviso', 'Webhooks de pedidos', 'não cadastrados'];
    }

    foreach ($d['webhooks'] as $webhook) {
        $nome = trim((string) preg_replace('/^Hub Editora:\s*/i', '', $webhook['nome']));
        $estado = ['active' => 'ativo', 'paused' => 'pausado', 'disabled' => 'desativado'][$webhook['estado']] ?? $webhook['estado'];
        $linhas[] = [$webhook['estado'] === 'active' && ! $webhook['falhas'] ? 'ok' : 'aviso', 'Webhook: '.$nome, $estado.($webhook['falhas'] ? ' · '.$webhook['falhas'].' falhas' : '')];
    }

    $recente = $d['ultimo_acesso'] !== '' && strtotime(get_gmt_from_date($d['ultimo_acesso'])) > time() - 2 * DAY_IN_SECONDS;
    $linhas[] = [$recente ? 'ok' : 'aviso', 'Leitura da loja (API)', conexao_admin_ha_quanto($d['ultimo_acesso'])];
    $linhas[] = ['ok', 'Publicações DOI recebidas', sprintf('%d %s · %d cap.', $d['livros'], $d['livros'] === 1 ? 'livro' : 'livros', $d['capitulos'])];
    $linhas[] = [$d['nao_registrados'] ? 'aviso' : 'ok', 'DOIs ainda não registrados', (string) $d['nao_registrados']];
    ?>
    <ul class="cxa-lista-estado">
        <?php foreach ($linhas as [$tom, $rotulo, $valor]) : ?>
            <li><span class="cxa-ponto cxa-ponto--<?php echo esc_attr($tom); ?>"></span><?php echo esc_html($rotulo); ?><span class="cxa-sub <?php echo $tom === 'aviso' ? 'cxa-cor-aviso' : ''; ?>"><?php echo esc_html($valor); ?></span></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($d['ultimo_livro'] !== '') : ?>
        <p class="cxa-sub">Último livro recebido <?php echo esc_html(conexao_admin_ha_quanto($d['ultimo_livro'])); ?>.</p>
    <?php endif; ?>
    <p class="cxa-rodape-widget"><a href="<?php echo esc_url($d['url']); ?>" target="_blank" rel="noopener">Abrir o Hub →</a></p>
    <?php
}

function conexao_admin_widget_atividade(): void
{
    $recentes = get_posts([
        'post_type' => array_values(array_filter(['post', 'evento', 'product', 'page'], 'post_type_exists')),
        'post_status' => 'publish',
        'numberposts' => 4,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    ]);

    $comentarios = get_comments(['type' => 'comment', 'status' => 'approve', 'number' => 2]);
    ?>
    <div class="cxa-eb">Publicados recentemente</div>
    <?php if (! $recentes) : ?>
        <p class="cxa-sub">Nada publicado ainda.</p>
    <?php endif; ?>
    <ul class="cxa-lista-datas">
        <?php foreach ($recentes as $item) : ?>
            <li>
                <span class="cxa-mono cxa-sub"><?php echo esc_html(get_the_date('d/m H:i', $item)); ?></span>
                <a href="<?php echo esc_url((string) get_edit_post_link($item)); ?>"><?php echo esc_html(get_the_title($item) ?: '(sem título)'); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="cxa-eb cxa-eb--espaco">Comentários recentes</div>
    <?php if (! $comentarios) : ?>
        <p class="cxa-sub">Nenhum comentário recente.</p>
    <?php endif; ?>
    <?php foreach ($comentarios as $comentario) : ?>
        <div class="cxa-comentario">
            <b><?php echo esc_html(get_comment_author($comentario)); ?></b> em
            <a href="<?php echo esc_url((string) get_edit_post_link((int) $comentario->comment_post_ID)); ?>"><?php echo esc_html(get_the_title((int) $comentario->comment_post_ID)); ?></a>
            <div class="cxa-sub"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($comentario->comment_content), 10, '…')); ?></div>
        </div>
    <?php endforeach; ?>
    <?php
}

function conexao_admin_widget_avaliacoes(): void
{
    $avaliacoes = get_comments(['type' => 'review', 'status' => 'all', 'number' => 3]);
    $lista = admin_url('edit.php?post_type=product&page=product-reviews');

    if (! $avaliacoes) {
        echo '<p class="cxa-sub">Nenhuma avaliação ainda.</p>';

        return;
    }
    ?>
    <ul class="cxa-avaliacoes">
        <?php foreach ($avaliacoes as $avaliacao) : ?>
            <?php
            $nota = max(0, min(5, (int) get_comment_meta((int) $avaliacao->comment_ID, 'rating', true)));
            $pendente = $avaliacao->comment_approved === '0';
            ?>
            <li>
                <div class="cxa-avaliacoes__topo">
                    <span class="cxa-forte"><?php echo esc_html(get_the_title((int) $avaliacao->comment_post_ID)); ?></span>
                    <span class="cxa-estrelas" aria-label="<?php echo esc_attr($nota.' de 5 estrelas'); ?>"><?php echo esc_html(str_repeat('★', $nota).str_repeat('☆', 5 - $nota)); ?></span>
                </div>
                <div class="cxa-sub"><?php echo esc_html(get_comment_author($avaliacao)); ?> · "<?php echo esc_html(wp_trim_words(wp_strip_all_tags($avaliacao->comment_content), 9, '…')); ?>"</div>
                <div class="cxa-avaliacoes__acoes">
                    <?php if ($pendente) : ?>
                        <span class="cxa-selo cxa-selo--aviso">pendente</span>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('comment.php?action=approvecomment&c='.$avaliacao->comment_ID), 'approve-comment_'.$avaliacao->comment_ID)); ?>">Aprovar</a> ·
                    <?php endif; ?>
                    <a href="<?php echo esc_url($lista); ?>">Responder</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

function conexao_admin_widget_agora(): void
{
    $tema = wp_get_theme();
    $versao_tema = defined('CONEXAO_VERSION') ? CONEXAO_VERSION : $tema->get('Version');
    ?>
    <div class="cxa-agora">
        <?php foreach (conexao_admin_contagens() as $item) : ?>
            <a href="<?php echo esc_url(admin_url($item['url'])); ?>"><span class="cxa-mono cxa-agora__n"><?php echo esc_html(number_format_i18n($item['n'])); ?></span><span class="cxa-sub"><?php echo esc_html($item['rotulo']); ?></span></a>
        <?php endforeach; ?>
    </div>
    <p class="cxa-sub cxa-rodape-widget"><?php echo esc_html(sprintf('WordPress %s · tema %s %s · %s', get_bloginfo('version'), $tema->get('Name'), $versao_tema, get_locale())); ?></p>
    <?php
}
