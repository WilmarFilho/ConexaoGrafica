<?php
/**
 * Página de um produto: galeria + configurador + modal de orçamento.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$produto_id = get_the_ID();
	$grupos     = cnx_produto_configuracao( $produto_id );
	$blocos     = cnx_produto_blocos( $produto_id );
	$galeria    = cnx_produto_galeria( $produto_id );
	$resumo     = cnx_meta( $produto_id, 'resumo' );
	$numero     = cnx_whatsapp_numero( $produto_id );
	$categoria  = cnx_categoria_principal( $produto_id );

	// A imagem destacada abre a galeria.
	if ( has_post_thumbnail() ) {
		array_unshift( $galeria, (int) get_post_thumbnail_id() );
		$galeria = array_values( array_unique( $galeria ) );
	}

	$trilha = array();

	if ( $categoria instanceof WP_Term ) {
		$trilha[] = array( $categoria->name, (string) get_term_link( $categoria ) );
	}

	$trilha[] = array( get_the_title(), '' );
	?>

	<div class="cnx-secao__inner">
		<?php cnx_breadcrumb( $trilha ); ?>
	</div>

	<div class="cnx-secao__inner">
		<article <?php post_class( 'cnx-produto' ); ?>>

			<div class="cnx-produto__midia">
				<?php if ( ! empty( $galeria ) ) : ?>
					<div class="cnx-galeria-principal">
						<?php echo wp_get_attachment_image( $galeria[0], 'cnx-produto', false, array( 'data-cnx-galeria-principal' => 'true' ) ); ?>
					</div>

					<?php if ( count( $galeria ) > 1 ) : ?>
						<ul class="cnx-galeria-thumbs">
							<?php foreach ( $galeria as $i => $img_id ) : ?>
								<li>
									<button type="button"
										data-cnx-thumb
										data-full="<?php echo esc_url( (string) wp_get_attachment_image_url( $img_id, 'cnx-produto' ) ); ?>"
										aria-current="<?php echo 0 === $i ? 'true' : 'false'; ?>">
										<?php echo wp_get_attachment_image( $img_id, 'cnx-card' ); ?>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php else : ?>
					<div class="cnx-galeria-principal">
						<span class="cnx-placeholder" aria-hidden="true"></span>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $blocos ) ) : ?>
					<div class="cnx-accordion">
						<?php foreach ( $blocos as $bloco ) : ?>
							<details>
								<summary><?php echo esc_html( $bloco['titulo'] ); ?></summary>
								<div class="cnx-accordion__conteudo"><?php echo wp_kses_post( wpautop( $bloco['conteudo'] ) ); ?></div>
							</details>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="cnx-produto__info">
				<h1 class="cnx-produto__titulo"><?php the_title(); ?></h1>

				<?php if ( $resumo ) : ?>
					<p class="cnx-produto__resumo"><?php echo esc_html( $resumo ); ?></p>
				<?php endif; ?>

				<?php if ( empty( $grupos ) ) : ?>

					<?php // Produto sem configurador: o modal já basta. ?>
					<button type="button" class="cnx-btn-orcamento" data-cnx-abrir-modal>
						<?php esc_html_e( 'Solicitar Orçamento', 'conexao' ); ?>
					</button>

				<?php else : ?>

					<form class="cnx-config" data-cnx-config onsubmit="return false;">

						<h2 class="cnx-produto__subtitulo"><?php esc_html_e( 'Configuração', 'conexao' ); ?></h2>

						<?php foreach ( $grupos as $grupo ) : ?>
							<fieldset class="cnx-config__grupo"
								data-cnx-grupo
								data-titulo="<?php echo esc_attr( $grupo['titulo'] ); ?>"
								data-obrigatorio="<?php echo $grupo['obrigatorio'] ? '1' : '0'; ?>">

								<legend class="cnx-config__titulo"><?php echo esc_html( $grupo['titulo'] ); ?></legend>

								<?php if ( $grupo['ajuda'] ) : ?>
									<p class="cnx-config__ajuda"><?php echo esc_html( $grupo['ajuda'] ); ?></p>
								<?php endif; ?>

								<div class="cnx-config__opcoes">
									<?php foreach ( $grupo['opcoes'] as $opcao ) : ?>
										<button type="button" class="cnx-opcao" aria-pressed="false"
											data-cnx-opcao="<?php echo esc_attr( $opcao ); ?>">
											<?php echo esc_html( $opcao ); ?>
										</button>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endforeach; ?>

						<div class="cnx-resumo">
							<h3 class="cnx-resumo__titulo"><?php esc_html_e( 'Resumo', 'conexao' ); ?></h3>

							<?php foreach ( $grupos as $grupo ) : ?>
								<p class="cnx-resumo__linha" data-cnx-resumo="<?php echo esc_attr( $grupo['titulo'] ); ?>">
									<span><?php echo esc_html( $grupo['titulo'] ); ?></span>
									<span data-cnx-valor><?php esc_html_e( 'Selecione', 'conexao' ); ?></span>
								</p>
							<?php endforeach; ?>

							<p class="cnx-resumo__aviso" data-cnx-aviso>
								<?php esc_html_e( 'Selecione as opções acima para continuar.', 'conexao' ); ?>
							</p>

							<button type="button" class="cnx-btn-orcamento" data-cnx-abrir-modal aria-disabled="true">
								<?php esc_html_e( 'Solicitar Orçamento', 'conexao' ); ?>
							</button>
						</div>
					</form>

					<?php if ( ! $numero ) : ?>
						<p class="cnx-resumo__aviso">
							<?php esc_html_e( 'Configure o número de WhatsApp em Configurações → Conexão.', 'conexao' ); ?>
						</p>
					<?php endif; ?>

				<?php endif; ?>

				<?php if ( trim( (string) get_the_content() ) ) : ?>
					<div class="cnx-produto__descricao"><?php the_content(); ?></div>
				<?php endif; ?>
			</div>

		</article>
	</div>

	<?php
	get_template_part(
		'template-parts/produto/modal-orcamento',
		null,
		array( 'produto_id' => $produto_id )
	);

	/**
	 * Relacionados: da mesma categoria, senão os destaques. Reaproveita a seção
	 * da home em vez de repetir a marcação do card.
	 */
	$relacionados = array();

	if ( $categoria instanceof WP_Term ) {
		$relacionados = get_posts(
			array(
				'post_type'      => 'cnx_produto',
				'post_status'    => 'publish',
				'posts_per_page' => 4,
				'post__not_in'   => array( $produto_id ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'cnx_categoria_produto',
						'field'    => 'term_id',
						'terms'    => array( $categoria->term_id ),
					),
				),
			)
		);
	}

	if ( count( $relacionados ) < 4 ) {
		$relacionados = get_posts(
			array(
				'post_type'      => 'cnx_produto',
				'post_status'    => 'publish',
				'posts_per_page' => 4,
				'post__not_in'   => array( $produto_id ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'meta_key'       => '_cnx_destaque',
				'meta_value'     => '1',
			)
		);
	}

	if ( ! empty( $relacionados ) ) {
		get_template_part(
			'template-parts/sections/mais-vendidos',
			null,
			array(
				'titulo'   => __( 'Produtos relacionados', 'conexao' ),
				'produtos' => $relacionados,
				'contexto' => 'relacionados',
			)
		);
	}

endwhile;

get_footer();
