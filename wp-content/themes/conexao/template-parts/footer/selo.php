<?php
/**
 * Selo "Compra segura".
 *
 * Aparece em dois lugares do rodapé porque o layout o coloca em pontos
 * diferentes: no desktop, embaixo do bloco de contato; no mobile, centralizado
 * sob os ícones de pagamento. Um deles fica com display:none conforme a
 * largura — e display:none também o tira da árvore de acessibilidade, então o
 * leitor de tela nunca anuncia os dois.
 *
 * @var array $args { @type string $classe }
 */

defined( 'ABSPATH' ) || exit;
?>

<p class="cnx-rodape__selo <?php echo esc_attr( (string) ( $args['classe'] ?? '' ) ); ?>">
	<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
		stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
		<rect x="4" y="10" width="16" height="11" rx="2"/>
		<path d="M8 10V7a4 4 0 0 1 8 0v3"/>
	</svg>
	<?php esc_html_e( 'COMPRA SEGURA', 'conexao' ); ?>
</p>
