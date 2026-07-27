<?php
/**
 * Painel "Conexão" — mapa das seções do site.
 *
 * Categorias e Soluções são taxonomias: o WordPress as esconde como submenu de
 * Produtos, e ninguém adivinha que é ali que se cadastra a imagem do card da
 * home. Esta tela liga cada seção ao lugar onde ela se edita.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'cnx_registrar_hub', 9 );

function cnx_registrar_hub(): void {
	add_menu_page(
		__( 'Seções do site', 'conexao' ),
		__( 'Conexão', 'conexao' ),
		'edit_posts',
		'cnx-hub',
		'cnx_render_hub',
		'dashicons-layout',
		3
	);
}

/**
 * Descreve cada seção da home: onde edita, qual shortcode e o que falta.
 */
function cnx_mapa_das_secoes(): array {
	return array(
		array(
			'titulo'    => __( 'Carrossel do topo', 'conexao' ),
			'shortcode' => '[cnx_hero]',
			'onde'      => __( 'Slides', 'conexao' ),
			'url'       => admin_url( 'edit.php?post_type=' . CNX_CPT_SLIDE ),
			'contagem'  => cnx_contar_posts( CNX_CPT_SLIDE ),
			'faltando'  => cnx_contar_posts_sem_imagem( CNX_CPT_SLIDE ),
			'nota'      => __( 'A imagem da coluna direita é a imagem destacada do slide.', 'conexao' ),
		),
		array(
			'titulo'    => __( 'Categorias em destaque', 'conexao' ),
			'shortcode' => '[cnx_categorias]',
			'onde'      => __( 'Produtos → Categorias', 'conexao' ),
			'url'       => admin_url( 'edit-tags.php?taxonomy=' . CNX_TAX_CATEGORIA . '&post_type=' . CNX_CPT_PRODUTO ),
			'contagem'  => cnx_contar_termos_destaque(),
			'faltando'  => cnx_contar_termos_sem_imagem( CNX_TAX_CATEGORIA ),
			'nota'      => __( 'Só aparecem as categorias com "Exibir em Categorias em destaque" marcado.', 'conexao' ),
		),
		array(
			'titulo'    => __( 'Soluções', 'conexao' ),
			'shortcode' => '[cnx_solucoes]',
			'onde'      => __( 'Produtos → Soluções', 'conexao' ),
			'url'       => admin_url( 'edit-tags.php?taxonomy=' . CNX_TAX_SOLUCAO . '&post_type=' . CNX_CPT_PRODUTO ),
			'contagem'  => cnx_contar_termos( CNX_TAX_SOLUCAO ),
			'faltando'  => cnx_contar_termos_sem_imagem( CNX_TAX_SOLUCAO ),
			'nota'      => __( 'Cada solução tem imagem, cor da tarja e ordem.', 'conexao' ),
		),
		array(
			'titulo'    => __( 'Mais vendidos', 'conexao' ),
			'shortcode' => '[cnx_mais_vendidos]',
			'onde'      => __( 'Produtos', 'conexao' ),
			'url'       => admin_url( 'edit.php?post_type=' . CNX_CPT_PRODUTO ),
			'contagem'  => cnx_contar_produtos_destaque(),
			'faltando'  => cnx_contar_posts_sem_imagem( CNX_CPT_PRODUTO ),
			'nota'      => __( 'Marque "Exibir em Mais vendidos" ao editar cada produto.', 'conexao' ),
		),
		array(
			'titulo'    => __( 'Banner de chamada', 'conexao' ),
			'shortcode' => '[cnx_banner slug="..."]',
			'onde'      => __( 'Banners', 'conexao' ),
			'url'       => admin_url( 'edit.php?post_type=' . CNX_CPT_BANNER ),
			'contagem'  => cnx_contar_posts( CNX_CPT_BANNER ),
			'faltando'  => cnx_contar_posts_sem_imagem( CNX_CPT_BANNER ),
			'nota'      => __( 'A foto de fundo é a imagem destacada do banner.', 'conexao' ),
		),
		array(
			'titulo'    => __( 'Diferenciais e Como Funciona', 'conexao' ),
			'shortcode' => '[cnx_diferenciais] · [cnx_como_funciona]',
			'onde'      => __( 'No código', 'conexao' ),
			'url'       => '',
			'contagem'  => null,
			'faltando'  => 0,
			'nota'      => __( 'Texto fixo, sem imagens. Editável em includes/shortcodes.php.', 'conexao' ),
		),
	);
}

function cnx_contar_posts( string $post_type ): int {
	$contagem = wp_count_posts( $post_type );

	return (int) ( $contagem->publish ?? 0 );
}

function cnx_contar_posts_sem_imagem( string $post_type ): int {
	$ids = get_posts(
		array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	return count( $ids );
}

function cnx_contar_termos( string $taxonomy ): int {
	$n = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

	return is_wp_error( $n ) ? 0 : (int) $n;
}

function cnx_contar_termos_destaque(): int {
	$termos = get_terms(
		array(
			'taxonomy'   => CNX_TAX_CATEGORIA,
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'   => 'cnx_destaque',
					'value' => '1',
				),
			),
		)
	);

	return is_wp_error( $termos ) ? 0 : count( $termos );
}

function cnx_contar_termos_sem_imagem( string $taxonomy ): int {
	$termos = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

	if ( is_wp_error( $termos ) ) {
		return 0;
	}

	$sem = 0;

	foreach ( $termos as $termo ) {
		if ( ! get_term_meta( $termo->term_id, 'cnx_imagem', true ) ) {
			++$sem;
		}
	}

	return $sem;
}

function cnx_contar_produtos_destaque(): int {
	$ids = get_posts(
		array(
			'post_type'   => CNX_CPT_PRODUTO,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_cnx_destaque',
			'meta_value'  => '1',
		)
	);

	return count( $ids );
}

function cnx_render_hub(): void {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Seções do site', 'conexao' ); ?></h1>

		<p class="description" style="max-width:760px;font-size:14px;">
			<?php esc_html_e( 'Cada seção da home é um shortcode colado numa página. Aqui está onde se edita o conteúdo de cada uma.', 'conexao' ); ?>
		</p>

		<table class="widefat striped" style="margin-top:18px;max-width:1100px;">
			<thead>
				<tr>
					<th style="width:22%;"><?php esc_html_e( 'Seção', 'conexao' ); ?></th>
					<th style="width:20%;"><?php esc_html_e( 'Shortcode', 'conexao' ); ?></th>
					<th style="width:22%;"><?php esc_html_e( 'Onde editar', 'conexao' ); ?></th>
					<th><?php esc_html_e( 'Situação', 'conexao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( cnx_mapa_das_secoes() as $secao ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $secao['titulo'] ); ?></strong><br>
							<span class="description"><?php echo esc_html( $secao['nota'] ); ?></span>
						</td>
						<td><code><?php echo esc_html( $secao['shortcode'] ); ?></code></td>
						<td>
							<?php if ( '' !== $secao['url'] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $secao['url'] ); ?>">
									<?php echo esc_html( $secao['onde'] ); ?>
								</a>
							<?php else : ?>
								<span class="description"><?php echo esc_html( $secao['onde'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( null === $secao['contagem'] ) : ?>
								—
							<?php else : ?>
								<?php
								printf(
									/* translators: %d: quantidade de itens exibidos */
									esc_html( _n( '%d item exibido', '%d itens exibidos', (int) $secao['contagem'], 'conexao' ) ),
									(int) $secao['contagem']
								);
								?>

								<?php if ( 0 === (int) $secao['contagem'] ) : ?>
									<span style="color:#d63638;"> — <?php esc_html_e( 'a seção não aparece no site', 'conexao' ); ?></span>
								<?php elseif ( $secao['faltando'] > 0 ) : ?>
									<br>
									<span style="color:#996800;">
										<?php
										printf(
											/* translators: %d: quantidade sem imagem */
											esc_html( _n( '%d sem imagem', '%d sem imagem', (int) $secao['faltando'], 'conexao' ) ),
											(int) $secao['faltando']
										);
										?>
									</span>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:34px;"><?php esc_html_e( 'Cabeçalho e rodapé', 'conexao' ); ?></h2>

		<p class="description" style="max-width:760px;font-size:14px;">
			<?php esc_html_e( 'O tema é clássico (PHP), não um tema de blocos: cabeçalho e rodapé não aparecem em Aparência → Editor nem em Padrões. O layout deles é código; o conteúdo se edita nos lugares abaixo.', 'conexao' ); ?>
		</p>

		<table class="widefat striped" style="margin-top:14px;max-width:1100px;">
			<thead>
				<tr>
					<th style="width:30%;"><?php esc_html_e( 'O que mudar', 'conexao' ); ?></th>
					<th style="width:26%;"><?php esc_html_e( 'Onde', 'conexao' ); ?></th>
					<th><?php esc_html_e( 'Observação', 'conexao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$cnx_itens = array(
					array(
						__( 'Links da topbar e do rodapé', 'conexao' ),
						__( 'Aparência → Menus', 'conexao' ),
						admin_url( 'nav-menus.php' ),
						__( 'Locais: Topbar, Categorias do header, Menu do rodapé e Links legais.', 'conexao' ),
					),
					array(
						__( 'Logo', 'conexao' ),
						__( 'Personalizar → Identidade do site', 'conexao' ),
						admin_url( 'customize.php' ),
						__( 'Sem logo definida, o tema usa assets/img/logo.png.', 'conexao' ),
					),
					array(
						__( 'Telefones, e-mail, horário, redes', 'conexao' ),
						__( 'Configurações → Conexão', 'conexao' ),
						admin_url( 'options-general.php?page=cnx-settings' ),
						__( 'Aparecem no rodapé e no botão de WhatsApp.', 'conexao' ),
					),
					array(
						__( 'Faixa de categorias do header', 'conexao' ),
						__( 'Produtos → Categorias', 'conexao' ),
						admin_url( 'edit-tags.php?taxonomy=' . CNX_TAX_CATEGORIA . '&post_type=' . CNX_CPT_PRODUTO ),
						__( 'A ordem vem do menu "Categorias do header", se houver.', 'conexao' ),
					),
					array(
						__( 'Estrutura e estilo (HTML/CSS)', 'conexao' ),
						__( 'No código do tema', 'conexao' ),
						'',
						__( 'template-parts/header/ e template-parts/footer/', 'conexao' ),
					),
				);

				foreach ( $cnx_itens as $cnx_item ) :
					?>
					<tr>
						<td><strong><?php echo esc_html( $cnx_item[0] ); ?></strong></td>
						<td>
							<?php if ( '' !== $cnx_item[2] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $cnx_item[2] ); ?>">
									<?php echo esc_html( $cnx_item[1] ); ?>
								</a>
							<?php else : ?>
								<span class="description"><?php echo esc_html( $cnx_item[1] ); ?></span>
							<?php endif; ?>
						</td>
						<td class="description"><?php echo esc_html( $cnx_item[3] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
