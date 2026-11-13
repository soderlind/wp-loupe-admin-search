( () => {
	const config = window.wpLoupeAdminSearch || {};
	const labels = config.labels || {};
	const apiPath = ( config.path || '/loupe-admin-search/v1/search' ).replace(
		/^(?!\/)/,
		'/'
	);
	const perPage = Number( config.perPage ) || 10;
	const __ = window.wp?.i18n?.__ || ( ( text ) => text );

	class WpLoupeAdminSearch {
		constructor() {
			this.modal = document.getElementById(
				'wp-loupe-admin-search-modal'
			);
			this.panel = this.modal?.querySelector(
				'.wp-loupe-admin-search-panel'
			);
			this.openers = document.querySelectorAll(
				'#wp-admin-bar-wp-loupe-admin-search .ab-item, a[href="#wp-loupe-admin-search"]'
			);
			this.forms = document.querySelectorAll(
				'.wp-loupe-admin-search-form'
			);
			this.lastActiveElement = null;
			this.searchState = new WeakMap();
			this.commandPaletteIcon = null;
		}

		init() {
			if ( this.forms.length === 0 ) {
				return;
			}

			this.installNonceMiddleware();
			this.registerCommandPaletteLoader();
			this.bindOpeners();
			this.bindModal();
			this.bindForms();
		}

		installNonceMiddleware() {
			if (
				window.wp &&
				wp.apiFetch &&
				wp.apiFetch.createNonceMiddleware &&
				config.nonce
			) {
				wp.apiFetch.use(
					wp.apiFetch.createNonceMiddleware( config.nonce )
				);
			}
		}

		registerCommandPaletteLoader() {
			const commandPalette = config.commandPalette || {};
			const commandStore = window.wp?.commands?.store;
			const dispatch = window.wp?.data?.dispatch;
			const element = window.wp?.element;

			if (
				! commandPalette.enabled ||
				! commandStore ||
				typeof dispatch !== 'function' ||
				typeof element?.createElement !== 'function' ||
				typeof element?.useState !== 'function' ||
				typeof element?.useEffect !== 'function' ||
				typeof window.wp?.apiFetch !== 'function'
			) {
				return;
			}

			this.commandPaletteIcon = this.createCommandPaletteIcon( element );

			const commandsDispatch = dispatch( commandStore );

			if ( typeof commandsDispatch?.registerCommandLoader !== 'function' ) {
				return;
			}

			commandsDispatch.registerCommandLoader( {
				name: 'wp-loupe-admin/search-results',
				hook: this.createCommandPaletteLoader( element ),
			} );
		}

		createCommandPaletteIcon( element ) {
			return element.createElement(
				'svg',
				{
					viewBox: '0 0 20 20',
					width: 20,
					height: 20,
					xmlns: 'http://www.w3.org/2000/svg',
					'aria-hidden': true,
					focusable: false,
				},
				element.createElement( 'circle', {
					cx: 10,
					cy: 10,
					r: 9,
					fill: '#6c63ff',
					fillOpacity: 0.12,
					stroke: '#6c63ff',
					strokeWidth: 1.5,
				} ),
				element.createElement( 'circle', {
					cx: 10,
					cy: 10,
					r: 5.5,
					fill: 'none',
					stroke: '#6c63ff',
					strokeWidth: 1,
					opacity: 0.5,
				} ),
				element.createElement( 'path', {
					d: 'M7,10.5 L9,12.5 L13,8',
					fill: 'none',
					stroke: '#6c63ff',
					strokeWidth: 1.75,
					strokeLinecap: 'round',
					strokeLinejoin: 'round',
				} )
			);
		}

		createCommandPaletteLoader( element ) {
			return ( loaderInput = {} ) => {
				const commandPalette = config.commandPalette || {};
				const availableScopes = Array.isArray( commandPalette.scopes )
					? commandPalette.scopes
					: [];
				const minQueryLength = Math.max(
					1,
					Number( commandPalette.minQueryLength ) || 2
				);
				const perScopeLimit = Math.max(
					1,
					Number( commandPalette.perScopeLimit ) || 3
				);
				const searchTerm = this.getCommandPaletteSearchTerm( loaderInput );
				const [ commands, setCommands ] = element.useState( [] );
				const [ isLoading, setIsLoading ] = element.useState( false );

				element.useEffect( () => {
					let isStale = false;

					if (
						searchTerm.length < minQueryLength ||
						availableScopes.length === 0
					) {
						setCommands( [] );
						setIsLoading( false );
						return () => {
							isStale = true;
						};
					}

					setIsLoading( true );

					Promise.all(
						availableScopes.map( ( scope ) => {
							return this.fetchCommandPaletteScope(
								scope,
								searchTerm,
								perScopeLimit
							).catch( () => [] );
						} )
					)
						.then( ( scopedCommands ) => {
							if ( isStale ) {
								return;
							}

							setCommands( scopedCommands.flat() );
						} )
						.finally( () => {
							if ( ! isStale ) {
								setIsLoading( false );
							}
						} );

					return () => {
						isStale = true;
					};
				}, [ searchTerm ] );

				return { commands, isLoading };
			};
		}

		getCommandPaletteSearchTerm( loaderInput ) {
			const search =
				typeof loaderInput === 'string'
					? loaderInput
					: loaderInput?.search || loaderInput?.searchTerm || '';

			return String( search ).trim();
		}

		async fetchCommandPaletteScope( scope, searchTerm, perScopeLimit ) {
			const scopeName = scope?.name;

			if ( ! scopeName ) {
				return [];
			}

			const response = await wp.apiFetch( {
				path: `${ apiPath }?q=${ encodeURIComponent(
					searchTerm
				) }&per_page=${ perScopeLimit }&page=1&scope=${ encodeURIComponent(
					scopeName
				) }`,
				method: 'GET',
			} );

			const hits = Array.isArray( response?.hits ) ? response.hits : [];

			return hits
				.map( ( hit, index ) => {
					return this.createCommandFromHit( hit, scope, index, searchTerm );
				} )
				.filter( Boolean );
		}

		createCommandFromHit( hit, scope, index, searchTerm = '' ) {
			const targetUrl = hit?.editUrl || hit?.viewUrl || '';

			if ( ! targetUrl ) {
				return null;
			}

			const title = String( hit.title || __( '(no title)', 'wp-loupe-admin-search' ) );
			const scopeLabel = String( scope?.label || scope?.name || '' );
			const keywords = [
				title,
				searchTerm,
				hit.postTypeLabel,
				hit.statusLabel,
				hit.authorName,
				hit.dateLabel,
				hit.excerpt,
				scopeLabel,
			]
				.filter( Boolean )
				.map( ( keyword ) => String( keyword ) );

			return {
				name: `wp-loupe-admin/${ scope?.name || 'result' }/${ this.getStableCommandId(
					hit.id,
					index
				) }`,
				label: title,
				icon: this.commandPaletteIcon,
				searchLabel: keywords.join( ' ' ),
				keywords,
				category: hit.editUrl ? 'edit' : 'view',
				callback: () => {
					this.closeCommandPalette();
					this.navigateToUrl( targetUrl );
				},
			};
		}

		navigateToUrl( targetUrl ) {
			const navigationEvent = new CustomEvent(
				'wp-loupe-admin-search:navigate',
				{
					cancelable: true,
					detail: {
						url: targetUrl,
					},
				}
			);

			if ( ! document.dispatchEvent( navigationEvent ) ) {
				return;
			}

			window.location.assign( targetUrl );
		}

		getStableCommandId( id, fallback ) {
			const stableId = String( id || fallback )
				.replace( /[^a-z0-9_-]+/gi, '-' )
				.replace( /^-+|-+$/g, '' );

			return stableId || String( fallback );
		}

		closeCommandPalette() {
			const commandStore = window.wp?.commands?.store;
			const dispatch = window.wp?.data?.dispatch;

			if ( ! commandStore || typeof dispatch !== 'function' ) {
				return;
			}

			const commandsDispatch = dispatch( commandStore );

			if ( typeof commandsDispatch?.close === 'function' ) {
				commandsDispatch.close();
				return;
			}

			if ( typeof commandsDispatch?.closeCommandPalette === 'function' ) {
				commandsDispatch.closeCommandPalette();
			}
		}

		bindOpeners() {
			this.openers.forEach( ( opener ) => {
				opener.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					this.openModal();
				} );
			} );
		}

		bindModal() {
			if ( ! this.modal ) {
				return;
			}

			this.modal.addEventListener( 'click', ( event ) => {
				if ( event.target.closest( '[data-wp-loupe-close="1"]' ) ) {
					this.closeModal();
				}
			} );

			document.addEventListener( 'keydown', ( event ) => {
				if ( this.modal.hidden ) {
					return;
				}

				if ( event.key === 'Escape' ) {
					this.closeModal();
					return;
				}

				if ( event.key === 'Tab' ) {
					this.trapFocus( event );
				}
			} );
		}

		bindForms() {
			this.forms.forEach( ( form ) => {
				this.bindSearchForm( form );
			} );
		}

		bindSearchForm( form ) {
			const shell = form.closest( '.wp-loupe-admin-search-shell' );

			if ( ! shell ) {
				return;
			}

			shell.addEventListener( 'click', ( event ) => {
				const pageButton = event.target.closest( '[data-wp-loupe-page]' );

				if ( ! pageButton ) {
					return;
				}

				event.preventDefault();
				void this.handleSubmit(
					form,
					Number( pageButton.dataset.wpLoupePage ) || 1
				);
			} );

			form.addEventListener( 'submit', async ( event ) => {
				event.preventDefault();
				await this.handleSubmit( form, 1 );
			} );
		}

		openModal() {
			if ( ! this.modal ) {
				return;
			}

			this.lastActiveElement = document.activeElement;
			this.modal.hidden = false;
			document.body.classList.add( 'wp-loupe-admin-search-open' );
			this.getInitialFocusTarget()?.focus();
		}

		async openScopedSearch( scope, query ) {
			const modalForm = this.modal?.querySelector(
				'.wp-loupe-admin-search-form[data-wp-loupe-search-form="modal"]'
			);

			if ( ! modalForm ) {
				return;
			}

			const scopeField = modalForm.querySelector( 'select[name="scope"]' );
			const queryField = modalForm.querySelector( 'input[name="q"]' );

			this.openModal();

			if ( scopeField ) {
				scopeField.value = scope;
			}

			if ( queryField ) {
				queryField.value = query;
			}

			await this.handleSubmit( modalForm, 1 );
		}

		closeModal() {
			if ( ! this.modal ) {
				return;
			}

			this.modal.hidden = true;
			document.body.classList.remove( 'wp-loupe-admin-search-open' );

			if ( this.lastActiveElement instanceof HTMLElement ) {
				this.lastActiveElement.focus();
			}
		}

		getInitialFocusTarget() {
			return (
				this.modal?.querySelector( 'input[type="search"]' ) ||
				this.panel
			);
		}

		getFocusableElements() {
			if ( ! this.modal || this.modal.hidden ) {
				return [];
			}

			return Array.from(
				this.modal.querySelectorAll(
					'a[href], button:not([disabled]), textarea:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
				)
			).filter( ( element ) => {
				return element instanceof HTMLElement && ! element.hidden;
			} );
		}

		trapFocus( event ) {
			const focusableElements = this.getFocusableElements();

			if ( focusableElements.length === 0 ) {
				event.preventDefault();
				this.panel?.focus();
				return;
			}

			const firstElement = focusableElements[ 0 ];
			const lastElement = focusableElements[ focusableElements.length - 1 ];

			if ( event.shiftKey && document.activeElement === firstElement ) {
				event.preventDefault();
				lastElement.focus();
				return;
			}

			if ( ! event.shiftKey && document.activeElement === lastElement ) {
				event.preventDefault();
				firstElement.focus();
			}
		}

		async handleSubmit( form, requestedPage = 1 ) {
			const input = form.querySelector( 'input[name="q"]' );
			const shell = form.closest( '.wp-loupe-admin-search-shell' );
			const results = shell?.querySelector(
				'.wp-loupe-admin-search-results'
			);

			if ( ! input || ! results || ! shell ) {
				return;
			}

			const previousState = this.searchState.get( shell ) || {};
			const query = input.value.trim() || previousState.query || '';
			const scope =
				form.querySelector( 'select[name="scope"]' )?.value ||
				previousState.scope ||
				'content';
			if ( query.length === 0 ) {
				this.releaseContainerHeight( results );
				this.renderMessage(
					results,
					labels.emptyQuery ||
						__( 'Enter a search term.', 'wp-loupe-admin-search' )
				);
				return;
			}

			this.lockContainerHeight( results );
			this.renderMessage(
				results,
				labels.loading || __( 'Searching…', 'wp-loupe-admin-search' )
			);

			try {
				const page = Math.max( 1, requestedPage );
				const response = await wp.apiFetch( {
					path: `${ apiPath }?q=${ encodeURIComponent(
						query
					) }&per_page=${ perPage }&page=${ page }&scope=${ encodeURIComponent(
						scope
					) }`,
					method: 'GET',
				} );
				this.searchState.set( shell, {
					query,
					page: response?.page || page,
					scope,
				} );
				this.renderResults( shell, results, response );
			} catch ( error ) {
				this.renderMessage(
					results,
					error.message ||
						labels.error ||
						__(
							'Search failed. Please try again.',
							'wp-loupe-admin-search'
						)
				);
				this.releaseContainerHeight( results );
			}
		}

		lockContainerHeight( container ) {
			if ( ! container ) {
				return;
			}

			if ( ! container.dataset.baseMinHeight ) {
				container.dataset.baseMinHeight =
					window.getComputedStyle( container ).minHeight;
			}

			if ( container.offsetHeight > 0 ) {
				container.style.minHeight = `${ container.offsetHeight }px`;
			}
		}

		releaseContainerHeight( container ) {
			if ( ! container ) {
				return;
			}

			container.style.minHeight = container.dataset.baseMinHeight || '';
		}

		renderMessage( container, message ) {
			container.innerHTML = '';
			const paragraph = document.createElement( 'p' );
			paragraph.className = 'wp-loupe-admin-search-message';
			paragraph.textContent = message;
			container.appendChild( paragraph );
		}

		renderResults( shell, container, response ) {
			container.innerHTML = '';
			const hits = Array.isArray( response?.hits ) ? response.hits : [];

			if ( hits.length === 0 ) {
				this.renderMessage(
					container,
					labels.noResults ||
						__( 'No matching content found.', 'wp-loupe-admin-search' )
				);
				this.releaseContainerHeight( container );
				return;
			}

			const list = document.createElement( 'ul' );
			list.className = 'wp-loupe-admin-search-result-list';

			hits.forEach( ( hit ) => {
				list.appendChild( this.createResultItem( hit ) );
			} );

			container.appendChild( list );

			container.appendChild( this.createPagination( shell, response ) );
			this.releaseContainerHeight( container );
		}

		createPagination( shell, response ) {
			const wrapper = document.createElement( 'div' );
			wrapper.className = 'wp-loupe-admin-search-pagination';

			const summary = document.createElement( 'p' );
			summary.className = 'description';
			summary.textContent = `${ response.total } ${ __(
				'total matches',
				'wp-loupe-admin-search'
			) }`;
			wrapper.appendChild( summary );

			if ( ( response?.totalPages || 1 ) <= 1 ) {
				return wrapper;
			}

			const controls = document.createElement( 'div' );
			controls.className = 'wp-loupe-admin-search-pagination-controls';

			controls.appendChild(
				this.createPaginationButton(
					labels.previous || __( 'Previous', 'wp-loupe-admin-search' ),
					( response.page || 1 ) - 1,
					( response.page || 1 ) <= 1
				)
			);

			const indicator = document.createElement( 'span' );
			indicator.className = 'wp-loupe-admin-search-pagination-indicator';
			indicator.textContent = `${ labels.page || __( 'Page', 'wp-loupe-admin-search' ) } ${
				response.page || 1
			} ${ labels.of || __( 'of', 'wp-loupe-admin-search' ) } ${
				response.totalPages || 1
			}`;
			controls.appendChild( indicator );

			controls.appendChild(
				this.createPaginationButton(
					labels.next || __( 'Next', 'wp-loupe-admin-search' ),
					( response.page || 1 ) + 1,
					( response.page || 1 ) >= ( response.totalPages || 1 )
				)
			);

			wrapper.appendChild( controls );

			this.searchState.set( shell, {
				query: response.query || '',
				page: response.page || 1,
				scope: response.scope || 'content',
			} );

			return wrapper;
		}

		createPaginationButton( label, page, disabled ) {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button';
			button.textContent = label;
			button.disabled = disabled;
			button.dataset.wpLoupePage = String( page );

			return button;
		}

		createResultItem( hit ) {
			const item = document.createElement( 'li' );
			item.className = 'wp-loupe-admin-search-result';

			const title = document.createElement( 'a' );
			title.className = 'wp-loupe-admin-search-title';
			title.href = hit.editUrl || hit.viewUrl || '#';
			title.textContent = hit.title;

			const meta = document.createElement( 'div' );
			meta.className = 'wp-loupe-admin-search-meta';
			meta.textContent = [
				hit.postTypeLabel,
				hit.statusLabel,
				hit.authorName,
				hit.dateLabel,
			]
				.filter( Boolean )
				.join( ' - ' );

			const excerpt = this.createExcerpt( hit.excerpt );

			const actions = document.createElement( 'div' );
			actions.className = 'wp-loupe-admin-search-actions';

			if ( hit.editUrl ) {
				actions.appendChild(
					this.createActionLink(
						hit.editUrl,
						__( 'Edit', 'wp-loupe-admin-search' )
					)
				);
			}

			if ( hit.viewUrl ) {
				actions.appendChild(
					this.createActionLink(
						hit.viewUrl,
						__( 'View', 'wp-loupe-admin-search' ),
						true
					)
				);
			}

			item.appendChild( title );
			item.appendChild( meta );
			if ( excerpt ) {
				item.appendChild( excerpt );
			}
			item.appendChild( actions );

			return item;
		}

		createExcerpt( excerpt ) {
			if ( ! excerpt ) {
				return null;
			}

			const paragraph = document.createElement( 'p' );
			paragraph.className = 'wp-loupe-admin-search-excerpt';
			paragraph.textContent = excerpt;

			return paragraph;
		}

		createActionLink( href, label, newTab = false ) {
			const link = document.createElement( 'a' );
			link.href = href;
			link.textContent = label;
			if ( newTab ) {
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
			}
			return link;
		}
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		new WpLoupeAdminSearch().init();
	} );
} )();