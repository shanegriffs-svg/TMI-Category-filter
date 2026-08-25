( function () {
	'use strict';

	const formSelector           = '.tmi-category-filter';
	const resultsWrapperSelector = '#tmi-product-results';
	const productSelector        = 'ul.products';
	const auxiliarySelectors     = [
		'.woocommerce-result-count',
		'.woocommerce-ordering',
		'.woocommerce-pagination'
	];
	const minimumLoadingMs       = 320;

	let requestController = null;

	function getForm() {
		return document.querySelector( formSelector );
	}

	function getLoadingRoot() {
		return document.querySelector( resultsWrapperSelector ) || document.querySelector( productSelector );
	}

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function wait( milliseconds ) {
		return new Promise( function ( resolve ) {
			window.setTimeout( resolve, milliseconds );
		} );
	}

	function formatCurrency( value ) {
		const amount = Number.isFinite( value ) ? Math.round( value ) : 0;
		return '$' + amount.toLocaleString( 'en-AU' );
	}

	function syncPriceSlider( slider, changedInput ) {
		if ( ! slider ) {
			return;
		}

		const minInput = slider.querySelector( '.tmi-price-range-min' );
		const maxInput = slider.querySelector( '.tmi-price-range-max' );
		const minOutput = slider.querySelector( '.tmi-price-output-min' );
		const maxOutput = slider.querySelector( '.tmi-price-output-max' );

		if ( ! minInput || ! maxInput ) {
			return;
		}

		const lowerLimit = Number( minInput.min ) || 0;
		const upperLimit = Number( minInput.max ) || 50000;
		let minValue     = Number( minInput.value );
		let maxValue     = Number( maxInput.value );

		if ( ! Number.isFinite( minValue ) ) {
			minValue = lowerLimit;
		}

		if ( ! Number.isFinite( maxValue ) ) {
			maxValue = upperLimit;
		}

		if ( minValue > maxValue ) {
			if ( changedInput === maxInput ) {
				maxValue = minValue;
				maxInput.value = String( maxValue );
			} else {
				minValue = maxValue;
				minInput.value = String( minValue );
			}
		}

		const range = Math.max( 1, upperLimit - lowerLimit );
		const minPosition = ( ( minValue - lowerLimit ) / range ) * 100;
		const maxPosition = ( ( maxValue - lowerLimit ) / range ) * 100;

		slider.style.setProperty( '--tmi-price-min-pos', minPosition + '%' );
		slider.style.setProperty( '--tmi-price-max-pos', maxPosition + '%' );

		if ( minOutput ) {
			minOutput.textContent = formatCurrency( minValue );
		}

		if ( maxOutput ) {
			maxOutput.textContent = formatCurrency( maxValue );
		}

		minInput.style.zIndex = minValue > upperLimit - ( range * 0.1 ) ? '5' : '3';
		maxInput.style.zIndex = '4';
	}

	function initializePriceSliders( root ) {
		const scope = root || document;

		scope.querySelectorAll( '.tmi-price-slider' ).forEach( function ( slider ) {
			syncPriceSlider( slider, null );
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

	function setLoading( loading ) {
		const form    = getForm();
		const results = getLoadingRoot();

		if ( form ) {
			form.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
			form.style.pointerEvents = loading ? 'none' : '';
		}

		if ( ! results ) {
			return;
		}

		results.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		results.style.transition    = prefersReducedMotion() ? 'none' : 'opacity 220ms ease';
		results.style.opacity       = loading ? '0.38' : '1';
		results.style.pointerEvents = loading ? 'none' : '';
	}

	function replaceElement( parsedDocument, selector ) {
		const current = document.querySelector( selector );
		const next    = parsedDocument.querySelector( selector );

		if ( current && next ) {
			current.replaceWith( next );
			return true;
		}

		if ( current && ! next ) {
			current.remove();
		}

		return false;
	}

	function replaceProducts( parsedDocument ) {
		const currentProducts = document.querySelector( productSelector );
		const nextProducts    = parsedDocument.querySelector( productSelector );

		if ( ! currentProducts || ! nextProducts ) {
			return false;
		}

		currentProducts.replaceWith( nextProducts );

		auxiliarySelectors.forEach( function ( selector ) {
			replaceElement( parsedDocument, selector );
		} );

		return true;
	}

	function replaceFilter( parsedDocument ) {
		const currentForm = getForm();
		const nextForm    = parsedDocument.querySelector( formSelector );

		if ( currentForm && nextForm ) {
			currentForm.replaceWith( nextForm );
			initializePriceSliders( nextForm );
		}
	}

	function refreshFrontendFeatures() {
		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'updated_wc_div' );
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
		const startedAt   = window.performance && typeof window.performance.now === 'function'
			? window.performance.now()
			: Date.now();

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
			const now            = window.performance && typeof window.performance.now === 'function'
				? window.performance.now()
				: Date.now();
			const remainingDelay = Math.max( 0, minimumLoadingMs - ( now - startedAt ) );

			if ( remainingDelay > 0 && ! prefersReducedMotion() ) {
				await wait( remainingDelay );
			}

			if ( ! replaceProducts( parsedDocument ) ) {
				window.location.assign( url );
				return;
			}

			replaceFilter( parsedDocument );

			if ( addToHistory ) {
				window.history.pushState( { tmiCategoryFilter: true }, '', url );
			}

			setLoading( false );
			refreshFrontendFeatures();
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

		if ( form ) {
			loadUrl( buildUrl( form ), true );
		}
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

		if ( field.matches( 'input[type="range"]' ) ) {
			syncPriceSlider( field.closest( '.tmi-price-slider' ), field );
			applyCurrentForm();
			return;
		}

		if ( field.matches( 'input[type="checkbox"], input[type="radio"], select' ) ) {
			applyCurrentForm();
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		const field = event.target;
		const form  = field.closest( formSelector );

		if ( ! form || ! field.matches( 'input[type="range"]' ) ) {
			return;
		}

		syncPriceSlider( field.closest( '.tmi-price-slider' ), field );
	} );

	document.addEventListener( 'click', function ( event ) {
		const clearLink = event.target.closest( '.tmi-filter-clear' );

		if ( clearLink ) {
			event.preventDefault();
			loadUrl( clearLink.href, true );
			return;
		}

		const paginationLink = event.target.closest( '.woocommerce-pagination a' );

		if ( paginationLink ) {
			event.preventDefault();
			loadUrl( paginationLink.href, true );
		}
	} );

	window.addEventListener( 'popstate', function () {
		loadUrl( window.location.href, false );
	} );

	initializePriceSliders( document );
}() );
