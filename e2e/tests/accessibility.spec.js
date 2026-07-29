import { expect, test } from '@playwright/test';

/**
 * The brief requires the layout to be fully operable without a pointing
 * device, so these tests drive the UI with the keyboard only.
 */
test.describe( 'Keyboard accessibility', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/' );
		await expect( page.locator( '[data-oxcd-finder]' ) ).toBeVisible();
	} );

	test( 'every control is reachable by tabbing', async ( { page } ) => {
		const reachable = new Set();

		await page.locator( 'body' ).press( 'Tab' );

		for ( let step = 0; step < 60; step++ ) {
			const id = await page.evaluate( () => document.activeElement?.id || document.activeElement?.tagName );

			if ( id ) {
				reachable.add( id );
			}

			await page.keyboard.press( 'Tab' );
		}

		expect( [ ...reachable ].some( ( id ) => String( id ).startsWith( 'oxcd-q' ) ) ).toBe( true );
		expect( [ ...reachable ].some( ( id ) => String( id ).startsWith( 'oxcd-provider' ) ) ).toBe( true );
		expect( [ ...reachable ].some( ( id ) => String( id ) === 'oxcd-orderby' ) ).toBe( true );
	} );

	test( 'the combobox opens, selects and closes from the keyboard alone', async ( { page } ) => {
		const disclosure = page.locator( '#oxcd-location-list' );
		const trigger = page.getByRole( 'group', { name: 'Locations' } ).locator( 'summary' );

		await trigger.focus();
		await page.keyboard.press( 'Enter' );
		await expect( disclosure ).toBeVisible();

		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'Space' );

		await expect( disclosure.getByRole( 'checkbox' ).first() ).toBeChecked();

		await page.keyboard.press( 'Escape' );
		await expect( disclosure ).toBeHidden();
	} );

	test( 'the results region is a live region', async ( { page } ) => {
		const region = page.locator( '[data-oxcd-summary]' );

		await expect( region ).toHaveAttribute( 'aria-live', 'polite' );
		await expect( region ).toHaveAttribute( 'role', 'status' );
	} );

	test( 'grouped filters expose an accessible name', async ( { page } ) => {
		for ( const name of [ 'Providers', 'Locations', 'Start dates', 'Categories' ] ) {
			await expect( page.getByRole( 'group', { name } ) ).toBeVisible();
		}
	} );

	test( 'the search landmark is labelled', async ( { page } ) => {
		await expect(
			page.getByRole( 'search', { name: /course search and filters/i } )
		).toBeVisible();
	} );

	test( 'pagination is a labelled navigation landmark', async ( { page } ) => {
		await expect(
			page.getByRole( 'navigation', { name: /course results pages/i } )
		).toBeVisible();
	} );
} );
