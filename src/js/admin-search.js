( () => {
	const config = window.wpLoupeAdminSearch || {};
	const labels = config.labels || {};
	const apiPath = ( config.path || '/wp-loupe-admin/v1/search' ).replace(
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
		}

		init() {
			if ( this.forms.length === 0 ) {
				return;
			}

			this.installNonceMiddleware();
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
						__( 'Enter a search term.', 'wp-loupe-admin' )
				);
				return;
			}

			this.lockContainerHeight( results );
			this.renderMessage(
				results,
				labels.loading || __( 'Searching…', 'wp-loupe-admin' )
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
							'wp-loupe-admin'
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
						__( 'No matching content found.', 'wp-loupe-admin' )
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
				'wp-loupe-admin'
			) }`;
			wrapper.appendChild( summary );

			if ( ( response?.totalPages || 1 ) <= 1 ) {
				return wrapper;
			}

			const controls = document.createElement( 'div' );
			controls.className = 'wp-loupe-admin-search-pagination-controls';

			controls.appendChild(
				this.createPaginationButton(
					labels.previous || __( 'Previous', 'wp-loupe-admin' ),
					( response.page || 1 ) - 1,
					( response.page || 1 ) <= 1
				)
			);

			const indicator = document.createElement( 'span' );
			indicator.className = 'wp-loupe-admin-search-pagination-indicator';
			indicator.textContent = `${ labels.page || __( 'Page', 'wp-loupe-admin' ) } ${
				response.page || 1
			} ${ labels.of || __( 'of', 'wp-loupe-admin' ) } ${
				response.totalPages || 1
			}`;
			controls.appendChild( indicator );

			controls.appendChild(
				this.createPaginationButton(
					labels.next || __( 'Next', 'wp-loupe-admin' ),
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
						__( 'Edit', 'wp-loupe-admin' )
					)
				);
			}

			if ( hit.viewUrl ) {
				actions.appendChild(
					this.createActionLink(
						hit.viewUrl,
						__( 'View', 'wp-loupe-admin' ),
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