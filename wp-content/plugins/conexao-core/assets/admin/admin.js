/* global jQuery, wp, cnxAdmin */
( function ( $ ) {
	'use strict';

	/* ---------------------------------------------------------------
	 * Repetidores (grupos de configuração e blocos de conteúdo)
	 * --------------------------------------------------------------- */

	function reindexar( $repeater ) {
		$repeater.find( '> .cnx-repeater__rows > .cnx-row' ).each( function ( i ) {
			$( this ).find( '[name]' ).each( function () {
				this.name = this.name.replace( /\[(\d+|__INDEX__)\]/, '[' + i + ']' );
			} );
		} );

		$repeater.attr( 'data-next-index', $repeater.find( '> .cnx-repeater__rows > .cnx-row' ).length );
	}

	$( document ).on( 'click', '.cnx-repeater__add', function () {
		var $repeater = $( this ).closest( '.cnx-repeater' );
		var index     = parseInt( $repeater.attr( 'data-next-index' ), 10 ) || 0;
		var html      = $repeater.find( '> .cnx-repeater__template' ).html();

		$repeater.find( '> .cnx-repeater__rows' ).append(
			html.replace( /__INDEX__/g, index )
		);

		$repeater.attr( 'data-next-index', index + 1 );
	} );

	$( document ).on( 'click', '.cnx-row__remove', function () {
		var $repeater = $( this ).closest( '.cnx-repeater' );

		if ( ! window.confirm( cnxAdmin.confirmar ) ) {
			return;
		}

		$( this ).closest( '.cnx-row' ).remove();
		reindexar( $repeater );
	} );

	$( function () {
		$( '.cnx-repeater__rows' ).sortable( {
			handle: '.cnx-row__handle',
			placeholder: 'cnx-row is-dragging',
			forcePlaceholderSize: true,
			update: function () {
				reindexar( $( this ).closest( '.cnx-repeater' ) );
			}
		} );
	} );

	/* ---------------------------------------------------------------
	 * Galeria — seletor de mídia nativo do WP
	 * --------------------------------------------------------------- */

	function sincronizarGaleria( $galeria ) {
		var ids = $galeria.find( '.cnx-galeria__item' ).map( function () {
			return $( this ).attr( 'data-id' );
		} ).get();

		$galeria.find( 'input[type="hidden"]' ).first().val( ids.join( ',' ) );
	}

	$( document ).on( 'click', '.cnx-galeria__add', function ( e ) {
		e.preventDefault();

		var $galeria = $( this ).closest( '[data-cnx-galeria]' );
		var $campo   = $galeria.find( 'input[type="hidden"]' ).first();
		// A caixa de fundo do slide aceita uma imagem só.
		var unica    = $galeria.is( '[data-cnx-unica]' );
		var atuais   = ( $campo.val() || '' ).split( ',' ).filter( Boolean );

		var frame = wp.media( {
			title: cnxAdmin.galeriaTitulo,
			button: { text: cnxAdmin.galeriaBotao },
			library: { type: 'image' },
			multiple: unica ? false : 'add'
		} );

		// Pré-seleciona o que já está na galeria.
		frame.on( 'open', function () {
			var selecao = frame.state().get( 'selection' );

			atuais.forEach( function ( id ) {
				var anexo = wp.media.attachment( id );
				anexo.fetch();
				selecao.add( anexo );
			} );
		} );

		frame.on( 'select', function () {
			var $list = $galeria.find( '.cnx-galeria__list' );

			$list.empty();

			frame.state().get( 'selection' ).each( function ( anexo ) {
				var dados = anexo.toJSON();
				var url   = ( dados.sizes && dados.sizes.thumbnail )
					? dados.sizes.thumbnail.url
					: dados.url;

				$list.append(
					$( '<li class="cnx-galeria__item"></li>' )
						.attr( 'data-id', dados.id )
						.append( $( '<img>' ).attr( { src: url, alt: dados.alt || '' } ) )
						.append( '<button type="button" class="cnx-galeria__remove">✕</button>' )
				);
			} );

			sincronizarGaleria( $galeria );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.cnx-galeria__remove', function () {
		var $galeria = $( this ).closest( '[data-cnx-galeria]' );

		$( this ).closest( '.cnx-galeria__item' ).remove();
		sincronizarGaleria( $galeria );
	} );

	$( function () {
		$( '.cnx-galeria__list' ).sortable( {
			update: function () {
				sincronizarGaleria( $( this ).closest( '[data-cnx-galeria]' ) );
			}
		} );
	} );

} )( jQuery );
