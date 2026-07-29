<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Frontend;

use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Filter\ProvidesOptions;
use Oxford\CourseDiscovery\Query\CourseRepository;
use Oxford\CourseDiscovery\Search\CriteriaFactory;
use Oxford\CourseDiscovery\Search\Orderings;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Search\SearchResults;
use Oxford\CourseDiscovery\Support\Hooks;

/**
 * Application service behind the front end: request in, view model out.
 *
 * Used by both the shortcode (server rendered, works without JavaScript) and
 * the REST controller (used by the enhanced UI), so the two can never drift.
 */
final class CourseFinder {

	public function __construct(
		private readonly FilterRegistry $filters,
		private readonly CriteriaFactory $criteriaFactory,
		private readonly CourseRepository $repository
	) {
	}

	/**
	 * @param array<string, mixed> $request Request parameters.
	 */
	public function find( array $request ): FinderResult {
		$criteria = $this->criteriaFactory->fromRequest( $request );

		return new FinderResult(
			$criteria,
			$this->repository->search( $criteria ),
			$this->filterViews( $criteria ),
			Orderings::available()
		);
	}

	/**
	 * Filter views for the current search, options included.
	 *
	 * @return list<FilterView>
	 */
	public function filterViews( SearchCriteria $criteria ): array {
		$views = array();

		foreach ( $this->filters->all() as $filter ) {
			$options = $filter instanceof ProvidesOptions
				? $filter->options( $criteria )
				: \Oxford\CourseDiscovery\Filter\FilterOptionCollection::empty();

			/**
			 * @see Hooks::FILTER_OPTIONS
			 */
			$filtered = apply_filters( Hooks::FILTER_OPTIONS, $options, $filter->key(), $criteria );

			$views[] = new FilterView(
				$filter,
				$filtered instanceof \Oxford\CourseDiscovery\Filter\FilterOptionCollection ? $filtered : $options,
				$criteria->valueFor( $filter->key() )
			);
		}

		return $views;
	}

	public function search( SearchCriteria $criteria ): SearchResults {
		return $this->repository->search( $criteria );
	}
}
