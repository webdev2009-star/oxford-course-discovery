<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Cli;

use Oxford\CourseDiscovery\Container;
use Oxford\CourseDiscovery\WordPress\Fields\FieldKeys;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use Oxford\CourseDiscovery\WordPress\PostType\InstructorPostType;
use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;
use Oxford\CourseDiscovery\WordPress\Taxonomy\CourseCategoryTaxonomy;
use Oxford\CourseDiscovery\WordPress\Taxonomy\LocationTaxonomy;
use WP_CLI;

/**
 * `wp oxcd <command>` — the operational surface of the plugin.
 *
 * Migrations and reindexing are deliberately available from the command line:
 * a deploy pipeline should never depend on somebody visiting an admin screen.
 */
final class Commands {

	public function __construct( private readonly Container $container ) {
	}

	public static function register( Container $container ): void {
		WP_CLI::add_command( 'oxcd', new self( $container ) );
	}

	/**
	 * Run pending database migrations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oxcd migrate
	 *
	 * @param list<string>          $args       Positional arguments.
	 * @param array<string, string> $assocArgs  Flags.
	 */
	public function migrate( array $args = array(), array $assocArgs = array() ): void {
		$migrator = $this->container->migrator();
		$ran      = $migrator->migrate();

		if ( array() === $ran ) {
			WP_CLI::success( sprintf( 'Schema already at version %d.', $migrator->currentVersion() ) );

			return;
		}

		foreach ( $ran as $name ) {
			WP_CLI::log( '  ✓ ' . $name );
		}

		WP_CLI::success( sprintf( 'Migrated to schema version %d.', $migrator->currentVersion() ) );
	}

	/**
	 * Rebuild the course lookup and search index tables.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<size>]
	 * : Courses per batch. Default 200.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oxcd reindex
	 *
	 * @param list<string>          $args      Positional arguments.
	 * @param array<string, string> $assocArgs Flags.
	 */
	public function reindex( array $args = array(), array $assocArgs = array() ): void {
		$this->container->migrator()->migrate();

		$batch = max( 1, (int) ( $assocArgs['batch'] ?? 200 ) );
		$count = $this->container->indexer()->reindexAll( $batch );

		WP_CLI::success( sprintf( 'Indexed %d course(s).', $count ) );
	}

	/**
	 * Generate demo content: providers, instructors, categories and courses.
	 *
	 * ## OPTIONS
	 *
	 * [--courses=<number>]
	 * : How many courses to create. Default 40.
	 *
	 * [--fresh]
	 * : Delete existing demo content first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oxcd seed --courses=60 --fresh
	 *
	 * @param list<string>          $args      Positional arguments.
	 * @param array<string, string> $assocArgs Flags.
	 */
	public function seed( array $args = array(), array $assocArgs = array() ): void {
		$total = max( 1, (int) ( $assocArgs['courses'] ?? 40 ) );

		if ( isset( $assocArgs['fresh'] ) ) {
			$this->deleteDemoContent();
			WP_CLI::log( 'Removed existing demo content.' );
		}

		$this->container->migrator()->migrate();

		$locations   = $this->seedLocations();
		$providers   = $this->seedProviders( $locations );
		$instructors = $this->seedInstructors();
		$categories  = $this->seedCategories();

		$created = 0;

		for ( $i = 1; $i <= $total; $i++ ) {
			$this->seedCourse( $i, $providers, $instructors, $categories );
			++$created;
		}

		$indexed = $this->container->indexer()->reindexAll();

		WP_CLI::success(
			sprintf(
				'Created %d course(s), %d provider(s), %d instructor(s); indexed %d course(s).',
				$created,
				count( $providers ),
				count( $instructors ),
				$indexed
			)
		);
	}

	/**
	 * @return array<string, int> Slug => term ID.
	 */
	private function seedLocations(): array {
		$names = array( 'London', 'Oxford', 'Brighton', 'Manchester', 'Dublin', 'Toronto', 'Delhi', 'Shanghai' );
		$terms = array();

		foreach ( $names as $name ) {
			$terms[ sanitize_title( $name ) ] = $this->ensureTerm( $name, LocationTaxonomy::NAME );
		}

		return $terms;
	}

	/**
	 * @return array<string, int> Slug => term ID.
	 */
	private function seedCategories(): array {
		$tree = array(
			'Business'    => array( 'Accounting', 'Marketing' ),
			'Design'      => array( 'Graphic Design', 'User Experience' ),
			'Engineering' => array( 'Civil Engineering', 'Software Engineering' ),
			'Humanities'  => array( 'English Literature', 'History' ),
		);

		$terms = array();

		foreach ( $tree as $parentName => $children ) {
			$parentId                               = $this->ensureTerm( $parentName, CourseCategoryTaxonomy::NAME );
			$terms[ sanitize_title( $parentName ) ] = $parentId;

			foreach ( $children as $child ) {
				$terms[ sanitize_title( $child ) ] = $this->ensureTerm( $child, CourseCategoryTaxonomy::NAME, $parentId );
			}
		}

		return $terms;
	}

	/**
	 * @param array<string, int> $locations Location terms.
	 *
	 * @return list<int>
	 */
	private function seedProviders( array $locations ): array {
		$providers = array(
			'University of Oxford International Study Centre' => array( 'oxford', 'london' ),
			'De Montfort University'    => array( 'manchester', 'london' ),
			'University of Brighton'    => array( 'brighton' ),
			'Trinity College Dublin'    => array( 'dublin' ),
			'Bangor University'         => array( 'manchester' ),
			'Global Pathways Institute' => array( 'delhi', 'shanghai', 'toronto' ),
		);

		$ids = array();

		foreach ( $providers as $name => $locationSlugs ) {
			$id = $this->ensurePost( $name, ProviderPostType::NAME );

			$termIds = array();

			foreach ( $locationSlugs as $slug ) {
				if ( isset( $locations[ $slug ] ) ) {
					$termIds[] = $locations[ $slug ];
				}
			}

			wp_set_object_terms( $id, $termIds, LocationTaxonomy::NAME );
			update_post_meta( $id, FieldKeys::PROVIDER_URL, 'https://example.org/' . sanitize_title( $name ) );

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * @return list<int>
	 */
	private function seedInstructors(): array {
		$people = array(
			'Dr Amara Okafor'   => 'Programme Lead',
			'Prof. Liam Hughes' => 'Head of Design',
			'Dr Wei Zhang'      => 'Senior Lecturer',
			'Sofia Marchetti'   => 'Course Director',
			'Dr Priya Raman'    => 'Associate Professor',
			'James Fitzgerald'  => 'Industry Fellow',
		);

		$ids = array();

		foreach ( $people as $name => $role ) {
			$id = $this->ensurePost( $name, InstructorPostType::NAME );
			update_post_meta( $id, FieldKeys::INSTRUCTOR_ROLE, $role );

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * @param list<int>          $providers   Provider IDs.
	 * @param list<int>          $instructors Instructor IDs.
	 * @param array<string, int> $categories  Category terms.
	 */
	private function seedCourse( int $index, array $providers, array $instructors, array $categories ): void {
		$subjects = array( 'Graphic Design', 'Software Engineering', 'Marketing', 'Civil Engineering', 'English Literature', 'User Experience', 'Accounting', 'History' );
		$levels   = array( 'Foundation', 'BSc (Hons)', 'MSc', 'Pre-Master\'s', 'International Year One' );

		$subject = $subjects[ $index % count( $subjects ) ];
		$level   = $levels[ $index % count( $levels ) ];
		$title   = sprintf( '%s %s', $level, $subject );

		$postId = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_type'    => CoursePostType::NAME,
				'post_status'  => 'publish',
				'post_content' => sprintf(
					'This %s programme in %s prepares international students for progression to degree level study. Modules cover academic English, subject knowledge and independent research skills, with small class sizes and dedicated tutorial support throughout the year.',
					strtolower( $level ),
					strtolower( $subject )
				),
			),
			true
		);

		if ( is_wp_error( $postId ) ) {
			WP_CLI::warning( $postId->get_error_message() );

			return;
		}

		$postId = (int) $postId;

		update_post_meta(
			$postId,
			FieldKeys::SHORT_DESCRIPTION,
			sprintf( 'A %s route into %s, taught with intensive academic support.', strtolower( $level ), strtolower( $subject ) )
		);
		update_post_meta( $postId, FieldKeys::PRICE, (string) ( 8500 + ( $index % 7 ) * 1250 ) );
		update_post_meta( $postId, FieldKeys::CURRENCY, 'GBP' );

		update_post_meta( $postId, FieldKeys::START_DATES, implode( ', ', $this->upcomingIntakes( $index ) ) );

		$providerCount = 1 + ( $index % 2 );
		update_post_meta(
			$postId,
			FieldKeys::PROVIDERS,
			array_slice( $this->rotate( $providers, $index ), 0, $providerCount )
		);

		update_post_meta(
			$postId,
			FieldKeys::INSTRUCTORS,
			array_slice( $this->rotate( $instructors, $index ), 0, 2 )
		);

		$slug = sanitize_title( $subject );

		if ( isset( $categories[ $slug ] ) ) {
			wp_set_object_terms( $postId, array( $categories[ $slug ] ), CourseCategoryTaxonomy::NAME );
		}
	}

	/**
	 * Two or three intakes from the standard January / May / September cycle,
	 * always in the future so the "upcoming intakes" facet is populated.
	 *
	 * @return list<string> `MM-YYYY` values.
	 */
	private function upcomingIntakes( int $index ): array {
		$currentKey = (int) gmdate( 'Y' ) * 100 + (int) gmdate( 'n' );
		$candidates = array();

		$thisYear = (int) gmdate( 'Y' );

		for ( $year = $thisYear; $year <= $thisYear + 2; $year++ ) {
			foreach ( array( 1, 5, 9 ) as $month ) {
				if ( $year * 100 + $month >= $currentKey ) {
					$candidates[] = sprintf( '%02d-%d', $month, $year );
				}
			}
		}

		$offset = $index % max( 1, count( $candidates ) - 2 );

		return array_slice( $candidates, $offset, 2 + ( $index % 2 ) );
	}

	/**
	 * @param list<int> $items Items to rotate.
	 *
	 * @return list<int>
	 */
	private function rotate( array $items, int $by ): array {
		if ( array() === $items ) {
			return array();
		}

		$offset = $by % count( $items );

		return array( ...array_slice( $items, $offset ), ...array_slice( $items, 0, $offset ) );
	}

	private function ensureTerm( string $name, string $taxonomy, int $parentId = 0 ): int {
		$existing = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );

		if ( $existing instanceof \WP_Term ) {
			return $existing->term_id;
		}

		$created = wp_insert_term( $name, $taxonomy, array( 'parent' => $parentId ) );

		return is_array( $created ) ? (int) $created['term_id'] : 0;
	}

	private function ensurePost( string $title, string $postType ): int {
		$existing = get_page_by_path( sanitize_title( $title ), OBJECT, $postType );

		if ( $existing instanceof \WP_Post ) {
			return $existing->ID;
		}

		return (int) wp_insert_post(
			array(
				'post_title'  => $title,
				'post_name'   => sanitize_title( $title ),
				'post_type'   => $postType,
				'post_status' => 'publish',
			)
		);
	}

	private function deleteDemoContent(): void {
		foreach ( array( CoursePostType::NAME, ProviderPostType::NAME, InstructorPostType::NAME ) as $postType ) {
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
	}
}
