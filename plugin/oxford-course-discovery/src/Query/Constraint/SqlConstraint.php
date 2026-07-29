<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query\Constraint;

use Oxford\CourseDiscovery\Database\DatabaseGateway;

/**
 * A constraint expressed as a SQL fragment appended to the `WHERE` clause.
 *
 * Fragments are self-contained boolean expressions and are combined with `AND`
 * by the compiler — that is the "top level filters are AND'd" rule, enforced
 * structurally rather than by convention.
 */
interface SqlConstraint {

	/**
	 * Stable identity, used to de-duplicate constraints and to key caches.
	 */
	public function identity(): string;

	/**
	 * @param DatabaseGateway $db Gateway used for table names and escaping.
	 *
	 * @return string A complete, safely prepared boolean expression. Return an
	 *                empty string to contribute nothing.
	 */
	public function toSql( DatabaseGateway $db ): string;
}
