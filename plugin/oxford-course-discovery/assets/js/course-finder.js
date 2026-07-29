/**
 * Course finder — progressive enhancement.
 *
 * The finder is fully functional before this file loads: the form is a plain
 * GET form and every control is a native input. This module only removes page
 * reloads and adds the type-ahead behaviour expected of a combobox.
 *
 * Deliberately dependency free and framework free — one module, no build step,
 * and nothing that has to be kept in step with a bundler.
 */

const settings = window.oxcdSettings || {};
const strings = settings.strings || {};

/**
 * Debounce, so typing in the keyword box does not fire a request per keystroke.
 *
 * @param {Function} fn    Callback.
 * @param {number}   delay Milliseconds.
 * @return {Function} Debounced callback.
 */
const debounce = ( fn, delay ) => {
	let timer;

	return ( ...args ) => {
		window.clearTimeout( timer );
		timer = window.setTimeout( () => fn( ...args ), delay );
	};
};

/**
 * Enhance one <details> based multi-select into a filtering combobox.
 *
 * @param {HTMLElement} root Combobox wrapper.
 */
const enhanceCombobox = ( root ) => {
	const details = root.querySelector( 'details' );
	const search = root.querySelector( '[data-oxcd-combobox-search]' );
	const list = root.querySelector( '[data-oxcd-combobox-list]' );
	const summary = root.querySelector( '[data-oxcd-combobox-summary]' );
	const empty = root.querySelector( '[data-oxcd-combobox-empty]' );

	if ( ! details || ! list ) {
		return;
	}

	const options = Array.from( list.querySelectorAll( '[data-oxcd-option]' ) );
	const checkboxes = options.map( ( option ) => option.querySelector( 'input[type="checkbox"]' ) );

	// The search box is hidden in the markup so that it never appears without
	// the behaviour that makes it work.
	if ( search ) {
		search.hidden = false;
	}

	const updateSummary = () => {
		if ( ! summary ) {
			return;
		}

		const selected = checkboxes.filter( ( input ) => input && input.checked );

		if ( selected.length === 0 ) {
			summary.textContent = strings.noneChosen || 'Any';

			return;
		}

		summary.textContent = selected
			.map( ( input ) => {
				const label = input.closest( 'li' )?.querySelector( 'label' );

				return label ? label.textContent.trim().replace( /\s*\(\d+\)$/, '' ) : input.value;
			} )
			.join( ', ' );
	};

	const applyQuery = ( query ) => {
		const needle = query.trim().toLowerCase();
		let visible = 0;

		options.forEach( ( option ) => {
			const label = option.getAttribute( 'data-label' ) || '';
			const matches = needle === '' || label.includes( needle );

			option.hidden = ! matches;

			if ( matches ) {
				visible += 1;
			}
		} );

		if ( empty ) {
			empty.hidden = visible !== 0;
		}
	};

	search?.addEventListener( 'input', ( event ) => applyQuery( event.target.value ) );

	// Roving keyboard navigation across the visible options.
	root.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && details.open ) {
			details.open = false;
			details.querySelector( 'summary' )?.focus();

			return;
		}

		if ( event.key !== 'ArrowDown' && event.key !== 'ArrowUp' ) {
			return;
		}

		const focusable = options
			.filter( ( option ) => ! option.hidden )
			.map( ( option ) => option.querySelector( 'input[type="checkbox"]' ) )
			.filter( Boolean );

		if ( focusable.length === 0 ) {
			return;
		}

		const currentIndex = focusable.indexOf( document.activeElement );
		const delta = event.key === 'ArrowDown' ? 1 : -1;
		const nextIndex =
			currentIndex === -1
				? 0
				: ( currentIndex + delta + focusable.length ) % focusable.length;

		event.preventDefault();
		focusable[ nextIndex ].focus();
	} );

	// Clicking away closes the panel, matching what a dropdown is expected to do.
	document.addEventListener( 'click', ( event ) => {
		if ( details.open && ! root.contains( event.target ) ) {
			details.open = false;
		}
	} );

	list.addEventListener( 'change', updateSummary );
	updateSummary();
};

/**
 * Wire one finder instance to the REST endpoint.
 *
 * @param {HTMLElement} finder Finder root element.
 */
const enhanceFinder = ( finder ) => {
	const form = finder.querySelector( '[data-oxcd-form]' );
	const results = finder.querySelector( '[data-oxcd-results]' );

	finder.querySelectorAll( '[data-oxcd-combobox]' ).forEach( enhanceCombobox );

	if ( ! form || ! results || ! settings.endpoint ) {
		return;
	}

	let inFlight = null;

	const request = async ( query, { push = true } = {} ) => {
		inFlight?.abort();
		inFlight = new AbortController();

		results.setAttribute( 'aria-busy', 'true' );

		try {
			const response = await fetch( `${ settings.endpoint }?${ query }`, {
				signal: inFlight.signal,
				headers: { 'X-WP-Nonce': settings.nonce || '' },
			} );

			if ( ! response.ok ) {
				throw new Error( `Request failed: ${ response.status }` );
			}

			const payload = await response.json();

			results.innerHTML = payload.html || '';

			// Move focus to the result summary so keyboard and screen reader
			// users land on the updated content instead of at the top of the
			// document.
			results.querySelector( '[data-oxcd-summary]' )?.focus();

			if ( push ) {
				const url = `${ window.location.pathname }?${ query }`;
				window.history.pushState( { oxcd: query }, '', url );
			}
		} catch ( error ) {
			if ( error.name === 'AbortError' ) {
				return;
			}

			// Leave the previous results in place and tell the user, rather
			// than blanking the region.
			const summary = results.querySelector( '[data-oxcd-summary]' );

			if ( summary ) {
				summary.textContent = strings.error || 'Something went wrong.';
			}
		} finally {
			results.setAttribute( 'aria-busy', 'false' );
			inFlight = null;
		}
	};

	const currentQuery = ( overrides = {} ) => {
		const params = new URLSearchParams();

		// Empty values are dropped rather than sent, so a shared URL contains
		// only the filters the user actually chose.
		new FormData( form ).forEach( ( value, key ) => {
			if ( String( value ).trim() !== '' ) {
				params.append( key, value );
			}
		} );

		Object.entries( overrides ).forEach( ( [ key, value ] ) => {
			params.delete( key );

			if ( value !== null && value !== undefined && value !== '' ) {
				params.set( key, String( value ) );
			}
		} );

		return params.toString();
	};

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		request( currentQuery( { paged: 1 } ) );
	} );

	const submitSoon = debounce( () => request( currentQuery( { paged: 1 } ) ), 350 );

	form.addEventListener( 'input', ( event ) => {
		if ( event.target.matches( '[data-oxcd-combobox-search]' ) ) {
			return; // Client side narrowing of the option list only.
		}

		if ( event.target.type === 'search' || event.target.type === 'text' ) {
			submitSoon();
		}
	} );

	form.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( 'input[type="checkbox"], select' ) ) {
			request( currentQuery( { paged: 1 } ) );
		}
	} );

	// Pagination links are inside the region we replace, so delegate.
	results.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( 'a[href^="?"]' );

		if ( ! link ) {
			return;
		}

		event.preventDefault();
		request( link.getAttribute( 'href' ).slice( 1 ) );
		finder.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	} );

	finder.querySelector( '[data-oxcd-reset]' )?.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		form.reset();
		form.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( input ) => {
			input.checked = false;
		} );
		request( '' );
	} );

	window.addEventListener( 'popstate', () => {
		request( window.location.search.replace( /^\?/, '' ), { push: false } );
	} );
};

document.querySelectorAll( '[data-oxcd-finder]' ).forEach( enhanceFinder );
