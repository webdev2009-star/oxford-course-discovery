<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Support\Doubles;

use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Filter\ProvidesOptions;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * A test filter that offers choices but does not constrain anything.
 */
final class SpyOptionsFilter extends SpyFilter implements ProvidesOptions {

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		return FilterOptionCollection::fromPairs(
			array(
				'one' => 'One',
				'two' => 'Two',
			)
		);
	}
}
