<?php
/**
 * Desempenho: formatos modernos de imagem e menos recursos no front.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Miniaturas geradas em WebP.
 *
 * Vale para os tamanhos que o WordPress gera (cnx-card, large, medium...): é o
 * que as páginas servem via srcset. O arquivo original enviado permanece como
 * está — sempre dá para regenerar tudo de novo a partir dele.
 */
add_filter(
	'image_editor_output_format',
	static function ( array $formats ): array {
		$formats['image/png']  = 'image/webp';
		$formats['image/jpeg'] = 'image/webp';

		return $formats;
	}
);

/**
 * Qualidade do WebP: 82 mantém as artes chapadas da marca sem serrilhar
 * degradês, com arquivos ~3x menores que o PNG equivalente.
 */
add_filter(
	'wp_editor_set_quality',
	static function ( int $quality, string $mime ): int {
		return 'image/webp' === $mime ? 82 : $quality;
	},
	10,
	2
);

/**
 * Front sem lastro: nada de emoji, estilos de blocos que o tema não usa, nem
 * dashicons para visitante. Cada um custa requisição e CSS bloqueante — em
 * celular é o que segura a primeira pintura.
 */
add_action(
	'init',
	static function (): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rsd_link' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );

		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	},
	100
);
