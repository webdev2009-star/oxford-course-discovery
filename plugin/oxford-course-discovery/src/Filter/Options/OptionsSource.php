<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Options;

use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Where a filter's choices come from.
 *
 * The seam that keeps filters small: a filter holds a source, the source knows
 * where the data lives. Caching, static test data and contextual faceting are
 * decorators over this one method rather than filter subclasses — see
 * {@see CachingOptions}.
 */
interface OptionsSource {

	public function options( SearchCriteria $criteria ): FilterOptionCollection;

	/**
	 * Cache identity for this source's current output. Sources that vary with
	 * the criteria should fold the relevant part of it into the key.
	 */
	public function cacheKey( SearchCriteria $criteria ): string;
}
