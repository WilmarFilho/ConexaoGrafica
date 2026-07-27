/**
 * Comportamentos presentes em todas as páginas.
 */
( function () {
	'use strict';

	function botaoVoltarAoTopo() {
		var botao = document.querySelector( '[data-cnx-topo]' );

		if ( ! botao ) {
			return;
		}

		function avaliar() {
			// Só aparece depois que rolar o equivalente a uma tela.
			botao.hidden = window.scrollY < window.innerHeight * 0.8;
		}

		botao.addEventListener( 'click', function () {
			var suave = ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			window.scrollTo( { top: 0, behavior: suave ? 'smooth' : 'auto' } );
		} );

		window.addEventListener( 'scroll', avaliar, { passive: true } );
		avaliar();
	}

	function menuMobile() {
		var abrir  = document.querySelector( '[data-cnx-menu-abrir]' );
		var painel = document.querySelector( '[data-cnx-menu]' );

		if ( ! abrir || ! painel ) {
			return;
		}

		var fechar = painel.querySelector( '[data-cnx-menu-fechar]' );

		function estaAberto() {
			return painel.classList.contains( 'esta-aberto' );
		}

		function abre() {
			painel.classList.add( 'esta-aberto' );
			abrir.setAttribute( 'aria-expanded', 'true' );
			document.body.classList.add( 'cnx-sem-rolagem' );

			// O foco só pega depois que a visibility vira visible.
			if ( fechar ) {
				window.requestAnimationFrame( function () {
					fechar.focus();
				} );
			}
		}

		function fecha() {
			painel.classList.remove( 'esta-aberto' );
			abrir.setAttribute( 'aria-expanded', 'false' );
			document.body.classList.remove( 'cnx-sem-rolagem' );
			abrir.focus();
		}

		abrir.addEventListener( 'click', abre );

		if ( fechar ) {
			fechar.addEventListener( 'click', fecha );
		}

		// Clicar num link navega: o painel precisa sair do caminho.
		painel.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				fecha();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && estaAberto() ) {
				fecha();
			}
		} );

		// Se a tela alargar com o menu aberto, ele deixa de fazer sentido.
		window.matchMedia( '(min-width: 901px)' ).addEventListener( 'change', function ( e ) {
			if ( e.matches && estaAberto() ) {
				fecha();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		botaoVoltarAoTopo();
		menuMobile();
	} );
} )();
