/**
 * Comportamentos presentes em todas as páginas.
 */
( function () {
	'use strict';

	/**
	 * O botão vive no rodapé, não flutuando na tela — quem chegou até ele já
	 * rolou a página inteira, então não há o que esconder nem rolagem a vigiar.
	 */
	function botaoVoltarAoTopo() {
		var botao = document.querySelector( '[data-cnx-topo]' );

		if ( ! botao ) {
			return;
		}

		botao.addEventListener( 'click', function () {
			var suave = ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			window.scrollTo( { top: 0, behavior: suave ? 'smooth' : 'auto' } );
		} );
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

	/**
	 * Compartilhar: no celular abre a folha nativa do sistema; no desktop, onde
	 * navigator.share raramente existe, copia o link e confirma no próprio botão.
	 */
	function compartilhar() {
		document.querySelectorAll( '[data-cnx-compartilhar]' ).forEach( function ( botao ) {
			botao.addEventListener( 'click', function () {
				var dados = {
					title: botao.dataset.titulo || document.title,
					url: botao.dataset.url || window.location.href
				};

				if ( navigator.share ) {
					navigator.share( dados ).catch( function () {} );
					return;
				}

				if ( ! navigator.clipboard ) {
					return;
				}

				navigator.clipboard.writeText( dados.url ).then( function () {
					botao.classList.add( 'esta-copiado' );
					botao.setAttribute( 'title', 'Link copiado' );

					window.setTimeout( function () {
						botao.classList.remove( 'esta-copiado' );
						botao.removeAttribute( 'title' );
					}, 2000 );
				} ).catch( function () {} );
			} );
		} );
	}

	/**
	 * Rastreamento dos cliques principais.
	 *
	 * Os eventos vão para window.dataLayer (padrão do Google). Com o GA4
	 * configurado no painel eles viram eventos de verdade; sem ele, o push é
	 * inofensivo — nada quebra e nada é enviado a lugar nenhum.
	 */
	function rastrear( evento, dados ) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( Object.assign( { event: evento }, dados || {} ) );

		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'event', evento, dados || {} );
		}
	}

	function rastreamento() {
		document.addEventListener( 'click', function ( e ) {
			var alvo = e.target.closest( 'a, button' );

			if ( ! alvo ) {
				return;
			}

			var href = alvo.getAttribute( 'href' ) || '';

			if ( href.indexOf( 'wa.me' ) !== -1 || href.indexOf( 'api.whatsapp.com' ) !== -1 ) {
				rastrear( 'clique_whatsapp', { local: alvo.className || 'link' } );
			} else if ( href.indexOf( 'tel:' ) === 0 ) {
				rastrear( 'clique_telefone', { numero: href.slice( 4 ) } );
			} else if ( href.indexOf( 'mailto:' ) === 0 ) {
				rastrear( 'clique_email', {} );
			} else if ( alvo.hasAttribute( 'data-cnx-abrir-modal' ) ) {
				rastrear( 'abriu_orcamento', { pagina: window.location.pathname } );
			} else if ( alvo.classList.contains( 'cnx-topbar__cta' ) || alvo.classList.contains( 'cnx-btn--primario' ) ) {
				rastrear( 'clique_solicitar_orcamento', { pagina: window.location.pathname } );
			}
		}, true );

		document.addEventListener( 'submit', function ( e ) {
			var form = e.target;

			if ( form.matches( '[data-cnx-modal-form]' ) ) {
				rastrear( 'enviou_orcamento_produto', { pagina: window.location.pathname } );
			} else if ( form.classList.contains( 'cnx-form-contato' ) ) {
				rastrear( 'enviou_contato', {} );
			} else if ( form.classList.contains( 'cnx-form' ) ) {
				rastrear( 'enviou_desconto', {} );
			} else if ( form.classList.contains( 'cnx-busca' ) || form.classList.contains( 'cnx-busca-lateral' ) ) {
				rastrear( 'buscou', { termo: ( form.querySelector( 'input[name="s"]' ) || {} ).value || '' } );
			}
		}, true );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		botaoVoltarAoTopo();
		menuMobile();
		compartilhar();
		rastreamento();
	} );
} )();
