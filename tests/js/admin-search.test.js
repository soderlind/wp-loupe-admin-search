import { beforeEach, describe, expect, it, vi } from 'vitest';

const createApiFetch = () => {
	const apiFetch = vi.fn();
	apiFetch.use = vi.fn();
	apiFetch.createNonceMiddleware = vi.fn( () => vi.fn() );
	return apiFetch;
};

const buildMarkup = () => `
	<div id="wp-admin-bar-wp-loupe-admin-search"><a class="ab-item" href="#wp-loupe-admin-search">Open</a></div>
	<form id="native-plugin-search"><input id="plugin-search-input" value="akismet" /></form>
	<form id="native-user-search"><input id="user-search-input" value="admin" /></form>
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
					postTypeLabel: 'Plugin',
					statusLabel: 'Active',
				},
			],
			total: 1,
			query: 'akismet',
			page: 1,
			totalPages: 1,
			scope: 'plugins',
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

	it( 'intercepts native plugin search and opens the modal search with plugin scope', async () => {
		await import( '../../src/js/admin-search.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		const pluginForm = document.getElementById( 'native-plugin-search' );
		pluginForm.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );

		await Promise.resolve();

		expect( document.getElementById( 'wp-loupe-admin-search-modal' ).hidden ).toBe( false );
		expect(
			document.querySelector(
				'#wp-loupe-admin-search-modal select[name="scope"]'
			).value
		).toBe( 'plugins' );
		expect( window.wp.apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: expect.stringContaining( 'scope=plugins' ),
			} )
		);
	} );
} );