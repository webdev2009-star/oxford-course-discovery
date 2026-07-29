<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

/**
 * The identity half of a filter, as a value object.
 *
 * Filters compose one of these instead of extending an abstract base: identity
 * is data, not behaviour, and keeping it out of the inheritance chain means a
 * filter's only superclass-shaped dependency disappears. Relabelling or
 * reordering a built-in filter is then `->withLabel()`, not a subclass.
 */
final readonly class FilterDefinition {

	private function __construct(
		public FilterKey $key,
		public string $label,
		public FilterControl $control,
		public int $priority
	) {
	}

	public static function create(
		string $key,
		string $label,
		FilterControl $control,
		int $priority = 50
	): self {
		return new self( FilterKey::fromString( $key ), $label, $control, $priority );
	}

	public function withLabel( string $label ): self {
		return new self( $this->key, $label, $this->control, $this->priority );
	}

	public function withControl( FilterControl $control ): self {
		return new self( $this->key, $this->label, $control, $this->priority );
	}

	public function withPriority( int $priority ): self {
		return new self( $this->key, $this->label, $this->control, $priority );
	}
}
