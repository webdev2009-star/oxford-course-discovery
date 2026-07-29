<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Support\Doubles;

use Oxford\CourseDiscovery\Filter\ContributesQuery;
use Oxford\CourseDiscovery\Filter\FilterValue;
use Oxford\CourseDiscovery\Query\Constraint\MetaConstraint;
use Oxford\CourseDiscovery\Query\QueryPlan;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * A test filter that narrows the query — the minimum a third party has to
 * write to add a working filter.
 */
final class SpyQueryFilter extends SpyFilter implements ContributesQuery {

	public function contribute( FilterValue $value, QueryPlan $plan, SearchCriteria $criteria ): QueryPlan {
		++$this->contributions;

		return $plan->withMetaConstraint(
			MetaConstraint::anyOf( 'spy_' . $this->key()->value, $value->toArray() )
		);
	}
}
