<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Integration;

use Oxford\CourseDiscovery\Search\Ordering;
use Oxford\CourseDiscovery\Tests\Support\CourseTestCase;

/**
 * End-to-end filter behaviour against a real database.
 *
 * Where the unit suite asserts the SQL that *would* run, this suite asserts the
 * courses that actually come back — the pair together is what makes a refactor
 * of the query layer safe.
 *
 * @covers \Oxford\CourseDiscovery\Query\WpCourseRepository
 */
final class CourseSearchTest extends CourseTestCase {

	private int $oxford;
	private int $montfort;
	private int $pathways;

	protected function setUp(): void {
		parent::setUp();

		$this->oxford   = $this->makeProvider( 'UOSD', array( 'Oxford' ) );
		$this->montfort = $this->makeProvider( 'DMU', array( 'Leicester', 'India' ) );
		$this->pathways = $this->makeProvider( 'Pathways', array( 'China' ) );

		$this->makeCourse(
			'Graphic Design Foundation',
			array(
				'providers'         => array( $this->oxford ),
				'categories'        => array( 'Design' ),
				'start_dates'       => '09-2029',
				'price'             => 9500,
				'short_description' => 'Typography and branding for beginners.',
			)
		);

		$this->makeCourse(
			'International Business',
			array(
				'providers'         => array( $this->montfort ),
				'categories'        => array( 'Business' ),
				'start_dates'       => '01-2030',
				'price'             => 12500,
				'short_description' => 'Trade, economics and management.',
			)
		);

		$this->makeCourse(
			'Software Engineering',
			array(
				'providers'         => array( $this->montfort, $this->pathways ),
				'categories'        => array( 'Engineering' ),
				'start_dates'       => '09-2029, 01-2030',
				'price'             => 11000,
				'short_description' => 'Programming, systems design and testing.',
			)
		);

		$this->makeCourse(
			'Hidden Draft Course',
			array(
				'providers' => array( $this->oxford ),
				'status'    => 'draft',
			)
		);
	}

	public function test_an_unfiltered_search_returns_every_published_course(): void {
		$titles = $this->searchTitles( array() );

		self::assertCount( 3, $titles );
		self::assertNotContains( 'Hidden Draft Course', $titles );
	}

	public function test_values_within_a_filter_are_ored(): void {
		$titles = $this->searchTitles( array( 'provider' => array( 'uosd', 'dmu' ) ) );

		self::assertEqualsCanonicalizing(
			array( 'Graphic Design Foundation', 'International Business', 'Software Engineering' ),
			$titles
		);
	}

	public function test_separate_filters_are_anded(): void {
		$titles = $this->searchTitles(
			array(
				'provider' => array( 'uosd', 'dmu' ),
				'category' => array( 'business' ),
			)
		);

		self::assertSame( array( 'International Business' ), $titles );
	}

	/**
	 * The worked example from the brief.
	 */
	public function test_the_worked_example_from_the_brief(): void {
		$titles = $this->searchTitles(
			array(
				'provider' => array( 'dmu', 'pathways' ),
				'location' => array( 'india', 'china' ),
				'category' => array( 'engineering' ),
			)
		);

		self::assertSame( array( 'Software Engineering' ), $titles );
	}

	public function test_filtering_by_a_derived_location(): void {
		$titles = $this->searchTitles( array( 'location' => array( 'india' ) ) );

		self::assertEqualsCanonicalizing(
			array( 'International Business', 'Software Engineering' ),
			$titles
		);
	}

	public function test_filtering_by_start_date(): void {
		$titles = $this->searchTitles( array( 'start_date' => array( '01-2030' ) ) );

		self::assertEqualsCanonicalizing(
			array( 'International Business', 'Software Engineering' ),
			$titles
		);
	}

	public function test_a_course_matches_any_of_its_intakes(): void {
		$titles = $this->searchTitles( array( 'start_date' => array( '09-2029', '01-2030' ) ) );

		self::assertCount( 3, $titles );
		self::assertContains( 'Software Engineering', $titles );
	}

	public function test_an_impossible_combination_returns_nothing(): void {
		$titles = $this->searchTitles(
			array(
				'provider' => array( 'uosd' ),
				'location' => array( 'china' ),
			)
		);

		self::assertSame( array(), $titles );
	}

	/**
	 * Keyword behaviour lives in {@see KeywordSearchTest}: InnoDB does not
	 * expose uncommitted rows to `MATCH ... AGAINST`, so it needs a
	 * non-transactional harness.
	 */
	public function test_results_are_ordered_by_soonest_intake_by_default(): void {
		$titles = $this->searchTitles( array() );

		self::assertSame( 'Graphic Design Foundation', $titles[0] );
	}

	public function test_results_can_be_ordered_by_name(): void {
		$titles = $this->searchTitles( array( 'orderby' => Ordering::NAME ) );

		self::assertSame(
			array( 'Graphic Design Foundation', 'International Business', 'Software Engineering' ),
			$titles
		);
	}

	public function test_results_can_be_ordered_by_price(): void {
		$titles = $this->searchTitles( array( 'orderby' => Ordering::PRICE ) );

		self::assertSame( 'Graphic Design Foundation', $titles[0] );
		self::assertSame( 'International Business', $titles[2] );
	}

	public function test_pagination_splits_the_result_set_without_overlap(): void {
		$criteria = $this->container()->criteriaFactory()->fromRequest(
			array(
				'per_page' => 2,
				'orderby'  => Ordering::NAME,
			)
		);

		$repository = $this->container()->repository();
		$first      = $repository->search( $criteria );
		$second     = $repository->search( $criteria->withPage( 2 ) );

		self::assertSame( 3, $first->total );
		self::assertCount( 2, $first->courses->toArray() );
		self::assertCount( 1, $second->courses->toArray() );
		self::assertSame( array(), array_intersect( $first->courses->ids(), $second->courses->ids() ) );
		self::assertSame( 2, $first->totalPages() );
		self::assertTrue( $first->hasNextPage() );
		self::assertFalse( $second->hasNextPage() );
	}

	public function test_count_matches_the_search_total(): void {
		$criteria = $this->container()->criteriaFactory()->fromRequest( array( 'provider' => array( 'dmu' ) ) );

		self::assertSame(
			$this->container()->repository()->search( $criteria )->total,
			$this->container()->repository()->count( $criteria )
		);
	}

	public function test_a_course_is_hydrated_with_its_derived_data(): void {
		$criteria = $this->container()->criteriaFactory()->fromRequest( array( 'category' => array( 'design' ) ) );
		$course   = $this->container()->repository()->search( $criteria )->courses->first();

		self::assertNotNull( $course );
		self::assertSame( 'Graphic Design Foundation', $course->name->value );
		self::assertSame( array( 'UOSD' ), $course->providers->names() );
		self::assertSame( array( 'Oxford' ), $course->locations->names() );
		self::assertSame( array( 'Design' ), $course->categories->names() );
		self::assertSame( '£9,500', $course->formattedPrice() );
		self::assertSame( array( '09-2029' ), $course->startDates->toStrings() );
	}
}
