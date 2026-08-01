<?php
/**
 * Resultados de busca: vitrine de produtos com filtros, como no design.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$cnx_termo    = get_search_query();
$cnx_filtros  = cnx_filtros_da_busca();
$cnx_ids      = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
$cnx_solucoes = cnx_solucoes_da_busca( $cnx_termo );
$cnx_total    = (int) $GLOBALS['wp_query']->found_posts + count( $cnx_solucoes );

$cnx_relacionadas = cnx_buscas_relacionadas( $cnx_ids );

foreach ( $cnx_solucoes as $cnx_sol ) {
	array_unshift( $cnx_relacionadas, $cnx_sol->name );
}

$cnx_relacionadas = array_slice( array_unique( $cnx_relacionadas ), 0, 4 );

$cnx_faixas = cnx_faixas_quantidade();
$cnx_ordem  = isset( $_GET['ordem'] ) ? sanitize_key( wp_unslash( $_GET['ordem'] ) ) : '';

$cnx_categorias = get_terms( array( 'taxonomy' => 'cnx_categoria_produto', 'hide_empty' => true ) );
$cnx_categorias = is_wp_error( $cnx_categorias ) ? array() : cnx_ordenar_termos( $cnx_categorias );

$cnx_acabamentos = get_terms( array( 'taxonomy' => 'cnx_acabamento', 'hide_empty' => true ) );
$cnx_acabamentos = is_wp_error( $cnx_acabamentos ) ? array() : $cnx_acabamentos;

$cnx_papeis = get_terms( array( 'taxonomy' => 'cnx_papel', 'hide_empty' => true ) );
$cnx_papeis = is_wp_error( $cnx_papeis ) ? array() : $cnx_papeis;

/**
 * Contagem de produtos por faixa de quantidade mínima (para os rótulos).
 */
$cnx_qtd_contagens = array();

foreach ( $cnx_faixas as $cnx_chave => $cnx_faixa ) {
	$cnx_qtd_contagens[ $cnx_chave ] = count(
		get_posts(
			array(
				'post_type'      => 'cnx_produto',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_cnx_qtd_minima',
						'value'   => array( $cnx_faixa['min'], $cnx_faixa['max'] ),
						'type'    => 'NUMERIC',
						'compare' => 'BETWEEN',
					),
				),
			)
		)
	);
}
?>

<div class="cnx-secao__inner">
	<?php
	cnx_breadcrumb(
		array(
			array( __( 'Busca', 'conexao' ), '' !== $cnx_termo ? home_url( '/?s=' ) : '' ),
			array( '' !== $cnx_termo ? $cnx_termo : __( 'Todos', 'conexao' ), '' ),
		)
	);
	?>
</div>

<div class="cnx-secao__inner cnx-busca-pagina">

	<header class="cnx-busca-pagina__cabecalho">
		<h1 class="cnx-busca-pagina__titulo">
			<?php
			printf(
				/* translators: %s: termo buscado */
				esc_html__( 'Resultados para “%s”', 'conexao' ),
				esc_html( $cnx_termo )
			);
			?>
		</h1>

		<p class="cnx-busca-pagina__contagem">
			<?php
			printf(
				/* translators: %s: total de resultados */
				esc_html( _n( '%s produto e solução encontrada', '%s produtos e soluções encontradas', $cnx_total, 'conexao' ) ),
				esc_html( str_pad( (string) $cnx_total, 2, '0', STR_PAD_LEFT ) )
			);
			?>
		</p>

		<?php get_search_form(); ?>

		<?php if ( ! empty( $cnx_relacionadas ) ) : ?>
			<p class="cnx-busca-pagina__relacionadas">
				<span><?php esc_html_e( 'Buscas relacionadas:', 'conexao' ); ?></span>
				<?php foreach ( $cnx_relacionadas as $cnx_i => $cnx_rel ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $cnx_rel ), home_url( '/' ) ) ); ?>"><?php
						echo esc_html( mb_strtolower( $cnx_rel ) );
					?></a><?php echo $cnx_i < count( $cnx_relacionadas ) - 1 ? ',' : ''; ?>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
	</header>

	<div class="cnx-busca-pagina__grade">

		<form class="cnx-filtros" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="hidden" name="s" value="<?php echo esc_attr( $cnx_termo ); ?>">
			<?php if ( '' !== $cnx_ordem ) : ?>
				<input type="hidden" name="ordem" value="<?php echo esc_attr( $cnx_ordem ); ?>">
			<?php endif; ?>

			<?php
			$cnx_grupos_filtro = array(
				array( __( 'Categorias', 'conexao' ), 'categoria', $cnx_categorias, $cnx_filtros['categorias'] ),
				array( __( 'Acabamento', 'conexao' ), 'acabamento', $cnx_acabamentos, $cnx_filtros['acabamentos'] ),
				array( __( 'Tipo de papel', 'conexao' ), 'papel', $cnx_papeis, $cnx_filtros['papeis'] ),
			);
			?>

			<?php foreach ( $cnx_grupos_filtro as [ $cnx_rotulo, $cnx_nome, $cnx_termos_f, $cnx_marcados ] ) : ?>
				<?php if ( empty( $cnx_termos_f ) ) { continue; } ?>
				<fieldset class="cnx-filtros__grupo">
					<legend><?php echo esc_html( $cnx_rotulo ); ?></legend>

					<?php foreach ( $cnx_termos_f as $cnx_t ) : ?>
						<label class="cnx-filtros__opcao">
							<input type="checkbox" name="<?php echo esc_attr( $cnx_nome ); ?>[]"
								value="<?php echo esc_attr( $cnx_t->slug ); ?>"
								<?php checked( in_array( $cnx_t->slug, $cnx_marcados, true ) ); ?>>
							<span><?php echo esc_html( $cnx_t->name ); ?> (<?php echo esc_html( (string) $cnx_t->count ); ?>)</span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>

			<fieldset class="cnx-filtros__grupo">
				<legend><?php esc_html_e( 'Quantidade', 'conexao' ); ?></legend>

				<?php foreach ( $cnx_faixas as $cnx_chave => $cnx_faixa ) : ?>
					<label class="cnx-filtros__opcao">
						<input type="checkbox" name="qtd[]" value="<?php echo esc_attr( $cnx_chave ); ?>"
							<?php checked( in_array( $cnx_chave, $cnx_filtros['qtd'], true ) ); ?>>
						<span><?php echo esc_html( $cnx_faixa['rotulo'] ); ?> (<?php echo esc_html( (string) $cnx_qtd_contagens[ $cnx_chave ] ); ?>)</span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<a class="cnx-filtros__limpar" href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $cnx_termo ), home_url( '/' ) ) ); ?>">
				<?php esc_html_e( 'Limpar filtros', 'conexao' ); ?>
			</a>

			<button type="submit" class="cnx-filtros__aplicar">
				<?php esc_html_e( 'Aplicar filtros', 'conexao' ); ?>
			</button>
		</form>

		<div class="cnx-busca-pagina__resultados">
			<div class="cnx-busca-pagina__barra">
				<p class="cnx-busca-pagina__mini-contagem">
					<?php
					printf(
						/* translators: %s: total de resultados */
						esc_html( _n( '%s resultado encontrado', '%s resultados encontrados', $cnx_total, 'conexao' ) ),
						esc_html( str_pad( (string) $cnx_total, 2, '0', STR_PAD_LEFT ) )
					);
					?>
				</p>

				<div class="cnx-busca-pagina__controles">
					<label class="screen-reader-text" for="cnx-ordem"><?php esc_html_e( 'Ordenar por', 'conexao' ); ?></label>
					<select id="cnx-ordem" class="cnx-campo cnx-campo--select" data-cnx-ordem>
						<option value="" <?php selected( $cnx_ordem, '' ); ?>><?php esc_html_e( 'Mais relevantes', 'conexao' ); ?></option>
						<option value="nome" <?php selected( $cnx_ordem, 'nome' ); ?>><?php esc_html_e( 'Nome (A–Z)', 'conexao' ); ?></option>
						<option value="recentes" <?php selected( $cnx_ordem, 'recentes' ); ?>><?php esc_html_e( 'Mais recentes', 'conexao' ); ?></option>
					</select>

					<button type="button" class="cnx-vista" data-cnx-vista="grade" aria-pressed="true"
						aria-label="<?php esc_attr_e( 'Ver em grade', 'conexao' ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">
							<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/>
							<rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>
						</svg>
					</button>

					<button type="button" class="cnx-vista" data-cnx-vista="lista" aria-pressed="false"
						aria-label="<?php esc_attr_e( 'Ver em lista', 'conexao' ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">
							<rect x="3" y="4" width="18" height="3" rx="1"/><rect x="3" y="10.5" width="18" height="3" rx="1"/>
							<rect x="3" y="17" width="18" height="3" rx="1"/>
						</svg>
					</button>
				</div>
			</div>

			<?php if ( have_posts() ) : ?>

				<ul class="cnx-grade cnx-grade-listagem cnx-grade-busca" data-cnx-resultados>
					<?php while ( have_posts() ) : ?>
						<?php the_post(); ?>
						<?php get_template_part( 'template-parts/cards/produto-simples', null, array( 'produto' => get_post() ) ); ?>
					<?php endwhile; ?>
				</ul>

				<?php
				the_posts_pagination(
					array(
						'mid_size'  => 2,
						'prev_text' => __( 'Anterior', 'conexao' ),
						'next_text' => __( 'Próxima', 'conexao' ),
					)
				);
				?>

			<?php else : ?>

				<div class="cnx-busca-pagina__vazio">
					<p><?php esc_html_e( 'Nenhum produto encontrado para essa busca.', 'conexao' ); ?></p>
					<p>
						<a class="cnx-btn cnx-btn--secundario" href="<?php echo esc_url( (string) get_post_type_archive_link( 'cnx_produto' ) ); ?>">
							<?php esc_html_e( 'Ver todos os produtos', 'conexao' ); ?>
						</a>
					</p>
				</div>

			<?php endif; ?>
		</div>

	</div>
</div>

<?php
get_footer();
