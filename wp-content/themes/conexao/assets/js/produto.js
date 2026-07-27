/**
 * Configurador do produto.
 *
 * Cada grupo aceita uma escolha. Quando todos os grupos obrigatórios estão
 * preenchidos, o botão vira um link wa.me com a mensagem já montada.
 */
( function () {
	'use strict';

	function iniciarGaleria() {
		var principal = document.querySelector( '[data-cnx-galeria-principal]' );
		var thumbs    = document.querySelectorAll( '[data-cnx-thumb]' );

		if ( ! principal || ! thumbs.length ) {
			return;
		}

		thumbs.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				principal.src = thumb.dataset.full;
				principal.removeAttribute( 'srcset' );

				thumbs.forEach( function ( outro ) {
					outro.setAttribute( 'aria-current', String( outro === thumb ) );
				} );
			} );
		} );
	}

	function iniciarConfigurador( form ) {
		var grupos    = Array.prototype.slice.call( form.querySelectorAll( '[data-cnx-grupo]' ) );
		var cta       = form.querySelector( '[data-cnx-cta]' );
		var aviso     = form.querySelector( '[data-cnx-aviso]' );
		var escolhas  = {};

		function montarMensagem() {
			var linhas = [];

			if ( form.dataset.saudacao ) {
				linhas.push( form.dataset.saudacao, '' );
			}

			linhas.push( 'Produto: ' + form.dataset.produto );

			grupos.forEach( function ( grupo ) {
				var titulo = grupo.dataset.titulo;

				if ( escolhas[ titulo ] ) {
					linhas.push( titulo + ': ' + escolhas[ titulo ] );
				}
			} );

			linhas.push( '', form.dataset.url );

			return linhas.join( '\n' );
		}

		function atualizar() {
			// Espelha as escolhas no bloco de resumo.
			grupos.forEach( function ( grupo ) {
				var titulo = grupo.dataset.titulo;
				var linha  = form.querySelector( '[data-cnx-resumo="' + CSS.escape( titulo ) + '"] [data-cnx-valor]' );

				if ( linha ) {
					linha.textContent = escolhas[ titulo ] || 'Selecione';
				}
			} );

			var faltando = grupos.filter( function ( grupo ) {
				return '1' === grupo.dataset.obrigatorio && ! escolhas[ grupo.dataset.titulo ];
			} );

			var pronto = 0 === faltando.length && Boolean( form.dataset.numero );

			cta.setAttribute( 'aria-disabled', String( ! pronto ) );

			if ( pronto ) {
				cta.href = 'https://wa.me/' + form.dataset.numero +
					'?text=' + encodeURIComponent( montarMensagem() );
			} else {
				cta.removeAttribute( 'href' );
			}

			if ( aviso ) {
				aviso.hidden = pronto;
			}
		}

		// aria-disabled não bloqueia o clique num <a>; o listener faz isso.
		cta.addEventListener( 'click', function ( e ) {
			if ( 'true' === cta.getAttribute( 'aria-disabled' ) ) {
				e.preventDefault();
			}
		} );

		grupos.forEach( function ( grupo ) {
			var opcoes = Array.prototype.slice.call( grupo.querySelectorAll( '[data-cnx-opcao]' ) );

			opcoes.forEach( function ( opcao ) {
				opcao.addEventListener( 'click', function () {
					var jaSelecionada = 'true' === opcao.getAttribute( 'aria-pressed' );

					opcoes.forEach( function ( outra ) {
						outra.setAttribute( 'aria-pressed', 'false' );
					} );

					if ( jaSelecionada ) {
						// Clicar de novo desmarca.
						delete escolhas[ grupo.dataset.titulo ];
					} else {
						opcao.setAttribute( 'aria-pressed', 'true' );
						escolhas[ grupo.dataset.titulo ] = opcao.dataset.cnxOpcao;
					}

					atualizar();
				} );
			} );
		} );

		atualizar();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		iniciarGaleria();

		document.querySelectorAll( '[data-cnx-config]' ).forEach( iniciarConfigurador );
	} );
} )();
