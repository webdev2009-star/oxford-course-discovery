import { expect, test } from '@playwright/test';

/**
 * Runs in the `mobile` project (Pixel 7 viewport).
 */
test.describe( 'Small viewport', () => {
	test( 'the layout does not scroll horizontally', async ( { page } ) => {
		await page.goto( '/' );

		const overflow = await page.evaluate(
			() => document.documentElement.scrollWidth - document.documentElement.clientWidth
		);

		expect( overflow ).toBeLessThanOrEqual( 1 );
	} );

	test( 'filters stack into a single column', async ( { page } ) => {
		await page.goto( '/' );

		const boxes = await page.locator( '.oxcd-filters > *' ).evaluateAll( ( nodes ) =>
			nodes.map( ( node ) => node.getBoundingClientRect().left )
		);

		expect( new Set( boxes.map( Math.round ) ).size ).toBe( 1 );
	} );

	test( 'the finder is usable by touch', async ( { page } ) => {
		await page.goto( '/' );

		await page.getByRole( 'group', { name: 'Locations' } ).locator( 'summary' ).tap();
		await expect( page.locator( '#oxcd-location-list' ) ).toBeVisible();
	} );
} );
