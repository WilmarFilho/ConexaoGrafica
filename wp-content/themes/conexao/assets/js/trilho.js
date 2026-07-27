/**
 * Trilho de cards com rolagem horizontal.
 *
 * Serve a todas as seções em grade: no desktop elas são grid e não rolam, então
 * as setas se escondem sozinhas; no mobile viram carrossel de um card por vez.
 *
 * Usa a rolagem nativa (com scroll-snap no CSS) em vez de reposicionar itens:
 * teclado, touch e trackpad funcionam de graça.
 */
( function () {
	'use strict';

	function Trilho( raiz ) {
		var pista = raiz.querySelector( '[data-cnx-pista]' );
		var setas = Array.prototype.slice.call( raiz.querySelectorAll( '[data-cnx-rolar]' ) );

		if ( ! pista ) {
			return;
		}

		function passo() {
			var item = pista.querySelector( 'li' );

			if ( ! item ) {
				return pista.clientWidth;
			}

			var estilo = window.getComputedStyle( pista );
			var vao    = parseFloat( estilo.columnGap || estilo.gap ) || 0;

			// Rola de "uma tela cheia de itens", arredondado para o item inteiro.
			var largura = item.getBoundingClientRect().width + vao;
			var cabem   = Math.max( 1, Math.floor( pista.clientWidth / largura ) );

			return largura * cabem;
		}

		function atualizarSetas() {
			// 2px de folga: a rolagem raramente para no pixel exato.
			var noInicio = pista.scrollLeft <= 2;
			var noFim    = pista.scrollLeft + pista.clientWidth >= pista.scrollWidth - 2;

			setas.forEach( function ( seta ) {
				var direcao = parseInt( seta.dataset.cnxRolar, 10 );
				var esconder = direcao < 0 ? noInicio : noFim;

				seta.hidden = esconder;
			} );
		}

		setas.forEach( function ( seta ) {
			seta.addEventListener( 'click', function () {
				var direcao = parseInt( seta.dataset.cnxRolar, 10 ) || 1;

				pista.scrollBy( { left: direcao * passo(), behavior: 'smooth' } );
			} );
		} );

		pista.addEventListener( 'scroll', atualizarSetas, { passive: true } );
		window.addEventListener( 'resize', atualizarSetas );

		atualizarSetas();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-cnx-trilho-rolavel]' ).forEach( Trilho );
	} );
} )();
