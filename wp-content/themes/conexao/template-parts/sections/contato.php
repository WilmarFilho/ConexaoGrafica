<?php
/**
 * Canais de atendimento. Vem de [cnx_contato].
 *
 * @var array $args { whatsapp, horario, tel1, tel2, email }
 */

defined( 'ABSPATH' ) || exit;

$cnx_whatsapp = (string) ( $args['whatsapp'] ?? '' );
$cnx_horario  = (string) ( $args['horario'] ?? '' );
$cnx_tel1     = (string) ( $args['tel1'] ?? '' );
$cnx_tel2     = (string) ( $args['tel2'] ?? '' );
$cnx_email    = (string) ( $args['email'] ?? '' );

$cnx_tel_link = static fn( string $tel ): string => (string) preg_replace( '/\D/', '', $tel );
?>

<section class="cnx-secao cnx-contato">
	<div class="cnx-secao__inner">
		<div class="cnx-contato__grade">

			<?php if ( '' !== $cnx_whatsapp ) : ?>
				<a class="cnx-contato__cartao cnx-contato__cartao--zap" href="<?php echo esc_url( $cnx_whatsapp ); ?>"
					target="_blank" rel="noopener">
					<span class="cnx-contato__icone" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="26" height="26" focusable="false">
							<path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.16c-.25.69-1.44 1.32-1.99 1.4-.53.08-1.19.11-1.92-.12-.44-.14-1.01-.33-1.74-.64-3.06-1.32-5.06-4.4-5.21-4.6-.15-.2-1.25-1.66-1.25-3.17s.79-2.25 1.07-2.56c.28-.31.61-.38.81-.38h.58c.19 0 .44-.07.69.53.25.6.86 2.08.94 2.23.08.15.13.33.02.53-.1.2-.15.33-.3.5l-.45.53c-.15.15-.31.32-.13.63.17.31.77 1.28 1.66 2.07 1.14 1.02 2.1 1.34 2.4 1.49.3.15.48.13.65-.08.18-.2.75-.88.95-1.18.2-.3.4-.25.68-.15.28.1 1.76.83 2.06.98.3.15.5.23.58.35.07.13.07.72-.18 1.41Z"/>
						</svg>
					</span>
					<strong><?php esc_html_e( 'WhatsApp', 'conexao' ); ?></strong>
					<span><?php esc_html_e( 'Atendimento direto com um vendedor', 'conexao' ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( '' !== $cnx_tel1 || '' !== $cnx_tel2 ) : ?>
				<div class="cnx-contato__cartao">
					<span class="cnx-contato__icone" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
							stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
							<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.12.9.34 1.8.63 2.6a2 2 0 0 1-.45 2.1L8 9.7a16 16 0 0 0 6 6l1.3-1.27a2 2 0 0 1 2.1-.45c.85.3 1.73.5 2.63.63a2 2 0 0 1 1.7 2Z"/>
						</svg>
					</span>
					<strong><?php esc_html_e( 'Telefones', 'conexao' ); ?></strong>
					<span>
						<?php if ( '' !== $cnx_tel1 ) : ?>
							<a href="tel:<?php echo esc_attr( $cnx_tel_link( $cnx_tel1 ) ); ?>"><?php echo esc_html( $cnx_tel1 ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $cnx_tel1 && '' !== $cnx_tel2 ) : ?><span aria-hidden="true"> / </span><?php endif; ?>
						<?php if ( '' !== $cnx_tel2 ) : ?>
							<a href="tel:<?php echo esc_attr( $cnx_tel_link( $cnx_tel2 ) ); ?>"><?php echo esc_html( $cnx_tel2 ); ?></a>
						<?php endif; ?>
					</span>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $cnx_email ) : ?>
				<div class="cnx-contato__cartao">
					<span class="cnx-contato__icone" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
							stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
							<rect x="2" y="4" width="20" height="16" rx="2"/>
							<path d="m22 7-10 6L2 7"/>
						</svg>
					</span>
					<strong><?php esc_html_e( 'E-mail', 'conexao' ); ?></strong>
					<span><a href="mailto:<?php echo esc_attr( $cnx_email ); ?>"><?php echo esc_html( $cnx_email ); ?></a></span>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $cnx_horario ) : ?>
				<div class="cnx-contato__cartao">
					<span class="cnx-contato__icone" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
							stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
							<circle cx="12" cy="12" r="9"/>
							<path d="M12 7v5l3 2"/>
						</svg>
					</span>
					<strong><?php esc_html_e( 'Horário', 'conexao' ); ?></strong>
					<span><?php echo esc_html( $cnx_horario ); ?></span>
				</div>
			<?php endif; ?>

		</div>
	</div>
</section>
