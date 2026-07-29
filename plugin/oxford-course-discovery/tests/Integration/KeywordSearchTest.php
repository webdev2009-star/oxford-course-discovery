<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Integration;

use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Tests\Support\CourseTestCase;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;

/**
 * Keyword search against the real FULLTEXT index.
 *
 * This class deliberately opts out of the WordPress test suite's
 * transaction-per-test isolation, by overriding {@see self::start_transaction()}
 * with a no-op.
 *
 * The reason is a genuine InnoDB behaviour rather than a convenience: rows
 * written inside an open transaction are not visible to `MATCH ... AGAINST`
 * until that transaction commits. Under the standard harness every keyword
 * assertion would fail even though the feature works in production — the kind
 * of false negative that gets a test deleted rather than fixed. Committing for
 * real, and cleaning up explicitly afterwards, tests what actually ships.
 *
 * Everything else stays in the fast, isolated {@see CourseSearchTest}.
 *
 * @covers \Oxford\CourseDiscovery\Query\Constraint\KeywordConstraint
 */
final class KeywordSearchTest extends CourseTestCase {

	/**
	 * No-op: see the class docblock.
	 */
	public function start_transaction(): void {
	}

	protected function setUp(): void {
		parent::setUp();

		$this->deleteAllCourseContent();

		$provider = $this->makeProvider( 'UOSD', array( 'Oxford' ) );

		$this->makeCourse(
			'Graphic Design Foundation',
			array(
				'providers'         => array( $provider ),
				'short_description' => 'Typography and branding for beginners.',
				'content'           => 'Studio practice, print production and portfolio development.',
			)
		);

		$this->makeCourse(
			'Software Engineering',
			array(
				'providers'         => array( $provider ),
				'short_description' => 'Programming, systems design and testing.',
				'content'           => 'Distributed systems, algorithms and professional practice.',
			)
		);

		$this->makeCourse(
			'Marine Biology',
			array(
				'content' => 'Fieldwork on coastal ecosystems and cephalopod behaviour.',
			)
		);
	}

	protected function tearDown(): void {
		$this->deleteAllCourseContent();

		parent::tearDown();
	}

	public function test_it_matches_the_course_name(): void {
		self::assertSame( array( 'Software Engineering' ), $this->searchTitles( array( 'q' => 'software' ) ) );
	}

	public function test_it_matches_the_short_description(): void {
		self::assertSame( array( 'Graphic Design Foundation' ), $this->searchTitles( array( 'q' => 'typography' ) ) );
	}

	public function test_it_matches_the_long_description(): void {
		self::assertSame( array( 'Marine Biology' ), $this->searchTitles( array( 'q' => 'cephalopod' ) ) );
	}

	public function test_it_matches_a_word_prefix(): void {
		self::assertSame( array( 'Graphic Design Foundation' ), $this->searchTitles( array( 'q' => 'typograph' ) ) );
	}

	public function test_more_words_narrow_the_result_set(): void {
		$broad    = $this->searchTitles( array( 'q' => 'design' ) );
		$narrowed = $this->searchTitles( array( 'q' => 'typography branding' ) );

		self::assertGreaterThan( count( $narrowed ), count( $broad ) );
		self::assertSame( array( 'Graphic Design Foundation' ), $narrowed );
	}

	public function test_it_combines_with_other_filters(): void {
		self::assertSame(
			array(),
			$this->searchTitles(
				array(
					'q'        => 'cephalopod',
					'location' => array( 'oxford' ),
				)
			)
		);
	}

	public function test_an_unmatched_keyword_returns_nothing(): void {
		self::assertSame( array(), $this->searchTitles( array( 'q' => 'astrophysics' ) ) );
	}

	public function test_boolean_operators_in_user_input_cannot_break_the_query(): void {
		// A stray `-` in BOOLEAN MODE means "exclude"; a stray `"` is a syntax
		// error. Both are stripped before they reach MySQL.
		self::assertSame( array(), $this->searchTitles( array( 'q' => '-@#$%^' ) ) );
		self::assertSame( array( 'Marine Biology' ), $this->searchTitles( array( 'q' => '"cephalopod' ) ) );
	}

	public function test_relevance_ordering_ranks_a_title_match_first(): void {
		$titles = $this->searchTitles( array( 'q' => 'design' ) );

		self::assertNotEmpty( $titles );
		self::assertSame( 'Graphic Design Foundation', $titles[0] );
	}

	/**
	 * Without transactional rollback, cleanup is this class's own job.
	 */
	private function deleteAllCourseContent(): void {
		foreach ( array( CoursePostType::NAME, ProviderPostType::NAME ) as $postType ) {
			$ids = get_posts(
				array(
					'post_type'      => $postType,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
			}
		}

		$db = $this->container()->database();

		foreach ( Schema::tables() as $table ) {
			$db->execute( sprintf( 'TRUNCATE TABLE %s', $db->table( $table ) ) );
		}
	}
}
