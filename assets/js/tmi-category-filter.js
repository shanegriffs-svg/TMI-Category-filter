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

	function getResultsRoot() {
		return document.querySelector( resultsSelector ) || document.querySelector( 'ul.products' );
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

		element.style.transition    = prefersReducedMotion() ? 'none' : 'opacity 180ms ease';
		element.style.opacity       = loading ? '0.45' : '1';
		element.style.pointerEvents = loading ? 'none' : '';
		element.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
	}

	function setLoading( loading ) {
		const form    = getForm();
		const results = getResultsRoot();

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

		element.style.transition    = prefersReducedMotion() ? 'none' : 'opacity 180ms ease';
		element.style.opacity       = '0.45';
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

	function initializeOceanQuickViewContent( content ) {
		if ( window.oceanwpWooCustomFeatures &&
			window.oceanwpWooCustomFeatures.quantityButtons &&
			typeof window.oceanwpWooCustomFeatures.quantityButtons.start === 'function' ) {
			window.oceanwpWooCustomFeatures.quantityButtons.start();
		}

		if ( ! window.jQuery || ! content ) {
			return;
		}

		const $ = window.jQuery;
		const variationForm = content.querySelector( '.variations_form' );

		if ( variationForm ) {
			const $variationForm = $( variationForm );
			$variationForm.trigger( 'check_variations' );
			$variationForm.trigger( 'reset_image' );

			if ( typeof $variationForm.wc_variation_form === 'function' ) {
				$variationForm.wc_variation_form();
				$variationForm.find( 'select' ).trigger( 'change' );
			}
		}

		const gallery = content.querySelector( '.owp-qv-image' );

		if ( gallery && gallery.querySelectorAll( 'li' ).length && typeof $( gallery ).flexslider === 'function' ) {
			$( gallery ).flexslider();
		}
	}

	async function openOceanQuickViewFallback( button ) {
		const settings  = window.oceanwpLocalize;
		const productId = button.getAttribute( 'data-product_id' );
		const modal     = document.querySelector( '#owp-qv-wrap' );
		const content   = document.querySelector( '#owp-qv-content' );
		const parent    = button.parentElement;

		if ( ! settings || ! settings.ajax_url || ! productId || ! modal || ! content ) {
			return;
		}

		if ( parent ) {
			parent.classList.add( 'loading' );
		}

		const body = new URLSearchParams();
		body.append( 'action', 'oceanwp_product_quick_view' );
		body.append( 'product_id', productId );

		if ( settings.nonce ) {
			body.append( 'nonce', settings.nonce );
		}

		try {
			const response = await window.fetch( settings.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: body.toString()
			} );

			if ( ! response.ok ) {
				throw new Error( 'OceanWP Quick View request failed.' );
			}

			const payload = await response.json();
			const output  = payload && payload.data && payload.data.output ? payload.data.output : payload.output;

			if ( ! output ) {
				throw new Error( 'OceanWP Quick View returned no content.' );
			}

			document.documentElement.style.overflow = 'hidden';
			document.body.classList.add( 'owp-qv-open' );
			content.innerHTML = output;
			modal.style.display = 'block';
			modal.style.opacity = '1';
			modal.classList.add( 'is-visible' );

			initializeOceanQuickViewContent( content );
		} catch ( error ) {
			if ( window.console && typeof window.console.warn === 'function' ) {
				window.console.warn( 'TMI Category Filter: OceanWP Quick View fallback failed.', error );
			}
		} finally {
			if ( parent ) {
				parent.classList.remove( 'loading' );
			}
		}
	}

	function prepareOceanQuickViewFallback() {
		const results = getResultsRoot();

		if ( ! results ) {
			return;
		}

		results.querySelectorAll( '.owp-quick-view:not([data-tmi-qv-bound])' ).forEach( function ( button ) {
			button.setAttribute( 'data-tmi-qv-bound', '1' );

			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				window.setTimeout( function () {
					const parent = button.parentElement;
					const nativeOceanHandled =
						( parent && parent.classList.contains( 'loading' ) ) ||
						document.body.classList.contains( 'owp-qv-open' );

					if ( ! nativeOceanHandled ) {
						openOceanQuickViewFallback( button );
					}
				}, 0 );
			} );
		} );
	}

	function reinitializeFrontendFeatures() {
		const results = getResultsRoot();

		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'updated_wc_div' );
		}

		if ( window.elementorFrontend &&
			window.elementorFrontend.elementsHandler &&
			typeof window.elementorFrontend.elementsHandler.runReadyTrigger === 'function' &&
			results ) {
			window.elementorFrontend.elementsHandler.runReadyTrigger( results );
		}

		prepareOceanQuickViewFallback();
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
			reinitializeFrontendFeatures();

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
