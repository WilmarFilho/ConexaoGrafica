<?php
/**
 * Faixa de categorias em destaque (desktop).
 *
 * No mobile ela dá lugar ao menu lateral — ver template-parts/header/menu-mobile.php.
 */

defined( 'ABSPATH' ) || exit;
?>

<nav class="cnx-categorias" aria-label="<?php esc_attr_e( 'Categorias em destaque', 'conexao' ); ?>">
	<div class="cnx-categorias__inner">
		<?php
		get_template_part(
			'template-parts/header/lista-categorias',
			null,
			array( 'classe' => 'cnx-categorias__lista' )
		);
		?>
	</div>
</nav>
