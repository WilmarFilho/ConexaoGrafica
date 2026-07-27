<?php
/**
 * Lista de categorias do header — usada pela faixa do desktop e pelo menu mobile.
 *
 * Fonte única: se existir um menu em "Categorias do header", ele manda (é o jeito
 * de controlar rótulo e ordem); senão, as categorias de produto de primeiro nível.
 *
 * @var array $args { @type string $classe  @type int $limite }
 */

defined( 'ABSPATH' ) || exit;

$cnx_classe = (string) ( $args['classe'] ?? 'cnx-categorias__lista' );
$cnx_limite = (int) ( $args['limite'] ?? 7 );

if ( has_nav_menu( 'categorias' ) ) {
	wp_nav_menu(
		array(
			'theme_location' => 'categorias',
			'container'      => false,
			'menu_class'     => $cnx_classe,
			'depth'          => 1,
		)
	);

	return;
}

$cnx_termos = get_terms(
	array(
		'taxonomy'   => 'cnx_categoria_produto',
		'parent'     => 0,
		'hide_empty' => false,
		'orderby'    => 'name',
	)
);

$cnx_termos = is_wp_error( $cnx_termos )
	? array()
	: array_slice( cnx_ordenar_termos( $cnx_termos ), 0, $cnx_limite );

if ( empty( $cnx_termos ) ) {
	return;
}
?>

<ul class="<?php echo esc_attr( $cnx_classe ); ?>">
	<?php foreach ( $cnx_termos as $cnx_termo ) : ?>
		<li>
			<a href="<?php echo esc_url( (string) get_term_link( $cnx_termo ) ); ?>">
				<?php echo esc_html( $cnx_termo->name ); ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
