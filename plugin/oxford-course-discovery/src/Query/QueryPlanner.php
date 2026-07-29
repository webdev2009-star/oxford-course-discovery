<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query;

use Oxford\CourseDiscovery\Filter\ContributesQuery;
use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Support\Hooks;

/**
 * Folds the active filters into a single {@see QueryPlan}.
 *
 * This is where the brief's grouping rule is implemented, once:
 *
 *   (provider = uosd OR provider = dmu)
 *   AND (location = india OR location = china)
 *   AND (category = graphic-design)
 *
 * Each filter contributes at most one constraint holding all of its selected
 * values (the OR); the compiler joins constraints with AND. A new filter
 * inherits both halves by implementing {@see ContributesQuery} — there is no
 * combining logic to duplicate or get wrong.
 */
final class QueryPlanner {

	public function __construct( private readonly FilterRegistry $filters ) {
	}

	public function plan( SearchCriteria $criteria ): QueryPlan {
		$plan = QueryPlan::forCourses( $criteria->ordering, $criteria->pagination );

		foreach ( $this->filters->providing( ContributesQuery::class ) as $filter ) {
			$value = $criteria->valueFor( $filter->key() );

			if ( $value->isEmpty() ) {
				continue;
			}

			$plan = $filter->contribute( $value, $plan, $criteria );
		}

		/**
		 * @see Hooks::QUERY_PLAN
		 */
		$filtered = apply_filters( Hooks::QUERY_PLAN, $plan, $criteria );

		return $filtered instanceof QueryPlan ? $filtered : $plan;
	}
}
