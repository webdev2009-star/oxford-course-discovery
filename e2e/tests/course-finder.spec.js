import { expect, test } from '@playwright/test';

const summary = ( page ) => page.locator( '[data-oxcd-summary]' );

const totalFromSummary = async ( page ) => {
	const text = await summary( page ).textContent();
	const match = text.match( /of (\d+) courses?/ );

	return match ? Number( match[ 1 ] ) : 0;
};

test.describe( 'Course finder', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/' );
		await expect( page.locator( '[data-oxcd-finder]' ) ).toBeVisible();
	} );

	test( 'lists courses on load', async ( { page } ) => {
		await expect( summary( page ) ).toContainText( /Showing \d+/ );
		expect( await page.locator( '.oxcd-card' ).count() ).toBeGreaterThan( 0 );
	} );

	test( 'narrows results with a keyword and updates the URL', async ( { page } ) => {
		const before = await totalFromSummary( page );

		await page.getByRole( 'searchbox', { name: /search courses/i } ).fill( 'design' );
		await expect.poll( () => totalFromSummary( page ) ).toBeLessThan( before );

		await expect( page ).toHaveURL( /q=design/ );
	} );

	test( 'a shared URL reproduces the same search', async ( { page } ) => {
		await page.goto( '/?q=design' );

		const fromUrl = await totalFromSummary( page );

		await page.goto( '/' );
		await page.getByRole( 'searchbox', { name: /search courses/i } ).fill( 'design' );
		await expect.poll( () => totalFromSummary( page ) ).toBe( fromUrl );
	} );

	test( 'selecting two providers unions their results (OR)', async ( { page } ) => {
		const providers = page.getByRole( 'group', { name: 'Providers' } ).getByRole( 'checkbox' );

		await providers.nth( 0 ).check();
		const first = await totalFromSummary( page );

		await providers.nth( 0 ).uncheck();
		await providers.nth( 1 ).check();
		const second = await totalFromSummary( page );

		await providers.nth( 0 ).check();
		const union = await totalFromSummary( page );

		// |A ∪ B| is never smaller than either side and never larger than their
		// sum — true whatever the fixture data happens to overlap on.
		expect( union ).toBeGreaterThanOrEqual( Math.max( first, second ) );
		expect( union ).toBeLessThanOrEqual( first + second );
	} );

	test( 'adding a second filter narrows the result set (AND)', async ( { page } ) => {
		await page.getByRole( 'group', { name: 'Providers' } ).getByRole( 'checkbox' ).first().check();
		const providerOnly = await totalFromSummary( page );

		await page.getByRole( 'group', { name: 'Categories' } ).getByRole( 'checkbox' ).first().check();
		const both = await totalFromSummary( page );

		expect( both ).toBeLessThanOrEqual( providerOnly );
	} );

	test( 'start dates are offered in chronological order', async ( { page } ) => {
		await page.getByRole( 'group', { name: /start dates/i } ).click();

		const labels = await page
			.locator( '#oxcd-start_date-list .oxcd-combobox__option label' )
			.allTextContents();

		const months = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July',
			'August', 'September', 'October', 'November', 'December' ];

		const keys = labels.map( ( label ) => {
			const [ , month, year ] = label.trim().match( /^(\w+)\s+(\d{4})/ );

			return Number( year ) * 100 + months.indexOf( month ) + 1;
		} );

		expect( keys ).toEqual( [ ...keys ].sort( ( a, b ) => a - b ) );
	} );

	test( 'clearing filters restores the full result set', async ( { page } ) => {
		const before = await totalFromSummary( page );

		await page.getByRole( 'group', { name: 'Providers' } ).getByRole( 'checkbox' ).first().check();
		await expect.poll( () => totalFromSummary( page ) ).toBeLessThan( before );

		await page.locator( '[data-oxcd-reset]' ).click();
		await expect.poll( () => totalFromSummary( page ) ).toBe( before );
	} );

	test( 'pagination moves through the result set', async ( { page } ) => {
		const first = await page.locator( '.oxcd-card__title' ).first().textContent();

		await page.getByRole( 'link', { name: /next/i } ).click();

		await expect( page.locator( '.oxcd-card__title' ).first() ).not.toHaveText( first );
		await expect( page ).toHaveURL( /paged=2/ );
	} );

	test( 'an impossible combination reports no matches', async ( { page } ) => {
		await page.goto( '/?q=zzzznotacourse' );

		await expect( summary( page ) ).toContainText( 'No courses match your search.' );
	} );
} );
