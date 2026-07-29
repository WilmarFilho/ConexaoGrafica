<?php
/**
 * Card de produto das listagens (categoria, solução, busca).
 *
 * Diferente do card da home: aqui o nome vem inteiro e a chamada é "Ver mais",
 * porque quem está na listagem já sabe o contexto e quer chegar ao produto.
 *
 * @var array $args { @type WP_Post $produto }
 */

defined( 'ABSPATH' ) || exit;

$cnx_produto = $args['produto'] ?? null;

if ( ! $cnx_produto instanceof WP_Post ) {
	return;
}

$cnx_fundo = (string) cnx_meta( $cnx_produto->ID, 'fundo' );
?>

<li>
	<a class="cnx-card-simples" href="<?php echo esc_url( (string) get_permalink( $cnx_produto ) ); ?>">
		<span class="cnx-card-simples__midia"
			<?php if ( '' !== $cnx_fundo ) : ?>style="background:<?php echo esc_attr( $cnx_fundo ); ?>;"<?php endif; ?>>
			<?php cnx_figura( (int) get_post_thumbnail_id( $cnx_produto ), 'cnx-card', '', get_the_title( $cnx_produto ) ); ?>
		</span>

		<span class="cnx-card-simples__nome"><?php echo esc_html( get_the_title( $cnx_produto ) ); ?></span>
		<span class="cnx-card-simples__link"><?php esc_html_e( 'Ver mais', 'conexao' ); ?></span>
	</a>
</li>
