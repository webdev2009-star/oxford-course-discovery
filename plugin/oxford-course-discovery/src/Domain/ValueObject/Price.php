<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

/**
 * How much a course costs.
 *
 * The brief calls for a single numeric price but explicitly anticipates ranges
 * and multiple price points. Modelling price as an interface means "from £900"
 * and "£900 – £1,400" are new implementations rather than edits to existing
 * code: the rest of the system only ever asks for {@see self::from()},
 * {@see self::to()} and {@see self::format()}.
 */
interface Price {

	/**
	 * Lowest amount a student could pay. Used for sorting and range filtering.
	 */
	public function from(): Money;

	/**
	 * Highest amount a student could pay. Equal to {@see self::from()} for a
	 * single price point.
	 */
	public function to(): Money;

	/**
	 * Display string, already localised for the current site.
	 */
	public function format(): string;
}
