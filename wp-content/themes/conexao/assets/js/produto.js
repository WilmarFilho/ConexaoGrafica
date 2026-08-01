/**
 * Página do produto: galeria, configurador e modal de orçamento.
 *
 * O configurador não monta mais o link do WhatsApp: ele só guarda as escolhas e
 * as entrega ao modal, que envia pelo servidor. Assim o lead fica gravado antes
 * do redirecionamento.
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

	/**
	 * Devolve as escolhas do configurador como texto, uma por linha.
	 */
	function montarResumo( escolhas, grupos ) {
		var linhas = [];

		grupos.forEach( function ( grupo ) {
			var titulo = grupo.dataset.titulo;

			if ( escolhas[ titulo ] ) {
				linhas.push( titulo + ': ' + escolhas[ titulo ] );
			}
		} );

		return linhas.join( '\n' );
	}

	function iniciarModal( obterResumo ) {
		var modal = document.querySelector( '[data-cnx-modal]' );

		if ( ! modal ) {
			return null;
		}

		var campoResumo = modal.querySelector( '[data-cnx-modal-resumo]' );
		var primeiro    = modal.querySelector( 'input:not([type="hidden"])' );

		function abrir() {
			if ( campoResumo && obterResumo ) {
				campoResumo.value = obterResumo();
			}

			modal.classList.add( 'esta-aberto' );
			document.body.classList.add( 'cnx-sem-rolagem' );

			if ( primeiro ) {
				window.requestAnimationFrame( function () {
					primeiro.focus();
				} );
			}
		}

		function fechar() {
			modal.classList.remove( 'esta-aberto' );
			document.body.classList.remove( 'cnx-sem-rolagem' );
		}

		modal.querySelectorAll( '[data-cnx-modal-fechar]' ).forEach( function ( alvo ) {
			alvo.addEventListener( 'click', fechar );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && modal.classList.contains( 'esta-aberto' ) ) {
				fechar();
			}
		} );

		return { abrir: abrir, fechar: fechar };
	}

	function iniciarProduto() {
		var form    = document.querySelector( '[data-cnx-config]' );
		var grupos  = form ? Array.prototype.slice.call( form.querySelectorAll( '[data-cnx-grupo]' ) ) : [];
		var aviso   = form ? form.querySelector( '[data-cnx-aviso]' ) : null;
		var botoes  = Array.prototype.slice.call( document.querySelectorAll( '[data-cnx-abrir-modal]' ) );
		var escolhas = {};

		var modal = iniciarModal( function () {
			return montarResumo( escolhas, grupos );
		} );

		if ( ! modal ) {
			return;
		}

		function pronto() {
			// Sem configurador não há o que validar: o botão já vale.
			if ( ! grupos.length ) {
				return true;
			}

			return ! grupos.some( function ( grupo ) {
				return '1' === grupo.dataset.obrigatorio && ! escolhas[ grupo.dataset.titulo ];
			} );
		}

		function atualizar() {
			grupos.forEach( function ( grupo ) {
				var titulo = grupo.dataset.titulo;
				var linha  = form.querySelector( '[data-cnx-resumo="' + CSS.escape( titulo ) + '"] [data-cnx-valor]' );

				if ( linha ) {
					linha.textContent = escolhas[ titulo ] || 'Selecione';
				}
			} );

			var ok = pronto();

			botoes.forEach( function ( botao ) {
				botao.setAttribute( 'aria-disabled', String( ! ok ) );
			} );

			if ( aviso ) {
				aviso.hidden = ok;
			}
		}

		botoes.forEach( function ( botao ) {
			botao.addEventListener( 'click', function () {
				// aria-disabled não impede o clique num <button>; a checagem faz isso.
				if ( 'true' === botao.getAttribute( 'aria-disabled' ) ) {
					return;
				}

				modal.abrir();
			} );
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

	/**
	 * Funil GA4 da página de produto: quem chega vê (view_item), quem abre o
	 * modal inicia o pedido (begin_checkout) e quem envia vira lead
	 * (generate_lead). O envio real ao Google respeita o Consent Mode.
	 */
	function funil() {
		var rastrear = window.cnxRastrear;
		var titulo   = document.querySelector( '.cnx-produto__titulo' );

		if ( ! rastrear || ! titulo ) {
			return;
		}

		var item = { item_name: titulo.textContent.trim() };

		rastrear( 'view_item', { items: [ item ] } );

		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-cnx-abrir-modal]' ) ) {
				rastrear( 'begin_checkout', { items: [ item ] } );
			}
		}, true );

		document.addEventListener( 'submit', function ( e ) {
			if ( e.target.matches( '[data-cnx-modal-form]' ) ) {
				rastrear( 'generate_lead', { lead_source: 'orcamento_produto', items: [ item ] } );
			}
		}, true );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		iniciarGaleria();
		iniciarProduto();
		funil();
	} );
} )();
