<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Integration;

use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Tests\Support\CourseTestCase;
use Oxford\CourseDiscovery\WordPress\Taxonomy\LocationTaxonomy;

/**
 * The indexer is the highest risk component in the system: every filter reads
 * what it writes, and a stale row is invisible until a user notices a missing
 * course. These tests pin the derivation rules and the propagation paths.
 *
 * @covers \Oxford\CourseDiscovery\Indexing\CourseIndexer
 */
final class CourseIndexerTest extends CourseTestCase {

	public function test_it_writes_one_row_per_intake(): void {
		$courseId = $this->makeCourse( 'Foundation Design', array( 'start_dates' => '09-2026, 01-2027' ) );

		self::assertSame(
			array( '202609', '202701' ),
			$this->lookupColumn( Schema::START_DATES, 'sort_key', $courseId )
		);
	}

	public function test_it_deduplicates_repeated_intakes(): void {
		$courseId = $this->makeCourse( 'Repeats', array( 'start_dates' => '09-2026, 9-2026, September 2026' ) );

		self::assertCount( 1, $this->lookupColumn( Schema::START_DATES, 'sort_key', $courseId ) );
	}

	public function test_it_flattens_the_provider_relationship(): void {
		$oxford   = $this->makeProvider( 'Oxford International' );
		$montfort = $this->makeProvider( 'De Montfort University' );
		$courseId = $this->makeCourse( 'Business', array( 'providers' => array( $oxford, $montfort ) ) );

		self::assertEqualsCanonicalizing(
			array( 'oxford-international', 'de-montfort-university' ),
			$this->lookupColumn( Schema::PROVIDERS, 'provider_slug', $courseId )
		);
	}

	/**
	 * The derived-field rule from the brief: locations come from the provider,
	 * never from the course.
	 */
	public function test_it_derives_locations_from_providers(): void {
		$provider = $this->makeProvider( 'Global Pathways', array( 'Delhi', 'Shanghai' ) );
		$courseId = $this->makeCourse( 'Engineering', array( 'providers' => array( $provider ) ) );

		self::assertEqualsCanonicalizing(
			array( 'delhi', 'shanghai' ),
			$this->lookupColumn( Schema::LOCATIONS, 'location_slug', $courseId )
		);
	}

	public function test_it_unions_locations_across_providers(): void {
		$first    = $this->makeProvider( 'First Provider', array( 'London' ) );
		$second   = $this->makeProvider( 'Second Provider', array( 'London', 'Oxford' ) );
		$courseId = $this->makeCourse( 'Shared', array( 'providers' => array( $first, $second ) ) );

		$slugs = $this->lookupColumn( Schema::LOCATIONS, 'location_slug', $courseId );

		self::assertEqualsCanonicalizing( array( 'london', 'london', 'oxford' ), $slugs );
		self::assertSame( array( 'london', 'oxford' ), array_values( array_unique( $slugs ) ) );
	}

	/**
	 * Editing a provider must ripple out to every course that references it —
	 * the propagation path that is easiest to forget.
	 */
	public function test_changing_provider_locations_reindexes_its_courses(): void {
		$provider = $this->makeProvider( 'Mobile Campus', array( 'London' ) );
		$courseId = $this->makeCourse( 'Relocating course', array( 'providers' => array( $provider ) ) );

		wp_set_object_terms( $provider, array( 'Brighton' ), LocationTaxonomy::NAME );
		$this->container()->indexer()->reindexCoursesForProvider( $provider );

		self::assertSame(
			array( 'brighton' ),
			$this->lookupColumn( Schema::LOCATIONS, 'location_slug', $courseId )
		);
	}

	public function test_indexing_is_idempotent(): void {
		$provider = $this->makeProvider( 'Steady', array( 'Oxford' ) );
		$courseId = $this->makeCourse(
			'Steady course',
			array(
				'providers'   => array( $provider ),
				'start_dates' => '09-2026',
			)
		);

		$this->container()->indexer()->index( $courseId );
		$this->container()->indexer()->index( $courseId );

		self::assertCount( 1, $this->lookupColumn( Schema::LOCATIONS, 'location_slug', $courseId ) );
		self::assertCount( 1, $this->lookupColumn( Schema::START_DATES, 'sort_key', $courseId ) );
	}

	public function test_unpublishing_removes_a_course_from_the_index(): void {
		$courseId = $this->makeCourse( 'Draft bound', array( 'start_dates' => '09-2026' ) );

		wp_update_post(
			array(
				'ID'          => $courseId,
				'post_status' => 'draft',
			)
		);

		self::assertSame( array(), $this->lookupColumn( Schema::START_DATES, 'sort_key', $courseId ) );
	}

	public function test_deleting_a_course_removes_its_rows(): void {
		$courseId = $this->makeCourse( 'Doomed', array( 'start_dates' => '09-2026' ) );

		wp_delete_post( $courseId, true );

		self::assertSame( array(), $this->lookupColumn( Schema::START_DATES, 'sort_key', $courseId ) );
		self::assertSame( array(), $this->lookupColumn( Schema::SEARCH_INDEX, 'course_id', $courseId ) );
	}

	public function test_it_populates_the_search_index(): void {
		$courseId = $this->makeCourse(
			'Graphic Design',
			array(
				'short_description' => 'A creative foundation.',
				'content'           => '<p>Typography, branding and <strong>layout</strong>.</p>',
			)
		);

		$row = $this->searchRow( $courseId );

		self::assertSame( 'Graphic Design', $row['name'] );
		self::assertSame( 'A creative foundation.', $row['short_description'] );
		self::assertStringContainsString( 'Typography, branding and layout.', $row['long_description'] );
		self::assertStringNotContainsString( '<strong>', $row['long_description'] );
	}

	public function test_reindex_all_rebuilds_everything(): void {
		$provider = $this->makeProvider( 'Rebuilder', array( 'Oxford' ) );
		$courseId = $this->makeCourse(
			'Rebuild me',
			array(
				'providers'   => array( $provider ),
				'start_dates' => '09-2026',
			)
		);

		$this->container()->indexer()->remove( $courseId );
		self::assertSame( array(), $this->lookupColumn( Schema::START_DATES, 'sort_key', $courseId ) );

		$indexed = $this->container()->indexer()->reindexAll();

		self::assertGreaterThanOrEqual( 1, $indexed );
		self::assertSame( array( '202609' ), $this->lookupColumn( Schema::START_DATES, 'sort_key', $courseId ) );
	}

	/**
	 * @return list<string>
	 */
	private function lookupColumn( string $table, string $column, int $courseId ): array {
		$db = $this->container()->database();

		return $db->column(
			$db->prepare(
				sprintf(
					'SELECT %s FROM %s WHERE %s = %%d ORDER BY %s ASC',
					$column,
					$db->table( $table ),
					Schema::COURSE_COLUMN,
					$column
				),
				array( $courseId )
			)
		);
	}

	/**
	 * @return array<string, string|null>
	 */
	private function searchRow( int $courseId ): array {
		$db = $this->container()->database();

		$rows = $db->results(
			$db->prepare(
				sprintf( 'SELECT * FROM %s WHERE course_id = %%d', $db->table( Schema::SEARCH_INDEX ) ),
				array( $courseId )
			)
		);

		return $rows[0] ?? array();
	}
}
