import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.SITE_URL || 'http://localhost:8080';

export default defineConfig( {
	testDir: './tests',
	timeout: 30_000,
	expect: { timeout: 10_000 },
	fullyParallel: true,
	retries: process.env.CI ? 2 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			// Viewport-specific assertions belong to the mobile project, which
			// is the only one with a touch-capable context.
			testIgnore: /responsive\.spec\.js/,
		},
		{
			// The finder must work without JavaScript; this project proves it
			// rather than trusting the markup review.
			name: 'no-javascript',
			use: { ...devices[ 'Desktop Chrome' ], javaScriptEnabled: false },
			testMatch: /progressive-enhancement\.spec\.js/,
		},
		{
			name: 'mobile',
			use: { ...devices[ 'Pixel 7' ] },
			testMatch: /responsive\.spec\.js/,
		},
	],
} );
