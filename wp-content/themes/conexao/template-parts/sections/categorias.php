<?php
/**
 * Categorias em destaque. Vem de [cnx_categorias].
 *
 * @var array $args { @type string $titulo  @type WP_Term[] $termos }
 */

defined( 'ABSPATH' ) || exit;

$cnx_titulo = (string) ( $args['titulo'] ?? '' );
$cnx_termos = $args['termos'] ?? array();

if ( empty( $cnx_termos ) ) {
	return;
}
?>

<section class="cnx-secao cnx-categorias-destaque">
	<div class="cnx-secao__inner">

		<?php if ( '' !== $cnx_titulo ) : ?>
			<h2 class="cnx-secao__titulo"><?php echo esc_html( $cnx_titulo ); ?></h2>
		<?php endif; ?>

		<div class="cnx-palco" data-cnx-trilho-rolavel>
		<ul class="cnx-grade cnx-grade--4" data-cnx-pista>
			<?php foreach ( $cnx_termos as $cnx_termo ) : ?>
				<?php $cnx_fundo = (string) get_term_meta( $cnx_termo->term_id, 'cnx_fundo', true ); ?>
				<li>
					<a class="cnx-card-categoria" href="<?php echo esc_url( (string) get_term_link( $cnx_termo ) ); ?>">
						<span class="cnx-card-categoria__midia"
							<?php if ( '' !== $cnx_fundo ) : ?>style="background:<?php echo esc_attr( $cnx_fundo ); ?>;"<?php endif; ?>>
							<?php cnx_figura( (int) get_term_meta( $cnx_termo->term_id, 'cnx_imagem', true ), 'cnx-card', '', $cnx_termo->name ); ?>
						</span>
						<span class="cnx-card-categoria__nome"><?php echo esc_html( $cnx_termo->name ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
			<?php get_template_part( 'template-parts/sections/setas' ); ?>
		</div>

	</div>
</section>
