<?php
/**
 * Faixa de selos abaixo do carrossel. Vem do shortcode [cnx_diferenciais].
 *
 * @var array $args { @type array $itens }
 */

defined( 'ABSPATH' ) || exit;

$cnx_itens = $args['itens'] ?? array();

if ( empty( $cnx_itens ) ) {
	return;
}

/**
 * Ícones em SVG inline: são quatro, não vale uma requisição a mais por eles.
 */
$cnx_icones = array(
	'raio'     => '<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/>',
	'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
	'balao'    => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9 9 0 0 1-3.8-.8L3 21l1.9-4.9A8.4 8.4 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/>',
	'caminhao' => '<path d="M1 4h12v12H1z"/><path d="M13 8h4l4 4v4h-8"/><circle cx="6" cy="19" r="1.6"/><circle cx="17" cy="19" r="1.6"/>',
);
?>

<section class="cnx-diferenciais">
	<div class="cnx-diferenciais__inner">
		<div class="cnx-palco" data-cnx-trilho-rolavel>
		<ul class="cnx-grade cnx-grade--4" data-cnx-pista>
		<?php foreach ( $cnx_itens as $cnx_item ) : ?>
			<?php // O <li> é o item do trilho; o card é o div de dentro. ?>
			<li>
			<div class="cnx-diferencial">
				<span class="cnx-diferencial__icone" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor"
						stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
						<?php
						// Os paths são literais definidos acima, não entrada de usuário.
						echo $cnx_icones[ $cnx_item['icone'] ?? '' ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</svg>
				</span>

				<h3 class="cnx-diferencial__titulo"><?php echo esc_html( $cnx_item['titulo'] ?? '' ); ?></h3>
				<p class="cnx-diferencial__texto"><?php echo esc_html( $cnx_item['texto'] ?? '' ); ?></p>
			</div>
			</li>
		<?php endforeach; ?>
		</ul>
			<?php get_template_part( 'template-parts/sections/setas' ); ?>
		</div>
	</div>
</section>
