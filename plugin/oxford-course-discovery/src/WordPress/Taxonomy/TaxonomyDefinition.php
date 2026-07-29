<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Taxonomy;

interface TaxonomyDefinition {

	public function name(): string;

	/**
	 * @return list<string> Post types this taxonomy applies to.
	 */
	public function objectTypes(): array;

	/**
	 * @return array<string, mixed> Arguments for `register_taxonomy()`.
	 */
	public function args(): array;
}
