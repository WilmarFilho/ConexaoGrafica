<?php
/**
 * Catálogo completo: /produtos/.
 *
 * Mesmo layout da listagem de categoria, sem o recorte de termo — e sem a
 * seção de relacionados, que não faz sentido quando a página já mostra tudo.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="cnx-listagem">
	<div class="cnx-secao__inner">

		<header class="cnx-listagem__cabecalho">
			<p class="cnx-listagem__rotulo"><?php esc_html_e( 'Catálogo', 'conexao' ); ?></p>
			<h1 class="cnx-listagem__titulo"><?php esc_html_e( 'Todos os produtos', 'conexao' ); ?></h1>
			<p class="cnx-listagem__descricao">
				<?php esc_html_e( 'Impressos personalizados para empresas, consultórios, escritórios e editoras. Escolha um produto para configurar e pedir o seu orçamento.', 'conexao' ); ?>
			</p>
		</header>

		<?php if ( have_posts() ) : ?>

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
			$cnx_proxima = get_next_posts_page_link( $GLOBALS['wp_query']->max_num_pages );

			if ( $cnx_proxima ) :
				?>
				<div class="cnx-listagem__mais">
					<a class="cnx-btn cnx-btn--secundario" data-cnx-carregar-mais
						href="<?php echo esc_url( $cnx_proxima ); ?>">
						<?php esc_html_e( 'Carregar mais', 'conexao' ); ?>
					</a>
				</div>
			<?php endif; ?>

		<?php else : ?>

			<p class="cnx-listagem__vazio">
				<?php esc_html_e( 'Nenhum produto publicado ainda.', 'conexao' ); ?>
			</p>

		<?php endif; ?>

	</div>
</div>

<?php
get_footer();
