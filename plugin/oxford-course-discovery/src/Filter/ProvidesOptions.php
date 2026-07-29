<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Capability: this filter can enumerate the choices it offers.
 *
 * Options are requested with the current criteria so an implementation may
 * narrow them (contextual facets) — the default filters return the full set,
 * which keeps a selection from disappearing after it is made.
 */
interface ProvidesOptions {

	/**
	 * @param SearchCriteria $criteria The search currently being performed.
	 *
	 * @return FilterOptionCollection Ordered options, possibly hierarchical.
	 */
	public function options( SearchCriteria $criteria ): FilterOptionCollection;
}
