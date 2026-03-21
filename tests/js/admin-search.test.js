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
			path: '/wp-loupe-admin/v1/search',
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
} );