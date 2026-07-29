<?php
/**
 * Modal de orçamento do produto.
 *
 * Por que passar pelo servidor em vez de ir direto ao wa.me: o lead fica
 * gravado antes do redirecionamento. Se o WhatsApp não abrir — desktop sem app,
 * bloqueio de pop-up, celular sem o aplicativo — o contato não se perde.
 *
 * @var array $args { @type int $produto_id }
 */

defined( 'ABSPATH' ) || exit;

$cnx_produto_id = (int) ( $args['produto_id'] ?? 0 );

if ( ! $cnx_produto_id ) {
	return;
}
?>

<div class="cnx-modal" id="cnx-modal-orcamento" data-cnx-modal role="dialog" aria-modal="true"
	aria-labelledby="cnx-modal-titulo">

	<div class="cnx-modal__fundo" data-cnx-modal-fechar></div>

	<div class="cnx-modal__caixa" role="document">
		<button type="button" class="cnx-modal__fechar" data-cnx-modal-fechar
			aria-label="<?php esc_attr_e( 'Fechar', 'conexao' ); ?>">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
				stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false">
				<path d="M6 6 18 18M18 6 6 18"/>
			</svg>
		</button>

		<h2 class="cnx-modal__titulo" id="cnx-modal-titulo">
			<?php esc_html_e( 'Solicitar orçamento', 'conexao' ); ?>
		</h2>

		<p class="cnx-modal__aviso">
			<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/wpp.png' ) ); ?>"
				alt="" width="21" height="23" aria-hidden="true">
			<?php esc_html_e( 'Vamos abrir o WhatsApp com as informações certas para este produto.', 'conexao' ); ?>
		</p>

		<form class="cnx-modal__form" method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-cnx-modal-form>

			<input type="hidden" name="action" value="cnx_orcamento">
			<input type="hidden" name="cnx_produto_id" value="<?php echo esc_attr( (string) $cnx_produto_id ); ?>">
			<?php // Preenchido pelo JS com as escolhas do configurador. ?>
			<input type="hidden" name="cnx_resumo" value="" data-cnx-modal-resumo>
			<?php wp_nonce_field( 'cnx_orcamento', 'cnx_orcamento_nonce' ); ?>

			<label class="screen-reader-text" for="cnx_modal_nome"><?php esc_html_e( 'Nome', 'conexao' ); ?></label>
			<input type="text" id="cnx_modal_nome" name="cnx_nome" class="cnx-campo"
				placeholder="<?php esc_attr_e( 'Nome', 'conexao' ); ?>" required autocomplete="name">

			<label class="screen-reader-text" for="cnx_modal_tel"><?php esc_html_e( 'Telefone/WhatsApp', 'conexao' ); ?></label>
			<input type="tel" id="cnx_modal_tel" name="cnx_telefone" class="cnx-campo"
				placeholder="<?php esc_attr_e( 'Telefone/WhatsApp', 'conexao' ); ?>" required autocomplete="tel">

			<label class="screen-reader-text" for="cnx_modal_email"><?php esc_html_e( 'E-mail', 'conexao' ); ?></label>
			<input type="email" id="cnx_modal_email" name="cnx_email" class="cnx-campo"
				placeholder="<?php esc_attr_e( 'E-mail', 'conexao' ); ?>" autocomplete="email">

			<div class="cnx-form__armadilha" aria-hidden="true">
				<label for="cnx_modal_site"><?php esc_html_e( 'Deixe em branco', 'conexao' ); ?></label>
				<input type="text" id="cnx_modal_site" name="cnx_site" tabindex="-1" autocomplete="off">
			</div>

			<button type="submit" class="cnx-modal__enviar">
				<?php esc_html_e( 'Continuar para o WhatsApp', 'conexao' ); ?>
			</button>
		</form>
	</div>
</div>
