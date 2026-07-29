<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Options;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;

/**
 * Providers that actually have published courses, with course counts.
 *
 * Read from the lookup table in one grouped query. The alternative — load
 * every provider post, then count courses per provider — is N+1 queries and
 * would still need the serialised ACF relationship values unpicked in PHP.
 */
final class ProviderOptions implements OptionsSource {

	public function __construct( private readonly DatabaseGateway $db ) {
	}

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		if ( ! $this->db->tableExists( Schema::PROVIDERS ) ) {
			return $this->fallback();
		}

		$sql = sprintf(
			'SELECT p.post_name AS value, p.post_title AS label, COUNT( DISTINCT l.%s ) AS total
			 FROM %s l
			 INNER JOIN %s p ON p.ID = l.provider_id
			 WHERE p.post_status = %%s
			 GROUP BY p.ID, p.post_name, p.post_title
			 ORDER BY p.post_title ASC',
			Schema::COURSE_COLUMN,
			$this->db->table( Schema::PROVIDERS ),
			$this->db->postsTable()
		);

		$rows = $this->db->results( $this->db->prepare( $sql, array( 'publish' ) ) );

		if ( array() === $rows ) {
			return $this->fallback();
		}

		return FilterOptionCollection::of(
			array_map(
				static fn( array $row ): FilterOption => FilterOption::create(
					(string) ( $row['value'] ?? '' ),
					(string) ( $row['label'] ?? '' ),
					(int) ( $row['total'] ?? 0 )
				),
				array_values( array_filter( $rows, static fn( array $row ): bool => '' !== (string) ( $row['value'] ?? '' ) ) )
			)
		);
	}

	public function cacheKey( SearchCriteria $criteria ): string {
		return 'providers';
	}

	/**
	 * Before the first index run there is nothing to group; show every provider
	 * so the UI is never mysteriously empty.
	 */
	private function fallback(): FilterOptionCollection {
		$providers = get_posts(
			array(
				'post_type'              => ProviderPostType::NAME,
				'post_status'            => 'publish',
				// Bounded deliberately: a fallback for the pre-index state only.
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts
				'numberposts'            => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return FilterOptionCollection::of(
			array_map(
				static fn( \WP_Post $post ): FilterOption => FilterOption::create(
					$post->post_name,
					$post->post_title
				),
				$providers
			)
		);
	}
}
