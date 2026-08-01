<?php
/**
 * Aviso de cookies.
 *
 * Só aparece quando há medição configurada e o visitante ainda não decidiu —
 * a decisão fica no localStorage e o app.js aplica via Consent Mode. Sem GA4 e
 * sem GTM não há cookie de medição nenhum, então não há o que perguntar.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'cnx_ga4_id' ) || ( '' === cnx_ga4_id() && '' === cnx_gtm_id() ) ) {
	return;
}
?>

<div class="cnx-cookies" data-cnx-cookies hidden role="region"
	aria-label="<?php esc_attr_e( 'Aviso de cookies', 'conexao' ); ?>">
	<p class="cnx-cookies__texto">
		<?php esc_html_e( 'Usamos cookies para melhorar sua experiência e medir o uso do site.', 'conexao' ); ?>
		<a href="<?php echo esc_url( home_url( '/politica-de-cookies/' ) ); ?>">
			<?php esc_html_e( 'Política de Cookies', 'conexao' ); ?>
		</a>
	</p>

	<div class="cnx-cookies__acoes">
		<button type="button" class="cnx-cookies__recusar" data-cnx-cookies-recusar>
			<?php esc_html_e( 'Somente essenciais', 'conexao' ); ?>
		</button>
		<button type="button" class="cnx-cookies__aceitar" data-cnx-cookies-aceitar>
			<?php esc_html_e( 'Aceitar', 'conexao' ); ?>
		</button>
	</div>
</div>
