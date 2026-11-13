import { beforeEach, describe, expect, it, vi } from 'vitest';

const createApiFetch = () => {
	const apiFetch = vi.fn();
	apiFetch.use = vi.fn();
	apiFetch.createNonceMiddleware = vi.fn( () => vi.fn() );
	return apiFetch;
};

const buildMarkup = () => `
	<div id="wp-admin-bar-wp-loupe-admin-search"><a class="ab-item" href="#wp-loupe-admin-search">Open</a></div>
	<div id="wp-loupe-admin-search-modal" hidden>
		<div class="wp-loupe-admin-search-panel" tabindex="-1">
			<div class="wp-loupe-admin-search-shell" data-wp-loupe-search-shell="modal">
				<form class="wp-loupe-admin-search-form" data-wp-loupe-search-form="modal">
					<div class="wp-loupe-admin-search-row">
						<select name="scope">
							<option value="content">Content</option>
							<option value="users">Users</option>
							<option value="plugins">Plugins</option>
						</select>
						<input type="search" name="q" value="" />
						<button type="submit">Search</button>
					</div>
				</form>
				<div class="wp-loupe-admin-search-results"></div>
			</div>
		</div>
	</div>
	<div class="wp-loupe-admin-search-shell" data-wp-loupe-search-shell="widget">
		<form class="wp-loupe-admin-search-form" data-wp-loupe-search-form="widget">
			<div class="wp-loupe-admin-search-row">
				<select name="scope">
					<option value="content">Content</option>
					<option value="users">Users</option>
					<option value="plugins">Plugins</option>
				</select>
				<input type="search" name="q" value="" />
				<button type="submit">Search</button>
			</div>
		</form>
		<div class="wp-loupe-admin-search-results"></div>
	</div>
`;

const enableCommandPalette = () => {
	window.wpLoupeAdminSearch.commandPalette = {
		enabled: true,
		minQueryLength: 2,
		perScopeLimit: 3,
		scopes: [
			{
				name: 'content',
				label: 'Content',
			},
		],
		labels: {
			category: 'Loupe',
		},
	};
};

const installCommandPaletteStubs = () => {
	const registeredLoaders = [];
	const commandDispatch = {
		registerCommandLoader: vi.fn( ( loader ) => {
			registeredLoaders.push( loader );
		} ),
		close: vi.fn(),
	};
	const setters = [];

	window.wp.commands = {
		store: { name: 'core/commands' },
	};
	window.wp.data = {
		dispatch: vi.fn( () => commandDispatch ),
	};
	window.wp.element = {
		createElement: vi.fn( ( type, props, ...children ) => ( {
			type,
			props,
			children,
		} ) ),
		useState: vi.fn( ( initialValue ) => {
			const setter = vi.fn();
			setters.push( setter );
			return [ initialValue, setter ];
		} ),
		useEffect: vi.fn( ( effect ) => effect() ),
	};

	return { registeredLoaders, commandDispatch, setters };
};

const flushPromises = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

describe( 'admin search client', () => {
	beforeEach( () => {
		vi.resetModules();
		document.body.innerHTML = buildMarkup();

		const apiFetch = createApiFetch();
		apiFetch.mockResolvedValue( {
			hits: [
				{
					title: 'Result',
					editUrl: 'https://plugins.local/wp-admin/post.php?post=1&action=edit',
					viewUrl: '',
					postTypeLabel: 'Post',
					statusLabel: 'Published',
					authorName: 'Per',
					dateLabel: '2026-03-21',
					excerpt: 'Context excerpt for the current result.',
				},
			],
			total: 1,
			query: 'hello',
			page: 1,
			totalPages: 1,
			scope: 'content',
		} );

		window.wpLoupeAdminSearch = {
				path: '/loupe-admin-search/v1/search',
			nonce: 'test-nonce',
			labels: {},
			perPage: 10,
		};

		window.wp = {
			apiFetch,
			i18n: {
				__: ( text ) => text,
			},
		};

		globalThis.wp = window.wp;
	} );

	it( 'submits the selected scope in a regular search form', async () => {
		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		const form = document.querySelector(
			'.wp-loupe-admin-search-form[data-wp-loupe-search-form="widget"]'
		);
		form.querySelector( 'select[name="scope"]' ).value = 'users';
		form.querySelector( 'input[name="q"]' ).value = 'admin';

		form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );

		await Promise.resolve();

		expect( window.wp.apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: expect.stringContaining( 'scope=users' ),
			} )
		);
	} );

	it( 'opens modal search from the admin bar and renders content results', async () => {
		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		document.querySelector( '#wp-admin-bar-wp-loupe-admin-search .ab-item' ).click();
		const modalForm = document.querySelector(
			'.wp-loupe-admin-search-form[data-wp-loupe-search-form="modal"]'
		);
		modalForm.querySelector( 'input[name="q"]' ).value = 'hello';
		modalForm.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );

		await Promise.resolve();

		expect( document.getElementById( 'wp-loupe-admin-search-modal' ).hidden ).toBe( false );
		expect( window.wp.apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: expect.stringContaining( 'scope=content' ),
			} )
		);
		expect(
			document.querySelector( '.wp-loupe-admin-search-meta' ).textContent
		).toContain( 'Per' );
		expect(
			document.querySelector( '.wp-loupe-admin-search-excerpt' ).textContent
		).toBe( 'Context excerpt for the current result.' );
	} );

	it( 'does not intercept native admin search forms anymore', async () => {
		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		const nativeForm = document.createElement( 'form' );
		nativeForm.innerHTML = '<input id="post-search-input" value="hello" /><input type="submit" value="Search" />';
		document.body.appendChild( nativeForm );

		const submitEvent = new Event( 'submit', {
			bubbles: true,
			cancelable: true,
		} );
		nativeForm.dispatchEvent( submitEvent );
		await Promise.resolve();

		expect( submitEvent.defaultPrevented ).toBe( false );
		expect( window.wp.apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'registers a Command Palette loader when commands are available', async () => {
		enableCommandPalette();
		const { registeredLoaders } = installCommandPaletteStubs();

		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		expect( registeredLoaders ).toHaveLength( 1 );
		expect( registeredLoaders[ 0 ] ).toEqual(
			expect.objectContaining( {
				name: 'wp-loupe-admin/search-results',
				hook: expect.any( Function ),
			} )
		);
	} );

	it( 'does not register a Command Palette loader when commands are missing', async () => {
		enableCommandPalette();

		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		expect( window.wp.apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'ignores short Command Palette search terms', async () => {
		enableCommandPalette();
		const { registeredLoaders, setters } = installCommandPaletteStubs();

		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		registeredLoaders[ 0 ].hook( { search: 'a' } );

		expect( window.wp.apiFetch ).not.toHaveBeenCalled();
		expect( setters[ 0 ] ).toHaveBeenCalledWith( [] );
		expect( setters[ 1 ] ).toHaveBeenCalledWith( false );
	} );

	it( 'queries allowed scopes and maps hits into Command Palette commands', async () => {
		enableCommandPalette();
		const { registeredLoaders, setters } = installCommandPaletteStubs();

		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		registeredLoaders[ 0 ].hook( { search: 'hello' } );
		await flushPromises();

		expect( window.wp.apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: expect.stringContaining( 'q=hello&per_page=3&page=1&scope=content' ),
			} )
		);
		expect( setters[ 0 ] ).toHaveBeenCalledWith( [
			expect.objectContaining( {
				name: 'wp-loupe-admin/content/0',
				label: 'Result',
				icon: expect.objectContaining( {
					type: 'svg',
					props: expect.objectContaining( {
						viewBox: '0 0 20 20',
						width: 20,
						height: 20,
						xmlns: 'http://www.w3.org/2000/svg',
						'aria-hidden': true,
					} ),
					children: expect.arrayContaining( [
						expect.objectContaining( {
							type: 'circle',
							props: expect.objectContaining( {
								cx: 10,
								cy: 10,
								r: 9,
								fill: '#6c63ff',
								fillOpacity: 0.12,
							} ),
						} ),
						expect.objectContaining( {
							type: 'path',
							props: expect.objectContaining( {
								d: 'M7,10.5 L9,12.5 L13,8',
								stroke: '#6c63ff',
							} ),
						} ),
					] ),
				} ),
				searchLabel: expect.stringContaining( 'hello' ),
				keywords: expect.arrayContaining( [
					'Result',
					'hello',
					'Post',
					'Published',
					'Per',
					'2026-03-21',
					'Context excerpt for the current result.',
					'Content',
				] ),
				category: 'edit',
				callback: expect.any( Function ),
			} ),
		] );
		expect( setters[ 1 ] ).toHaveBeenLastCalledWith( false );
	} );

	it( 'closes the Command Palette and navigates to a selected result', async () => {
		enableCommandPalette();
		const { registeredLoaders, commandDispatch, setters } = installCommandPaletteStubs();
		const navigationListener = vi.fn( ( event ) => {
			event.preventDefault();
		} );
		document.addEventListener( 'wp-loupe-admin-search:navigate', navigationListener );

		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		registeredLoaders[ 0 ].hook( { search: 'hello' } );
		await flushPromises();

		const commands = setters[ 0 ].mock.calls.at( -1 )[ 0 ];
		commands[ 0 ].callback();

		expect( commandDispatch.close ).toHaveBeenCalled();
		expect( navigationListener ).toHaveBeenCalledWith(
			expect.objectContaining( {
				detail: {
					url: 'https://plugins.local/wp-admin/post.php?post=1&action=edit',
				},
			} )
		);

		document.removeEventListener( 'wp-loupe-admin-search:navigate', navigationListener );
	} );
} );
