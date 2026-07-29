<?php
/**
 * Listagem do blog (a página definida em Leitura → Página de posts).
 *
 * home.php e não index.php: o index continua sendo o último recurso do
 * WordPress, para arquivos e buscas que não têm template próprio.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$cnx_pagina = (int) get_option( 'page_for_posts' );
?>

<div class="cnx-secao__inner">
	<?php cnx_breadcrumb( array( array( __( 'Blog', 'conexao' ), '' ) ) ); ?>
</div>

<div class="cnx-blog">
	<div class="cnx-secao__inner cnx-blog__grade">

		<div class="cnx-blog__principal">
			<header class="cnx-blog__cabecalho">
				<h1 class="cnx-listagem__titulo">
					<?php echo esc_html( $cnx_pagina ? get_the_title( $cnx_pagina ) : __( 'Blog', 'conexao' ) ); ?>
				</h1>

				<p class="cnx-listagem__descricao">
					<?php esc_html_e( 'Confira dicas, ideias e informações sobre materiais gráficos, identidade visual, acabamento e comunicação profissional para destacar sua empresa e causar uma ótima impressão.', 'conexao' ); ?>
				</p>
			</header>

			<?php if ( have_posts() ) : ?>

				<div class="cnx-blog__lista">
					<?php while ( have_posts() ) : ?>
						<?php the_post(); ?>
						<?php get_template_part( 'template-parts/blog/card-post' ); ?>
					<?php endwhile; ?>
				</div>

				<?php
				// numbered: a paginação do design mostra os números e "Anterior/Próxima".
				the_posts_pagination(
					array(
						'mid_size'           => 2,
						'prev_text'          => __( 'Anterior', 'conexao' ),
						'next_text'          => __( 'Próxima', 'conexao' ),
						'screen_reader_text' => __( 'Navegação dos posts', 'conexao' ),
					)
				);
				?>

				<p class="cnx-blog__contador">
					<?php
					global $wp_query;
					printf(
						/* translators: 1: página atual, 2: total de páginas */
						esc_html__( 'Pág. %1$d de %2$d', 'conexao' ),
						max( 1, (int) get_query_var( 'paged' ) ),
						max( 1, (int) $wp_query->max_num_pages )
					);
					?>
				</p>

			<?php else : ?>
				<p class="cnx-listagem__vazio"><?php esc_html_e( 'Nenhum post publicado ainda.', 'conexao' ); ?></p>
			<?php endif; ?>
		</div>

		<?php get_template_part( 'template-parts/blog/sidebar' ); ?>

	</div>
</div>

<?php
get_footer();
