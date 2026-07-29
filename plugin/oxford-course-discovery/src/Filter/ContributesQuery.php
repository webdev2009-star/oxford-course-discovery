<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

use Oxford\CourseDiscovery\Query\QueryPlan;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Capability: this filter narrows the result set.
 *
 * A contributor never touches SQL or `WP_Query` directly. It receives the plan
 * built so far and returns a new one with its constraint added; the plan is
 * later compiled once, in one place. That is what guarantees the grouping rule
 * from the brief — every filter contributes one constraint, constraints are
 * AND'd together, and the values inside a single constraint are OR'd — without
 * each filter having to re-implement it.
 */
interface ContributesQuery {

	/**
	 * @param FilterValue    $value    The user's selection; never empty.
	 * @param QueryPlan      $plan     Plan built so far.
	 * @param SearchCriteria $criteria Full criteria, for cross-filter decisions.
	 *
	 * @return QueryPlan New plan; implementations must not mutate the input.
	 */
	public function contribute( FilterValue $value, QueryPlan $plan, SearchCriteria $criteria ): QueryPlan;
}
