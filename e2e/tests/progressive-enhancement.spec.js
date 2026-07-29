import { expect, test } from '@playwright/test';

/**
 * Runs in the `no-javascript` project. Everything here must pass with
 * scripting disabled — the finder degrades to a plain GET form.
 */
test.describe( 'Without JavaScript', () => {
	test( 'results are rendered server side', async ( { page } ) => {
		await page.goto( '/' );

		await expect( page.locator( '[data-oxcd-summary]' ) ).toContainText( /Showing \d+/ );
		expect( await page.locator( '.oxcd-card' ).count() ).toBeGreaterThan( 0 );
	} );

	test( 'the form submits and filters', async ( { page } ) => {
		await page.goto( '/' );

		await page.getByRole( 'searchbox', { name: /search courses/i } ).fill( 'design' );
		await page.getByRole( 'button', { name: /show results/i } ).click();

		await expect( page ).toHaveURL( /q=design/ );
		await expect( page.locator( '[data-oxcd-summary]' ) ).toContainText( /Showing \d+/ );
	} );

	test( 'the combobox still opens and selects', async ( { page } ) => {
		await page.goto( '/' );

		await page.getByRole( 'group', { name: 'Locations' } ).locator( 'summary' ).click();

		const option = page.locator( '#oxcd-location-list' ).getByRole( 'checkbox' ).first();
		await option.check();
		await expect( option ).toBeChecked();

		await page.getByRole( 'button', { name: /show results/i } ).click();
		await expect( page ).toHaveURL( /location/ );
	} );

	test( 'pagination links work as ordinary links', async ( { page } ) => {
		await page.goto( '/' );
		await page.getByRole( 'link', { name: /next/i } ).click();

		await expect( page ).toHaveURL( /paged=2/ );
		expect( await page.locator( '.oxcd-card' ).count() ).toBeGreaterThan( 0 );
	} );
} );
