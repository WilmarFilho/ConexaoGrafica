/**
 * "Carregar mais" das listagens.
 *
 * Melhoria progressiva: o botão é um link real para a página seguinte e
 * funciona sem JavaScript. Aqui interceptamos o clique, buscamos a página e
 * anexamos só os cards — o usuário não perde a posição de rolagem.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var botao = document.querySelector( '[data-cnx-carregar-mais]' );
		var lista = document.querySelector( '[data-cnx-listagem]' );

		if ( ! botao || ! lista ) {
			return;
		}

		var carregando = false;

		botao.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			if ( carregando ) {
				return;
			}

			var url = botao.getAttribute( 'href' );

			if ( ! url ) {
				return;
			}

			carregando = true;
			botao.setAttribute( 'aria-busy', 'true' );
			var rotulo = botao.textContent;
			botao.textContent = 'Carregando...';

			window.fetch( url, { credentials: 'same-origin' } )
				.then( function ( resposta ) {
					if ( ! resposta.ok ) {
						throw new Error( resposta.status );
					}

					return resposta.text();
				} )
				.then( function ( html ) {
					var doc = new DOMParser().parseFromString( html, 'text/html' );
					var novos = doc.querySelectorAll( '[data-cnx-listagem] > li' );

					novos.forEach( function ( item ) {
						lista.appendChild( document.importNode( item, true ) );
					} );

					// O botão da página buscada aponta para a página seguinte.
					var proximo = doc.querySelector( '[data-cnx-carregar-mais]' );

					if ( proximo && proximo.getAttribute( 'href' ) ) {
						botao.setAttribute( 'href', proximo.getAttribute( 'href' ) );
						botao.textContent = rotulo;
					} else {
						// Chegou ao fim: o botão não tem mais para onde levar.
						botao.parentNode.removeChild( botao );
					}
				} )
				.catch( function () {
					// Se a busca falhou, devolvemos o link: o clique seguinte navega.
					botao.textContent = rotulo;
					window.location.href = url;
				} )
				.finally( function () {
					carregando = false;
					botao.removeAttribute( 'aria-busy' );
				} );
		} );
	} );
} )();
