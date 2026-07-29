<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Options;

use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use WP_Term;

/**
 * Options read from a taxonomy, preserving hierarchy.
 */
final class TermOptions implements OptionsSource {

	public function __construct(
		private readonly string $taxonomy,
		private readonly bool $hideEmpty = true,
		private readonly bool $withCounts = true
	) {
	}

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => $this->hideEmpty,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return FilterOptionCollection::empty();
		}

		/** @var array<int, list<WP_Term>> $byParent */
		$byParent = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$byParent[ $term->parent ][] = $term;
		}

		return $this->build( $byParent, 0 );
	}

	public function cacheKey( SearchCriteria $criteria ): string {
		return sprintf( 'terms:%s:%d:%d', $this->taxonomy, (int) $this->hideEmpty, (int) $this->withCounts );
	}

	/**
	 * @param array<int, list<WP_Term>> $byParent Terms grouped by parent ID.
	 */
	private function build( array $byParent, int $parentId ): FilterOptionCollection {
		$options = array();

		foreach ( $byParent[ $parentId ] ?? array() as $term ) {
			$children = $this->build( $byParent, $term->term_id );

			$options[] = FilterOption::create(
				$term->slug,
				$term->name,
				$this->withCounts ? (int) $term->count : null,
				$children->isEmpty() ? null : $children
			);
		}

		return FilterOptionCollection::of( $options );
	}
}
