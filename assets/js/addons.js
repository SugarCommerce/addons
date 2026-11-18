/**
 * The add-ons catalogue: choosing a category and searching.
 *
 * Everything is done over the cards already on the page. Each card says
 * which category it is in and what words it is found by, and this hides
 * the ones that do not match; nothing is asked of any server.
 *
 * It knows nothing of whose catalogue it is. The markup it works on is
 * drawn by Renderer (src/Renderer.php), and the two travel together.
 */
( function () {
	'use strict';

	/**
	 * Set one catalogue up.
	 *
	 * @param {HTMLElement} root The catalogue.
	 */
	function catalogue( root ) {
		const cards = Array.from(
			root.querySelectorAll( '[data-addons-card]' )
		);
		const pills = Array.from(
			root.querySelectorAll( '[data-addons-category]' )
		);
		const search = root.querySelector( '[data-addons-search]' );
		const empty = root.querySelector( '[data-addons-empty]' );
		const status = root.querySelector( '[data-addons-status]' );
		const clear = root.querySelector( '[data-addons-clear]' );

		let category = root.getAttribute( 'data-category' ) || '';
		let announced = false;

		/**
		 * Show the cards that match, and say how many that is.
		 */
		function apply() {
			const words = ( search ? search.value : '' )
				.toLowerCase()
				.split( /\s+/ )
				.filter( Boolean );
			let shown = 0;

			cards.forEach( function ( card ) {
				const text = card.getAttribute( 'data-search' ) || '';
				const matches =
					( '' === category ||
						card.getAttribute( 'data-category' ) === category ) &&
					words.every( function ( word ) {
						return text.indexOf( word ) !== -1;
					} );

				card.hidden = ! matches;
				shown += matches ? 1 : 0;
			} );

			// A section with none of its cards showing is a heading over
			// nothing.
			root.querySelectorAll( '[data-addons-section]' ).forEach(
				function ( section ) {
					section.hidden = ! section.querySelector(
						'[data-addons-card]:not([hidden])'
					);
				}
			);

			if ( empty ) {
				empty.hidden = shown > 0;
			}

			// Not on the way in: a page that announced itself as it
			// loaded would talk over its own title.
			if ( status && announced ) {
				status.textContent = (
					root.getAttribute(
						1 === shown ? 'data-shown-one' : 'data-shown-many'
					) || '%d'
				).replace( '%d', String( shown ) );
			}

			announced = true;
		}

		/**
		 * Choose a category.
		 *
		 * @param {HTMLElement} pill  The pill chosen.
		 * @param {boolean}     focus Whether to move focus to it.
		 */
		function choose( pill, focus ) {
			category = pill.getAttribute( 'data-addons-category' ) || '';

			pills.forEach( function ( other ) {
				const chosen = other === pill;

				other.setAttribute( 'aria-checked', chosen ? 'true' : 'false' );
				other.tabIndex = chosen ? 0 : -1;
			} );

			if ( focus ) {
				pill.focus();
			}

			apply();
		}

		pills.forEach( function ( pill, index ) {
			pill.addEventListener( 'click', function () {
				choose( pill, false );
			} );

			// A group of radios: the arrows move the choice, and Home and
			// End go to its ends.
			pill.addEventListener( 'keydown', function ( event ) {
				let next = -1;

				if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
					next = ( index + 1 ) % pills.length;
				} else if (
					'ArrowLeft' === event.key ||
					'ArrowUp' === event.key
				) {
					next = ( index - 1 + pills.length ) % pills.length;
				} else if ( 'Home' === event.key ) {
					next = 0;
				} else if ( 'End' === event.key ) {
					next = pills.length - 1;
				}

				if ( next > -1 ) {
					event.preventDefault();
					choose( pills[ next ], true );
				}
			} );
		} );

		if ( search ) {
			search.addEventListener( 'input', apply );
		}

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				if ( search ) {
					search.value = '';
				}

				if ( pills.length ) {
					choose( pills[ 0 ], false );
				} else {
					category = '';
					apply();
				}

				if ( search ) {
					search.focus();
				}
			} );
		}

		apply();
	}

	document.querySelectorAll( '[data-addons]' ).forEach( catalogue );
} )();
