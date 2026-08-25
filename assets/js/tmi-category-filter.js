( function () {
	'use strict';

	const formSelector      = '.tmi-category-filter';
	const resultsSelector   = '#tmi-product-results';
	const fallbackSelectors = [
		'.woocommerce-result-count',
		'.woocommerce-ordering',
		'ul.products',
		'.woocommerce-pagination'
	];

	let requestController = null;
	let priceTimer        = null;

	document.documentElement.classList.add( 'tmi-filter-js' );

	function getForm() {
		return document.querySelector( formSelector );
	}

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function enhanceFilterUi() {
		document.querySelectorAll( formSelector ).forEach( function ( form ) {
			const applyButton = form.querySelector( '.tmi-filter-apply' );

			if ( applyButton ) {
				applyButton.hidden = true;
				applyButton.setAttribute( 'aria-hidden', 'true' );
			}
		} );
	}

	function buildUrl( form ) {
		const action = form.getAttribute( 'action' ) || window.location.href;
		const url    = new URL( action, window.location.origin );
		const data   = new FormData( form );

		for ( const [ key, rawValue ] of data.entries() ) {
			const value = String( rawValue ).trim();

			if ( value !== '' ) {
				url.searchParams.append( key, value );
			}
		}

		return url.toString();
	}

	function styleLoadingTarget( element, loading ) {
		if ( ! element ) {
			return;
		}

		element.style.transition   = prefersReducedMotion() ? 'none' : 'opacity 180ms ease';
		element.style.opacity      = loading ? '0.45' : '1';
		element.style.pointerEvents = loading ? 'none' : '';
		element.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
	}

	function setLoading( loading ) {
		const form    = getForm();
		const results = document.querySelector( resultsSelector ) || document.querySelector( 'ul.products' );

		if ( form ) {
			form.classList.toggle( 'is-loading', loading );
			form.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
			form.style.opacity       = loading ? '0.72' : '1';
			form.style.pointerEvents = loading ? 'none' : '';
		}

		if ( results ) {
			results.classList.toggle( 'tmi-is-loading', loading );
			styleLoadingTarget( results, loading );
		}
	}

	function prepareIncomingResults( element ) {
		if ( ! element ) {
			return;
		}

		element.style.transition   = prefersReducedMotion() ? 'none' : 'opacity 180ms ease';
		element.style.opacity      = '0.45';
		element.style.pointerEvents = 'none';
		element.setAttribute( 'aria-busy', 'true' );
	}

	function replaceNodeFromResponse( parsedDocument, selector ) {
		const current = document.querySelector( selector );
		const next    = parsedDocument.querySelector( selector );

		if ( ! current || ! next ) {
			return false;
		}

		current.replaceWith( next );
		return true;
	}

	function replaceResults( parsedDocument ) {
		const currentResults = document.querySelector( resultsSelector );
		const nextResults    = parsedDocument.querySelector( resultsSelector );

		if ( currentResults && nextResults ) {
			prepareIncomingResults( nextResults );
			currentResults.replaceWith( nextResults );
			return true;
		}

		let replaced = false;

		fallbackSelectors.forEach( function ( selector ) {
			if ( replaceNodeFromResponse( parsedDocument, selector ) ) {
				replaced = true;
			}
		} );

		return replaced;
	}

	function replaceFilter( parsedDocument ) {
		const currentForm = getForm();
		const nextForm    = parsedDocument.querySelector( formSelector );

		if ( currentForm && nextForm ) {
			currentForm.replaceWith( nextForm );
			enhanceFilterUi();
		}
	}

	function notifyUpdated( url ) {
		document.dispatchEvent(
			new CustomEvent( 'tmiCategoryFilterUpdated', {
				detail: { url: url }
			} )
		);

		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'tmi_category_filter_updated', [ url ] );
		}
	}

	async function loadUrl( url, addToHistory ) {
		if ( requestController ) {
			requestController.abort();
		}

		requestController = new AbortController();
		setLoading( true );

		try {
			const response = await window.fetch( url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				},
				signal: requestController.signal
			} );

			if ( ! response.ok ) {
				throw new Error( 'TMI filter request failed with status ' + response.status );
			}

			const html           = await response.text();
			const parsedDocument = new DOMParser().parseFromString( html, 'text/html' );
			const resultsUpdated = replaceResults( parsedDocument );

			if ( ! resultsUpdated ) {
				window.location.assign( url );
				return;
			}

			replaceFilter( parsedDocument );

			if ( addToHistory ) {
				window.history.pushState( { tmiCategoryFilter: true }, '', url );
			}

			window.requestAnimationFrame( function () {
				setLoading( false );
			} );

			notifyUpdated( url );
		} catch ( error ) {
			if ( error.name === 'AbortError' ) {
				return;
			}

			setLoading( false );
			window.location.assign( url );
		}
	}

	function applyCurrentForm() {
		const form = getForm();

		if ( ! form ) {
			return;
		}

		loadUrl( buildUrl( form ), true );
	}

	document.addEventListener( 'submit', function ( event ) {
		const form = event.target.closest( formSelector );

		if ( ! form ) {
			return;
		}

		event.preventDefault();
		loadUrl( buildUrl( form ), true );
	} );

	document.addEventListener( 'change', function ( event ) {
		const field = event.target;
		const form  = field.closest( formSelector );

		if ( ! form ) {
			return;
		}

		if ( field.matches( 'input[type="checkbox"], input[type="radio"], select' ) ) {
			applyCurrentForm();
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		const field = event.target;
		const form  = field.closest( formSelector );

		if ( ! form || ! field.matches( 'input[type="number"]' ) ) {
			return;
		}

		window.clearTimeout( priceTimer );
		priceTimer = window.setTimeout( applyCurrentForm, 650 );
	} );

	document.addEventListener( 'click', function ( event ) {
		const clearLink = event.target.closest( '.tmi-filter-clear' );

		if ( clearLink ) {
			event.preventDefault();
			loadUrl( clearLink.href, true );
			return;
		}

		const paginationLink = event.target.closest( '#tmi-product-results .woocommerce-pagination a, .woocommerce-pagination a' );

		if ( paginationLink ) {
			event.preventDefault();
			loadUrl( paginationLink.href, true );
		}
	} );

	window.addEventListener( 'popstate', function () {
		loadUrl( window.location.href, false );
	} );

	enhanceFilterUi();
}() );
