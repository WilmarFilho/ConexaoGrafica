<?php
/**
 * Página não encontrada.
 *
 * Um 404 vindo de link quebrado é a última chance de segurar o visitante:
 * além do aviso, oferece os dois caminhos principais e a busca.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="cnx-secao__inner">
	<?php cnx_breadcrumb( array( array( __( 'Página não encontrada', 'conexao' ), '' ) ) ); ?>
</div>

<div class="cnx-secao__inner cnx-404">

	<div class="cnx-404__conteudo">
		<p class="cnx-404__codigo" aria-hidden="true">404</p>

		<h1 class="cnx-404__titulo"><?php esc_html_e( 'Página não encontrada', 'conexao' ); ?></h1>

		<p class="cnx-404__texto">
			<?php esc_html_e( 'Ops! A página que você tentou acessar não existe, foi removida ou está temporariamente indisponível.', 'conexao' ); ?>
		</p>

		<div class="cnx-404__acoes">
			<a class="cnx-btn cnx-btn--secundario" href="<?php echo esc_url( (string) get_post_type_archive_link( 'cnx_produto' ) ); ?>">
				<?php esc_html_e( 'Ver Produtos', 'conexao' ); ?>
			</a>

			<a class="cnx-btn cnx-btn--primario" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Voltar para Home', 'conexao' ); ?>
			</a>
		</div>

		<?php get_search_form(); ?>
	</div>

	<div class="cnx-404__midia">
		<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/404.png' ) ); ?>"
			alt="" width="710" height="533" fetchpriority="high" aria-hidden="true">
	</div>

</div>

<?php
get_footer();
