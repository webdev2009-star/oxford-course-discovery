<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Frontend;

use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Search\SearchResults;

/**
 * Everything a course finder view needs, resolved once.
 */
final readonly class FinderResult {

	/**
	 * @param list<FilterView>      $filters   Filters with options and selection.
	 * @param array<string, string> $orderings Ordering key => label.
	 */
	public function __construct(
		public SearchCriteria $criteria,
		public SearchResults $results,
		public array $filters,
		public array $orderings
	) {
	}

	/**
	 * Human summary of the result count, for the live region.
	 */
	public function summary(): string {
		if ( $this->results->isEmpty() ) {
			return __( 'No courses match your search.', 'oxford-course-discovery' );
		}

		return sprintf(
			/* translators: 1: first result index, 2: last result index, 3: total results. */
			_n(
				'Showing %1$d–%2$d of %3$d course',
				'Showing %1$d–%2$d of %3$d courses',
				$this->results->total,
				'oxford-course-discovery'
			),
			$this->results->firstResultIndex(),
			$this->results->lastResultIndex(),
			$this->results->total
		);
	}

	/**
	 * @return array<string, mixed> JSON payload for the REST endpoint.
	 */
	public function toArray(): array {
		return array(
			...$this->results->toArray(),
			'summary'  => $this->summary(),
			'criteria' => $this->criteria->toQueryVars(),
			'filters'  => array_map(
				static fn( FilterView $view ): array => array(
					'key'      => $view->key(),
					'label'    => $view->label(),
					'control'  => $view->control()->value,
					'selected' => $view->selected->toArray(),
					'options'  => $view->options->toArrays(),
				),
				$this->filters
			),
		);
	}
}
