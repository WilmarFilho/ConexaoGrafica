/**
 * Carrossel do hero — sem dependência externa.
 *
 * Setas, pontos, autoplay, arraste no touch e navegação por teclado.
 * Respeita prefers-reduced-motion: quem pediu menos movimento não recebe autoplay.
 */
( function () {
	'use strict';

	var MENOS_MOVIMENTO = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	function Carrossel( raiz ) {
		var trilho  = raiz.querySelector( '[data-cnx-trilho]' );
		var slides  = Array.prototype.slice.call( raiz.querySelectorAll( '[data-cnx-slide]' ) );
		var pontos  = Array.prototype.slice.call( raiz.querySelectorAll( '[data-cnx-ponto]' ) );
		var total   = slides.length;
		var atual   = 0;
		var timer   = null;
		var intervalo = ( parseInt( raiz.dataset.autoplay, 10 ) || 0 ) * 1000;

		if ( ! trilho || total < 2 ) {
			return;
		}

		function mostrar( indice ) {
			// Circular: passar do fim volta ao começo.
			atual = ( indice + total ) % total;

			trilho.style.transform = 'translateX(' + ( -100 * atual ) + '%)';

			slides.forEach( function ( slide, i ) {
				// Slide fora de vista não deve ser lido nem receber foco.
				// aria-hidden="" não vale nada para o leitor de tela: ou "true", ou fora.
				if ( i === atual ) {
					slide.removeAttribute( 'aria-hidden' );
				} else {
					slide.setAttribute( 'aria-hidden', 'true' );
				}

				slide.querySelectorAll( 'a, button' ).forEach( function ( alvo ) {
					alvo.tabIndex = i === atual ? 0 : -1;
				} );
			} );

			pontos.forEach( function ( ponto, i ) {
				ponto.setAttribute( 'aria-selected', String( i === atual ) );
			} );
		}

		function iniciarAutoplay() {
			pararAutoplay();

			if ( intervalo > 0 && ! MENOS_MOVIMENTO.matches ) {
				timer = window.setInterval( function () {
					mostrar( atual + 1 );
				}, intervalo );
			}
		}

		function pararAutoplay() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		function reiniciar() {
			// Depois de uma ação do usuário, o relógio recomeça do zero.
			iniciarAutoplay();
		}

		var anterior = raiz.querySelector( '[data-cnx-anterior]' );
		var proximo  = raiz.querySelector( '[data-cnx-proximo]' );

		if ( anterior ) {
			anterior.addEventListener( 'click', function () {
				mostrar( atual - 1 );
				reiniciar();
			} );
		}

		if ( proximo ) {
			proximo.addEventListener( 'click', function () {
				mostrar( atual + 1 );
				reiniciar();
			} );
		}

		pontos.forEach( function ( ponto ) {
			ponto.addEventListener( 'click', function () {
				mostrar( parseInt( ponto.dataset.cnxPonto, 10 ) || 0 );
				reiniciar();
			} );
		} );

		raiz.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowLeft' === e.key ) {
				mostrar( atual - 1 );
				reiniciar();
			} else if ( 'ArrowRight' === e.key ) {
				mostrar( atual + 1 );
				reiniciar();
			}
		} );

		// Pausa enquanto o usuário está lendo ou tabulando dentro do carrossel.
		raiz.addEventListener( 'mouseenter', pararAutoplay );
		raiz.addEventListener( 'mouseleave', iniciarAutoplay );
		raiz.addEventListener( 'focusin', pararAutoplay );
		raiz.addEventListener( 'focusout', function ( e ) {
			if ( ! raiz.contains( e.relatedTarget ) ) {
				iniciarAutoplay();
			}
		} );

		// Aba em segundo plano não precisa girar slide.
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				pararAutoplay();
			} else {
				iniciarAutoplay();
			}
		} );

		/* --- Arraste / swipe --- */

		var inicioX = 0;
		var inicioY = 0;
		var arrastando = false;

		raiz.addEventListener( 'touchstart', function ( e ) {
			inicioX = e.touches[ 0 ].clientX;
			inicioY = e.touches[ 0 ].clientY;
			arrastando = true;
			pararAutoplay();
		}, { passive: true } );

		raiz.addEventListener( 'touchend', function ( e ) {
			if ( ! arrastando ) {
				return;
			}

			arrastando = false;

			var deltaX = e.changedTouches[ 0 ].clientX - inicioX;
			var deltaY = e.changedTouches[ 0 ].clientY - inicioY;

			// Só conta como swipe se for claramente horizontal — senão é rolagem.
			if ( Math.abs( deltaX ) > 45 && Math.abs( deltaX ) > Math.abs( deltaY ) ) {
				mostrar( deltaX < 0 ? atual + 1 : atual - 1 );
			}

			iniciarAutoplay();
		}, { passive: true } );

		mostrar( 0 );
		iniciarAutoplay();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-cnx-carrossel]' ).forEach( Carrossel );
	} );
} )();
