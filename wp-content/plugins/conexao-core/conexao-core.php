<?php
/**
 * Plugin Name:       Conexão Core
 * Plugin URI:        https://conexaografica.com.br
 * Description:       Tipos de conteúdo, campos personalizados e shortcodes da Conexão Gráfica. Independente do tema.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Conexão Gráfica
 * Text Domain:       conexao
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CNX_VERSION', '0.1.0' );
define( 'CNX_FILE', __FILE__ );
define( 'CNX_PATH', plugin_dir_path( __FILE__ ) );
define( 'CNX_URL', plugin_dir_url( __FILE__ ) );

/**
 * Chaves centralizadas. Mudar aqui muda em todo o projeto.
 */
define( 'CNX_CPT_PRODUTO', 'cnx_produto' );
define( 'CNX_CPT_SLIDE', 'cnx_slide' );
define( 'CNX_CPT_BANNER', 'cnx_banner' );
define( 'CNX_CPT_LEAD', 'cnx_lead' );
define( 'CNX_TAX_CATEGORIA', 'cnx_categoria_produto' );
define( 'CNX_TAX_SOLUCAO', 'cnx_solucao' );

require_once CNX_PATH . 'includes/helpers.php';
require_once CNX_PATH . 'includes/post-types.php';
require_once CNX_PATH . 'includes/taxonomies.php';
require_once CNX_PATH . 'includes/meta-produto.php';
require_once CNX_PATH . 'includes/meta-slide.php';
require_once CNX_PATH . 'includes/meta-banner.php';
require_once CNX_PATH . 'includes/term-meta.php';
require_once CNX_PATH . 'includes/leads.php';
require_once CNX_PATH . 'includes/seo.php';
require_once CNX_PATH . 'includes/analytics.php';
require_once CNX_PATH . 'includes/performance.php';
require_once CNX_PATH . 'includes/shortcodes.php';
require_once CNX_PATH . 'includes/admin-columns.php';
require_once CNX_PATH . 'includes/admin-assets.php';
require_once CNX_PATH . 'includes/settings.php';
require_once CNX_PATH . 'includes/admin-hub.php';

/**
 * Na ativação registramos tudo e regravamos as regras de URL,
 * senão /produtos/nome-do-produto devolve 404 até salvar os permalinks na mão.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		cnx_register_post_types();
		cnx_register_taxonomies();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
