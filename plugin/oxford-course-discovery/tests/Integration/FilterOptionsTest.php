<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Integration;

use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Filter\ProvidesOptions;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\Tests\Support\CourseTestCase;

/**
 * Facet generation: what the dropdowns actually contain.
 *
 * @covers \Oxford\CourseDiscovery\Filter\Options\StartDateOptions
 * @covers \Oxford\CourseDiscovery\Filter\Options\LocationOptions
 * @covers \Oxford\CourseDiscovery\Filter\Options\ProviderOptions
 * @covers \Oxford\CourseDiscovery\Filter\Options\TermOptions
 */
final class FilterOptionsTest extends CourseTestCase {

	protected function setUp(): void {
		parent::setUp();

		$oxford = $this->makeProvider( 'UOSD', array( 'Oxford' ) );
		$dmu    = $this->makeProvider( 'DMU', array( 'Leicester' ) );

		$this->makeCourse(
			'Later intake',
			array(
				'providers'   => array( $oxford ),
				'start_dates' => '01-2031, 09-2030',
				'categories'  => array( 'Design' ),
			)
		);

		$this->makeCourse(
			'Earlier intake',
			array(
				'providers'   => array( $dmu ),
				'start_dates' => '05-2030, 09-2030',
				'categories'  => array( 'Business' ),
			)
		);
	}

	/**
	 * The brief is explicit: intakes must be offered in chronological order.
	 * A naive `SELECT DISTINCT meta_value ORDER BY meta_value` returns
	 * 01-2031 before 05-2030 and 09-2030 — this is that regression, pinned.
	 */
	public function test_start_dates_are_offered_in_chronological_order(): void {
		$values = $this->optionsFor( 'start_date' )->values();

		self::assertSame( array( '05-2030', '09-2030', '01-2031' ), $values );
	}

	public function test_start_date_options_are_deduplicated_with_counts(): void {
		$options = $this->optionsFor( 'start_date' );
		$shared  = $options->find( '09-2030' );

		self::assertInstanceOf( FilterOption::class, $shared );
		self::assertSame( 2, $shared->count );
		self::assertSame( 'September 2030', $shared->label );
	}

	public function test_start_date_options_use_the_month_year_label_format(): void {
		$option = $this->optionsFor( 'start_date' )->find( '01-2031' );

		self::assertNotNull( $option );
		self::assertSame( 'January 2031', $option->label );
	}

	public function test_location_options_come_from_providers(): void {
		$values = $this->optionsFor( 'location' )->values();

		self::assertEqualsCanonicalizing( array( 'oxford', 'leicester' ), $values );
	}

	public function test_provider_options_carry_course_counts(): void {
		$option = $this->optionsFor( 'provider' )->find( 'uosd' );

		self::assertNotNull( $option );
		self::assertSame( 'UOSD', $option->label );
		self::assertSame( 1, $option->count );
	}

	public function test_category_options_preserve_hierarchy(): void {
		$parent = wp_insert_term( 'Creative Arts', 'course_category' );
		self::assertIsArray( $parent );

		wp_insert_term( 'Illustration', 'course_category', array( 'parent' => (int) $parent['term_id'] ) );

		$courseId = $this->makeCourse( 'Illustration BA', array() );
		wp_set_object_terms( $courseId, array( 'Illustration' ), 'course_category' );
		$this->container()->indexer()->index( $courseId );

		$options  = $this->optionsFor( 'category' );
		$creative = $options->find( 'creative-arts' );

		self::assertNotNull( $creative );
		self::assertTrue( $creative->hasChildren() );
		self::assertSame( array( 'illustration' ), $creative->children()->values() );
	}

	public function test_options_can_be_replaced_by_a_hook(): void {
		add_filter(
			Hooks::FILTER_OPTIONS,
			static function ( FilterOptionCollection $options, FilterKey $key ): FilterOptionCollection {
				return 'location' === $key->value
					? FilterOptionCollection::fromPairs( array( 'mars' => 'Mars' ) )
					: $options;
			},
			10,
			3
		);

		$views = $this->container()->finder()->filterViews( SearchCriteria::empty() );
		$found = null;

		foreach ( $views as $view ) {
			if ( 'location' === $view->key() ) {
				$found = $view;
			}
		}

		self::assertNotNull( $found );
		self::assertSame( array( 'mars' ), $found->options->values() );
	}

	private function optionsFor( string $key ): FilterOptionCollection {
		$filter = $this->container()->filters()->require( FilterKey::fromString( $key ) );

		self::assertInstanceOf( ProvidesOptions::class, $filter );

		return $filter->options( SearchCriteria::empty() );
	}
}
