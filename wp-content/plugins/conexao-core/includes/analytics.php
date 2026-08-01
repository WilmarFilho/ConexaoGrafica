<?php
/**
 * GA4 + Google Tag Manager com Consent Mode v2.
 *
 * A ordem no <head> é a alma disto: primeiro o consentimento padrão (negado),
 * depois GTM/gtag. Assim nenhuma tag grava cookie antes de o visitante decidir
 * no aviso de cookies — que é o que a nossa Política de Cookies promete e a
 * LGPD espera. Quem já decidiu tem a escolha reaplicada antes das tags.
 */

defined( 'ABSPATH' ) || exit;

function cnx_ga4_id(): string {
	$id = trim( (string) get_option( 'cnx_ga4_id', '' ) );

	return preg_match( '/^G-[A-Z0-9]{4,}$/i', $id ) ? $id : '';
}

function cnx_gtm_id(): string {
	$id = trim( (string) get_option( 'cnx_gtm_id', '' ) );

	return preg_match( '/^GTM-[A-Z0-9]{4,}$/i', $id ) ? $id : '';
}

/**
 * Consentimento padrão + tags. Prioridade 2: antes de tudo que imprime script.
 */
add_action( 'wp_head', 'cnx_analytics_head', 2 );

function cnx_analytics_head(): void {
	$ga4 = cnx_ga4_id();
	$gtm = cnx_gtm_id();

	if ( '' === $ga4 && '' === $gtm ) {
		return;
	}
	?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){ dataLayer.push(arguments); }
gtag('consent', 'default', {
	ad_storage: 'denied',
	ad_user_data: 'denied',
	ad_personalization: 'denied',
	analytics_storage: 'denied',
	wait_for_update: 500
});
/* Escolha anterior do visitante, reaplicada antes de qualquer tag. */
(function () {
	try {
		if ('granted' === localStorage.getItem('cnx_consentimento')) {
			gtag('consent', 'update', {
				ad_storage: 'granted',
				ad_user_data: 'granted',
				ad_personalization: 'granted',
				analytics_storage: 'granted'
			});
		}
	} catch (e) {}
})();
</script>
	<?php if ( '' !== $gtm ) : ?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $gtm ); ?>');</script>
	<?php endif; ?>
	<?php if ( '' !== $ga4 ) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4 ); ?>"></script>
<script>gtag('js', new Date());gtag('config', '<?php echo esc_js( $ga4 ); ?>');</script>
	<?php endif; ?>
	<?php
}

/**
 * O <noscript> do GTM, logo após a abertura do body.
 */
add_action( 'wp_body_open', 'cnx_analytics_noscript' );

function cnx_analytics_noscript(): void {
	$gtm = cnx_gtm_id();

	if ( '' === $gtm ) {
		return;
	}

	printf(
		'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n",
		esc_attr( $gtm )
	);
}
