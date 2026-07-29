<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Feature;

use Oxford\CourseDiscovery\Frontend\Shortcode;
use Oxford\CourseDiscovery\Tests\Support\CourseTestCase;

/**
 * What a visitor actually receives.
 *
 * These assertions double as an accessibility contract: the markup the brief
 * requires (semantic grouping, labelled controls, a live region, a form that
 * works without JavaScript) is verified here rather than left to a manual
 * audit that only happens once.
 *
 * @covers \Oxford\CourseDiscovery\Frontend\Shortcode
 */
final class CourseFinderShortcodeTest extends CourseTestCase {

	protected function setUp(): void {
		parent::setUp();

		$provider = $this->makeProvider( 'UOSD', array( 'Oxford' ) );

		$this->makeCourse(
			'Graphic Design Foundation',
			array(
				'providers'         => array( $provider ),
				'categories'        => array( 'Design' ),
				'start_dates'       => '09-2030',
				'price'             => 9500,
				'short_description' => 'Typography and branding.',
			)
		);

		$this->makeCourse(
			'International Business',
			array(
				'providers' => array( $provider ),
				'price'     => 12500,
			)
		);

		$_GET = array();
	}

	protected function tearDown(): void {
		$_GET = array();

		parent::tearDown();
	}

	private function render(): string {
		return do_shortcode( '[' . Shortcode::TAG . ']' );
	}

	public function test_it_renders_a_search_form_and_results(): void {
		$html = $this->render();

		self::assertStringContainsString( 'role="search"', $html );
		self::assertStringContainsString( 'method="get"', $html );
		self::assertStringContainsString( 'Graphic Design Foundation', $html );
		self::assertStringContainsString( 'International Business', $html );
	}

	public function test_every_filter_control_is_rendered(): void {
		$html = $this->render();

		foreach ( array( 'q', 'provider[]', 'location[]', 'start_date[]', 'category[]' ) as $name ) {
			self::assertStringContainsString( 'name="' . $name . '"', $html );
		}
	}

	public function test_multi_value_controls_post_arrays(): void {
		$html = $this->render();

		// `provider[]` is what makes multi-select work without JavaScript.
		self::assertStringContainsString( 'type="checkbox"', $html );
		self::assertStringContainsString( 'name="provider[]"', $html );
	}

	public function test_grouped_controls_use_fieldset_and_legend(): void {
		$html = $this->render();

		self::assertStringContainsString( '<fieldset', $html );
		self::assertStringContainsString( '<legend', $html );
	}

	public function test_the_combobox_is_a_native_disclosure(): void {
		$html = $this->render();

		// <details>/<summary> is keyboard operable with no scripting at all.
		self::assertStringContainsString( '<details', $html );
		self::assertStringContainsString( '<summary', $html );
		self::assertStringContainsString( 'data-oxcd-combobox', $html );
	}

	public function test_the_results_region_is_announced(): void {
		$html = $this->render();

		self::assertStringContainsString( 'aria-live="polite"', $html );
		self::assertStringContainsString( 'role="status"', $html );
		self::assertStringContainsString( 'aria-busy="false"', $html );
	}

	public function test_every_input_is_labelled(): void {
		$html = $this->render();

		preg_match_all( '/<input[^>]*id="([^"]+)"[^>]*>/', $html, $matches );

		self::assertNotEmpty( $matches[1] );

		foreach ( $matches[1] as $id ) {
			self::assertStringContainsString(
				'for="' . $id . '"',
				$html,
				sprintf( 'Input "%s" has no associated label.', $id )
			);
		}
	}

	public function test_it_filters_from_the_query_string(): void {
		$_GET = array( 'category' => array( 'design' ) );

		$html = $this->render();

		self::assertStringContainsString( 'Graphic Design Foundation', $html );
		self::assertStringNotContainsString( 'International Business', $html );
	}

	public function test_a_selected_filter_stays_checked(): void {
		$_GET = array( 'provider' => array( 'uosd' ) );

		$html = $this->render();

		self::assertMatchesRegularExpression(
			'/name="provider\[\]"\s+value="uosd"\s+checked/',
			$html
		);
	}

	public function test_an_empty_result_set_explains_itself(): void {
		$_GET = array( 'location' => array( 'atlantis' ) );

		$html = $this->render();

		self::assertStringContainsString( 'No courses match your search.', $html );
		self::assertStringContainsString( 'Try removing a filter', $html );
	}

	public function test_pagination_links_preserve_the_search(): void {
		$_GET = array( 'per_page' => 1 );

		$html = $this->render();

		self::assertStringContainsString( 'oxcd-pagination', $html );
		self::assertStringContainsString( 'aria-label="Course results pages"', $html );
		self::assertStringContainsString( 'per_page=1', $html );
	}

	public function test_shortcode_attributes_act_as_defaults(): void {
		$html = do_shortcode( '[' . Shortcode::TAG . ' category="design" heading="Find a course"]' );

		self::assertStringContainsString( 'Find a course', $html );
		self::assertStringContainsString( 'Graphic Design Foundation', $html );
		self::assertStringNotContainsString( 'International Business', $html );
	}

	public function test_the_query_string_overrides_shortcode_defaults(): void {
		$_GET = array( 'category' => array( 'uncategorised-nonsense' ) );

		$html = do_shortcode( '[' . Shortcode::TAG . ' category="design"]' );

		self::assertStringNotContainsString( 'Graphic Design Foundation', $html );
	}
}
