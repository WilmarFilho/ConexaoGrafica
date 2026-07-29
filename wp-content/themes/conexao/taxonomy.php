<?php
/**
 * Listagem de uma categoria ou solução.
 *
 * Um arquivo serve às duas taxonomias: o rótulo acima do título ("Categoria",
 * "Solução") vem do próprio registro da taxonomia, então não há o que duplicar.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$cnx_termo     = get_queried_object();
$cnx_taxonomia = $cnx_termo instanceof WP_Term ? get_taxonomy( $cnx_termo->taxonomy ) : null;
$cnx_rotulo    = $cnx_taxonomia ? $cnx_taxonomia->labels->singular_name : '';
?>

<div class="cnx-listagem">
	<div class="cnx-secao__inner">

		<header class="cnx-listagem__cabecalho">
			<?php if ( '' !== $cnx_rotulo ) : ?>
				<p class="cnx-listagem__rotulo"><?php echo esc_html( $cnx_rotulo ); ?></p>
			<?php endif; ?>

			<h1 class="cnx-listagem__titulo"><?php echo esc_html( single_term_title( '', false ) ); ?></h1>

			<?php if ( $cnx_termo instanceof WP_Term && '' !== $cnx_termo->description ) : ?>
				<p class="cnx-listagem__descricao"><?php echo esc_html( $cnx_termo->description ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>

			<h2 class="cnx-secao__titulo"><?php esc_html_e( 'Mais vendidos', 'conexao' ); ?></h2>

			<?php // data-cnx-listagem marca o alvo do "Carregar mais". ?>
			<ul class="cnx-grade cnx-grade-listagem" data-cnx-listagem>
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php
					get_template_part(
						'template-parts/cards/produto-simples',
						null,
						array( 'produto' => get_post() )
					);
					?>
				<?php endwhile; ?>
			</ul>

			<?php
			// Sem o max_num_pages a função devolve link até na última página.
			$cnx_proxima = get_next_posts_page_link( $GLOBALS['wp_query']->max_num_pages );

			if ( $cnx_proxima ) :
				?>
				<div class="cnx-listagem__mais">
					<?php // Link real: funciona sem JavaScript. O JS intercepta e anexa. ?>
					<a class="cnx-btn cnx-btn--secundario" data-cnx-carregar-mais
						href="<?php echo esc_url( $cnx_proxima ); ?>">
						<?php esc_html_e( 'Carregar mais', 'conexao' ); ?>
					</a>
				</div>
			<?php endif; ?>

		<?php else : ?>

			<p class="cnx-listagem__vazio">
				<?php esc_html_e( 'Ainda não há produtos publicados nesta categoria.', 'conexao' ); ?>
			</p>

		<?php endif; ?>

	</div>
</div>

<?php
/**
 * Relacionados: produtos em destaque de fora desta listagem. Sem eles a página
 * termina em beco sem saída quando a categoria tem poucos itens.
 */
$cnx_exibidos = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );

$cnx_relacionados = get_posts(
	array(
		'post_type'      => 'cnx_produto',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'post__not_in'   => $cnx_exibidos,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'meta_key'       => '_cnx_destaque',
		'meta_value'     => '1',
	)
);

// Sem destaques suficientes, completa com quaisquer outros produtos.
if ( count( $cnx_relacionados ) < 4 ) {
	$cnx_relacionados = get_posts(
		array(
			'post_type'      => 'cnx_produto',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'post__not_in'   => $cnx_exibidos,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
}

if ( ! empty( $cnx_relacionados ) ) {
	get_template_part(
		'template-parts/sections/mais-vendidos',
		null,
		array(
			'titulo'   => __( 'Produtos relacionados', 'conexao' ),
			'produtos' => $cnx_relacionados,
		)
	);
}

get_footer();
