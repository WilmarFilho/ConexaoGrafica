<?php
/**
 * Formulário de busca. Usado pelo header e por get_search_form() em geral.
 */

defined( 'ABSPATH' ) || exit;

$cnx_search_id = wp_unique_id( 'cnx-busca-' );
?>

<form class="cnx-busca" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $cnx_search_id ); ?>">
		<?php esc_html_e( 'Buscar no site', 'conexao' ); ?>
	</label>

	<input type="search"
		id="<?php echo esc_attr( $cnx_search_id ); ?>"
		class="cnx-busca__input"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Procure por produtos, serviços ou palavra-chave...', 'conexao' ); ?>">

	<button type="submit" class="cnx-busca__botao" aria-label="<?php esc_attr_e( 'Buscar', 'conexao' ); ?>">
		<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
			stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
			<circle cx="11" cy="11" r="7"/>
			<path d="m20 20-3.5-3.5"/>
		</svg>
	</button>
</form>
