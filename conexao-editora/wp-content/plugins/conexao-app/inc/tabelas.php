<?php
/**
 * Tabelas do app. Datas gravadas em UTC (a saída converte para o fuso do
 * site). Nada aqui guarda o token em si: só o SHA-256 dele.
 */

if (! defined('ABSPATH')) {
    exit;
}

function conexao_app_tabela(string $nome): string
{
    global $wpdb;

    return $wpdb->prefix.'conexao_app_'.$nome;
}

function conexao_app_cria_tabelas(): void
{
    global $wpdb;

    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    // cada token é um aparelho; id_local é o identificador que o app gera na
    // instalação, para um novo login no mesmo aparelho não ocupar outra vaga
    dbDelta('CREATE TABLE '.conexao_app_tabela('aparelhos')." (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        token_hash char(64) NOT NULL,
        nome varchar(80) NOT NULL DEFAULT '',
        plataforma varchar(10) NOT NULL DEFAULT 'web',
        id_local varchar(64) NOT NULL DEFAULT '',
        criado_em datetime NOT NULL,
        usado_em datetime NOT NULL,
        ip varchar(45) NOT NULL DEFAULT '',
        revogado_em datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY token_hash (token_hash),
        KEY usuario (user_id, revogado_em)
    ) $charset;");

    dbDelta('CREATE TABLE '.conexao_app_tabela('leitura')." (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        produto_id bigint(20) unsigned NOT NULL,
        pagina int(10) unsigned NOT NULL DEFAULT 1,
        total_paginas int(10) unsigned NOT NULL DEFAULT 0,
        atualizado_em datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY usuario_produto (user_id, produto_id)
    ) $charset;");

    dbDelta('CREATE TABLE '.conexao_app_tabela('marcadores')." (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        produto_id bigint(20) unsigned NOT NULL,
        pagina int(10) unsigned NOT NULL,
        criado_em datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY usuario_produto_pagina (user_id, produto_id, pagina)
    ) $charset;");
}

/** Agora em UTC, no formato do MySQL. */
function conexao_app_agora(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Data UTC do banco → ISO 8601 no fuso do site (o contrato pede o fuso). */
function conexao_app_iso(?string $utc): ?string
{
    if (! $utc || str_starts_with($utc, '0000')) {
        return null;
    }

    $ts = strtotime($utc.' UTC');

    return $ts ? wp_date('c', $ts) : null;
}

/** Data do WooCommerce (WC_DateTime) → ISO 8601 no fuso do site. */
function conexao_app_iso_wc($data): ?string
{
    return $data instanceof WC_DateTime ? wp_date('c', $data->getTimestamp()) : null;
}
