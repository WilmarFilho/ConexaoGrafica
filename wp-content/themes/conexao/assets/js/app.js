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

	// produto.js reaproveita para os eventos de funil.
	window.cnxRastrear = rastrear;

	/**
	 * Aviso de cookies + Consent Mode.
	 *
	 * O padrão "negado" já foi definido no <head> antes das tags; aqui só entra
	 * a decisão do visitante — aplicada na hora e lembrada nas próximas visitas.
	 */
	function consentimento() {
		var aviso = document.querySelector( '[data-cnx-cookies]' );

		if ( ! aviso ) {
			return;
		}

		var escolha = null;

		try {
			escolha = localStorage.getItem( 'cnx_consentimento' );
		} catch ( e ) {}

		if ( escolha ) {
			return; // Já decidiu: o head reaplicou; o aviso continua escondido.
		}

		aviso.hidden = false;

		function decidir( aceitou ) {
			try {
				localStorage.setItem( 'cnx_consentimento', aceitou ? 'granted' : 'denied' );
			} catch ( e ) {}

			if ( aceitou && typeof window.gtag === 'function' ) {
				window.gtag( 'consent', 'update', {
					ad_storage: 'granted',
					ad_user_data: 'granted',
					ad_personalization: 'granted',
					analytics_storage: 'granted'
				} );
			}

			rastrear( 'cnx_consentimento', { escolha: aceitou ? 'aceitou' : 'recusou' } );
			aviso.hidden = true;
		}

		aviso.querySelector( '[data-cnx-cookies-aceitar]' ).addEventListener( 'click', function () {
			decidir( true );
		} );

		aviso.querySelector( '[data-cnx-cookies-recusar]' ).addEventListener( 'click', function () {
			decidir( false );
		} );
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
			} else if ( alvo.classList.contains( 'cnx-card-produto' ) || alvo.classList.contains( 'cnx-card-simples' ) ) {
				// Clique num card de produto: select_item, nome do funil GA4.
				var nomeEl = alvo.querySelector( 'strong, .cnx-card-simples__nome' );
				var nome   = nomeEl ? nomeEl.textContent.trim() : '';

				rastrear( 'select_item', {
					item_list_name: window.location.pathname,
					items: [ { item_name: nome.replace( /:$/, '' ) } ]
				} );
			}
		}, true );

		document.addEventListener( 'submit', function ( e ) {
			var form = e.target;

			if ( form.matches( '[data-cnx-modal-form]' ) ) {
				rastrear( 'enviou_orcamento_produto', { pagina: window.location.pathname } );
			} else if ( form.classList.contains( 'cnx-form-contato' ) ) {
				rastrear( 'enviou_contato', {} );
				rastrear( 'generate_lead', { lead_source: 'contato' } );
			} else if ( form.classList.contains( 'cnx-form' ) ) {
				rastrear( 'enviou_desconto', {} );
				rastrear( 'generate_lead', { lead_source: 'desconto_rodape' } );
			} else if ( form.classList.contains( 'cnx-busca' ) || form.classList.contains( 'cnx-busca-lateral' ) ) {
				rastrear( 'buscou', { termo: ( form.querySelector( 'input[name="s"]' ) || {} ).value || '' } );
			}
		}, true );
	}

	/**
	 * Página de resultados: filtros e ordenação aplicam na hora, buscando a
	 * própria página filtrada e trocando só o miolo de resultados — sem
	 * recarregar. A URL acompanha, então dá para compartilhar e recarregar.
	 *
	 * Os controles (ordenar, grade/lista) vivem dentro do miolo trocado, por
	 * isso os handlers são delegados no documento em vez de presos aos nós.
	 */
	function busca() {
		var filtros = document.querySelector( '[data-cnx-filtros]' );

		if ( ! filtros ) {
			return;
		}

		var fundo    = document.querySelector( '[data-cnx-filtros-fundo]' );
		var abrirF   = document.querySelector( '[data-cnx-filtros-abrir]' );
		var emLista  = false;
		var pedido   = 0; // Descarta respostas fora de ordem em cliques rápidos.

		function fecharFiltros() {
			filtros.classList.remove( 'esta-aberto' );

			if ( fundo ) {
				fundo.hidden = true;
			}

			document.body.classList.remove( 'cnx-sem-rolagem' );
		}

		function urlDosFiltros() {
			var url    = new URL( filtros.getAttribute( 'action' ), window.location.href );
			var ordem  = document.querySelector( '[data-cnx-ordem]' );

			url.search = new URLSearchParams( new FormData( filtros ) ).toString();

			if ( ordem && ordem.value ) {
				url.searchParams.set( 'ordem', ordem.value );
			} else {
				url.searchParams.delete( 'ordem' );
			}

			return url.toString();
		}

		function aplicarVista() {
			var lista = document.querySelector( '[data-cnx-resultados]' );

			if ( lista ) {
				lista.classList.toggle( 'esta-em-lista', emLista );
			}

			document.querySelectorAll( '[data-cnx-vista]' ).forEach( function ( botao ) {
				botao.setAttribute( 'aria-pressed', String( ( 'lista' === botao.dataset.cnxVista ) === emLista ) );
			} );
		}

		function atualizar() {
			var url  = urlDosFiltros();
			var este = ++pedido;

			document.querySelector( '.cnx-busca-pagina__resultados' ).setAttribute( 'aria-busy', 'true' );

			window.fetch( url ).then( function ( resposta ) {
				return resposta.text();
			} ).then( function ( html ) {
				if ( este !== pedido ) {
					return;
				}

				var doc   = new DOMParser().parseFromString( html, 'text/html' );
				var novo  = doc.querySelector( '.cnx-busca-pagina__resultados' );
				var atual = document.querySelector( '.cnx-busca-pagina__resultados' );

				if ( ! novo || ! atual ) {
					window.location.href = url;
					return;
				}

				atual.replaceWith( novo );

				var contagem = doc.querySelector( '.cnx-busca-pagina__contagem' );
				var alvo     = document.querySelector( '.cnx-busca-pagina__contagem' );

				if ( contagem && alvo ) {
					alvo.textContent = contagem.textContent;
				}

				aplicarVista();
				window.history.replaceState( null, '', url );
			} ).catch( function () {
				window.location.href = url; // Sem fetch, a navegação resolve.
			} );
		}

		// Marcar qualquer opção aplica na hora.
		filtros.addEventListener( 'change', atualizar );

		// "Aplicar filtros": no celular confirma e fecha o painel.
		filtros.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			fecharFiltros();
			atualizar();
		} );

		// "Limpar filtros" desmarca tudo e volta à listagem inicial do termo.
		var limpar = filtros.querySelector( '.cnx-filtros__limpar' );

		if ( limpar ) {
			limpar.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				filtros.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( caixa ) {
					caixa.checked = false;
				} );
				atualizar();
			} );
		}

		document.addEventListener( 'change', function ( e ) {
			if ( e.target.matches( '[data-cnx-ordem]' ) ) {
				atualizar();
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			var botao = e.target.closest( '[data-cnx-vista]' );

			if ( botao ) {
				emLista = 'lista' === botao.dataset.cnxVista;
				aplicarVista();
			}
		} );

		// No celular os filtros abrem como painel sobre a página (botão funil).
		if ( abrirF ) {
			abrirF.addEventListener( 'click', function () {
				filtros.classList.add( 'esta-aberto' );

				if ( fundo ) {
					fundo.hidden = false;
				}

				document.body.classList.add( 'cnx-sem-rolagem' );
			} );

			var fecharF = filtros.querySelector( '[data-cnx-filtros-fechar]' );

			if ( fecharF ) {
				fecharF.addEventListener( 'click', fecharFiltros );
			}

			if ( fundo ) {
				fundo.addEventListener( 'click', fecharFiltros );
			}

			document.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) {
					fecharFiltros();
				}
			} );
		}

		// Voltar/avançar do navegador restaura o estado daquela URL.
		window.addEventListener( 'popstate', function () {
			window.location.reload();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		botaoVoltarAoTopo();
		menuMobile();
		compartilhar();
		rastreamento();
		consentimento();
		busca();
	} );
} )();
