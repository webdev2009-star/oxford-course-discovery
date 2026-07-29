<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Collection;

use Oxford\CourseDiscovery\Domain\Course;
use Oxford\CourseDiscovery\Support\TypedCollection;

/**
 * @extends TypedCollection<Course>
 */
final class CourseCollection extends TypedCollection {

	protected static function itemType(): string {
		return Course::class;
	}

	/**
	 * @return list<int>
	 */
	public function ids(): array {
		return $this->map( static fn( Course $course ): int => $course->id->toInt() );
	}

	/**
	 * Flattened for JSON. Named separately from the inherited `toArray()`,
	 * which returns the courses themselves — overriding it with a different
	 * element type would break substitutability.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function toArrays(): array {
		return $this->map( static fn( Course $course ): array => $course->toArray() );
	}
}
