<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Capability: this filter can rewrite the whole search, not just its own slice.
 *
 * Used sparingly — the keyword filter switches the default ordering to
 * relevance when a query is present. Transformers run before query planning,
 * in filter priority order, each receiving the output of the last.
 */
interface TransformsCriteria {

	public function transform( SearchCriteria $criteria ): SearchCriteria;
}
