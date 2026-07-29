<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

/**
 * Capability: this filter validates and canonicalises its own input.
 *
 * Runs once, at the request boundary, before anything else sees the value.
 * Filters that accept structured values (a `{month}-{year}` intake, a numeric
 * ID) implement this so that malformed input is dropped at the edge instead of
 * reaching the query compiler.
 */
interface NormalisesValue {

	/**
	 * @param FilterValue $value Raw value from the request.
	 *
	 * @return FilterValue Canonical value; return {@see FilterValue::none()} to
	 *                     discard the selection entirely.
	 */
	public function normalise( FilterValue $value ): FilterValue;
}
