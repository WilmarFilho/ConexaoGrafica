<?php
/**
 * Par de setas de um trilho de cards.
 *
 * Nascem com hidden: o JS decide quando cada lado tem conteúdo. No desktop as
 * grades não rolam, então as duas continuam escondidas.
 */

defined( 'ABSPATH' ) || exit;
?>

<button type="button" class="cnx-seta cnx-seta--anterior" data-cnx-rolar="-1"
	aria-label="<?php esc_attr_e( 'Anterior', 'conexao' ); ?>" hidden>
	<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
		stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
		<path d="m15 18-6-6 6-6"/>
	</svg>
</button>

<button type="button" class="cnx-seta cnx-seta--proximo" data-cnx-rolar="1"
	aria-label="<?php esc_attr_e( 'Próximo', 'conexao' ); ?>" hidden>
	<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
		stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
		<path d="m9 18 6-6-6-6"/>
	</svg>
</button>
