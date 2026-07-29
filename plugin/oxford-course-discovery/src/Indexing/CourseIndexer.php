<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Indexing;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Domain\Collection\StartDateCollection;
use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;
use Oxford\CourseDiscovery\Filter\Options\CachingOptions;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\WordPress\Fields\FieldKeys;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use Oxford\CourseDiscovery\WordPress\Taxonomy\LocationTaxonomy;
use WP_Post;
use WP_Term;

/**
 * Keeps the lookup tables in step with editorial changes.
 *
 * Write-time denormalisation: reads are the hot path, writes are rare and
 * human-paced. Each rebuild is a delete-then-insert for one course, so it is
 * idempotent — a missed event costs a stale row until the next save or
 * `wp oxcd reindex`, never a corrupt index.
 *
 * The subtle part is propagation: locations belong to the *provider*, so
 * editing one must reindex every course referencing it.
 */
final class CourseIndexer {

	public function __construct( private readonly DatabaseGateway $db ) {
	}

	public function boot(): void {
		// Late priority: ACF writes its fields on save_post at priority 10.
		add_action( 'save_post_' . CoursePostType::NAME, $this->onCourseSaved( ... ), 20, 2 );
		add_action( 'deleted_post', $this->onPostDeleted( ... ), 10, 2 );
		add_action( 'trashed_post', $this->onPostTrashed( ... ) );
		add_action( 'untrashed_post', $this->onPostUntrashed( ... ) );

		// A provider's title, slug or locations changing invalidates every
		// course that points at it.
		add_action( 'save_post_provider', $this->onProviderSaved( ... ), 20 );
		add_action(
			'set_object_terms',
			function ( int $objectId, array $terms, array $tt, string $taxonomy ): void {
				if ( LocationTaxonomy::NAME === $taxonomy ) {
					$this->reindexCoursesForProvider( $objectId );
				}
			},
			20,
			4
		);
	}

	public function onCourseSaved( int $postId, ?WP_Post $post = null ): void {
		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) ) {
			return;
		}

		$post = $post instanceof WP_Post ? $post : get_post( $postId );

		if ( ! $post instanceof WP_Post || CoursePostType::NAME !== $post->post_type ) {
			return;
		}

		if ( 'publish' === $post->post_status ) {
			$this->index( $postId );
		} else {
			$this->remove( $postId );
		}
	}

	public function onPostDeleted( int $postId, ?WP_Post $post = null ): void {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $postId );

		if ( CoursePostType::NAME === $type ) {
			$this->remove( $postId );

			return;
		}

		if ( 'provider' === $type ) {
			// Resolve the affected courses *before* reindexing: once the rows
			// are rewritten the association is gone.
			foreach ( $this->courseIdsForProvider( $postId ) as $courseId ) {
				$this->index( $courseId );
			}
		}
	}

	public function onPostTrashed( int $postId ): void {
		$this->onPostDeleted( $postId );
	}

	public function onPostUntrashed( int $postId ): void {
		if ( CoursePostType::NAME === get_post_type( $postId ) ) {
			$this->index( $postId );
		}
	}

	public function onProviderSaved( int $providerId ): void {
		if ( wp_is_post_revision( $providerId ) || wp_is_post_autosave( $providerId ) ) {
			return;
		}

		$this->reindexCoursesForProvider( $providerId );
	}

	/**
	 * Rebuild every lookup row for one course.
	 */
	public function index( int $courseId ): void {
		$post = get_post( $courseId );

		if ( ! $post instanceof WP_Post || CoursePostType::NAME !== $post->post_type ) {
			return;
		}

		$this->remove( $courseId );

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$providerIds = $this->providerIds( $courseId );

		$this->insertStartDates( $courseId, $post );
		$this->insertProviders( $courseId, $providerIds );
		$this->insertLocations( $courseId, $providerIds );
		$this->insertSearchRow( $courseId, $post );

		CachingOptions::flush();

		/**
		 * @see Hooks::COURSE_INDEXED
		 */
		do_action( Hooks::COURSE_INDEXED, $courseId );
	}

	/**
	 * Remove every lookup row for a course (or provider).
	 */
	public function remove( int $courseId ): void {
		foreach ( Schema::tables() as $table ) {
			$this->db->execute(
				$this->db->prepare(
					sprintf( 'DELETE FROM %s WHERE %s = %%d', $this->db->table( $table ), Schema::COURSE_COLUMN ),
					array( $courseId )
				)
			);
		}

		CachingOptions::flush();
	}

	/**
	 * Rebuild the whole index.
	 *
	 * @param int                           $batchSize Courses per query batch.
	 * @param callable(int, int): void|null $progress Called with (done, total).
	 *
	 * @return int Number of courses indexed.
	 */
	public function reindexAll( int $batchSize = 200, ?callable $progress = null ): int {
		$total   = 0;
		$page    = 1;
		$fetched = 0;

		do {
			$ids = get_posts(
				array(
					'post_type'              => CoursePostType::NAME,
					'post_status'            => 'publish',
					'posts_per_page'         => $batchSize,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $ids as $id ) {
				$this->index( (int) $id );
				++$total;

				if ( null !== $progress ) {
					$progress( $total, (int) $id );
				}
			}

			++$page;
			$fetched = count( $ids );
		} while ( $fetched === $batchSize );

		return $total;
	}

	public function reindexCoursesForProvider( int $providerId ): void {
		foreach ( $this->courseIdsForProvider( $providerId ) as $courseId ) {
			$this->index( $courseId );
		}
	}

	/**
	 * Courses referencing a provider.
	 *
	 * Two sources, answering different questions: the lookup table knows the
	 * *previous* state (courses to refresh after an unlink), the meta table the
	 * current one. The `LIKE` over serialised meta is exactly the pattern the
	 * lookup tables exist to keep off the read path — here it runs once per
	 * provider save, not once per page view.
	 *
	 * @return list<int>
	 */
	public function courseIdsForProvider( int $providerId ): array {
		$ids = array();

		if ( $this->db->tableExists( Schema::PROVIDERS ) ) {
			$ids = array_map(
				'intval',
				$this->db->column(
					$this->db->prepare(
						sprintf(
							'SELECT %s FROM %s WHERE provider_id = %%d',
							Schema::COURSE_COLUMN,
							$this->db->table( Schema::PROVIDERS )
						),
						array( $providerId )
					)
				)
			);
		}

		global $wpdb;

		$fromMeta = $this->db->column(
			$this->db->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND ( meta_value LIKE %s OR meta_value = %s )",
				array(
					FieldKeys::PROVIDERS,
					'%' . $wpdb->esc_like( sprintf( '"%d"', $providerId ) ) . '%',
					(string) $providerId,
				)
			)
		);

		return array_values( array_unique( array( ...$ids, ...array_map( 'intval', $fromMeta ) ) ) );
	}

	private function insertStartDates( int $courseId, WP_Post $post ): void {
		$dates = StartDateCollection::fromDelimitedString(
			(string) get_post_meta( $courseId, FieldKeys::START_DATES, true )
		);

		if ( $dates->isEmpty() ) {
			return;
		}

		$rows   = array();
		$values = array();

		foreach ( $dates as $date ) {
			/** @var StartDate $date */
			$rows[]   = '( %d, %d, %d, %d )';
			$values[] = $courseId;
			$values[] = $date->sortKey();
			$values[] = $date->month;
			$values[] = $date->year;
		}

		$this->db->execute(
			$this->db->prepare(
				sprintf(
					'INSERT IGNORE INTO %s ( course_id, sort_key, start_month, start_year ) VALUES %s',
					$this->db->table( Schema::START_DATES ),
					implode( ', ', $rows )
				),
				$values
			)
		);
	}

	/**
	 * @param list<int> $providerIds Provider post IDs.
	 */
	private function insertProviders( int $courseId, array $providerIds ): void {
		$rows   = array();
		$values = array();

		foreach ( $providerIds as $providerId ) {
			$provider = get_post( $providerId );

			if ( ! $provider instanceof WP_Post || 'publish' !== $provider->post_status ) {
				continue;
			}

			$rows[]   = '( %d, %d, %s )';
			$values[] = $courseId;
			$values[] = $providerId;
			$values[] = $provider->post_name;
		}

		if ( array() === $rows ) {
			return;
		}

		$this->db->execute(
			$this->db->prepare(
				sprintf(
					'INSERT IGNORE INTO %s ( course_id, provider_id, provider_slug ) VALUES %s',
					$this->db->table( Schema::PROVIDERS ),
					implode( ', ', $rows )
				),
				$values
			)
		);
	}

	/**
	 * @param list<int> $providerIds Provider post IDs.
	 */
	private function insertLocations( int $courseId, array $providerIds ): void {
		$rows   = array();
		$values = array();

		foreach ( $providerIds as $providerId ) {
			$terms = get_the_terms( $providerId, LocationTaxonomy::NAME );

			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}

				$rows[]   = '( %d, %d, %s, %d )';
				$values[] = $courseId;
				$values[] = $term->term_id;
				$values[] = $term->slug;
				$values[] = $providerId;
			}
		}

		if ( array() === $rows ) {
			return;
		}

		$this->db->execute(
			$this->db->prepare(
				sprintf(
					'INSERT IGNORE INTO %s ( course_id, location_id, location_slug, provider_id ) VALUES %s',
					$this->db->table( Schema::LOCATIONS ),
					implode( ', ', $rows )
				),
				$values
			)
		);
	}

	private function insertSearchRow( int $courseId, WP_Post $post ): void {
		$this->db->execute(
			$this->db->prepare(
				sprintf(
					'REPLACE INTO %s ( course_id, name, short_description, long_description, updated_at )
					 VALUES ( %%d, %%s, %%s, %%s, %%s )',
					$this->db->table( Schema::SEARCH_INDEX )
				),
				array(
					$courseId,
					$post->post_title,
					wp_strip_all_tags( (string) get_post_meta( $courseId, FieldKeys::SHORT_DESCRIPTION, true ) ),
					wp_strip_all_tags( $post->post_content ),
					current_time( 'mysql', true ),
				)
			)
		);
	}

	/**
	 * @return list<int>
	 */
	private function providerIds( int $courseId ): array {
		$raw = get_post_meta( $courseId, FieldKeys::PROVIDERS, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = explode( ',', $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();

		foreach ( $raw as $value ) {
			if ( is_object( $value ) && isset( $value->ID ) ) {
				$value = $value->ID;
			}

			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$ids[] = (int) $value;
			}
		}

		return array_values( array_unique( $ids ) );
	}
}
