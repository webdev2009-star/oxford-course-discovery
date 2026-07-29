<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

use InvalidArgumentException;

/**
 * One selectable choice within a filter.
 *
 * Supports nesting so a hierarchical taxonomy (course categories) renders as a
 * tree without a second option type.
 */
final readonly class FilterOption {

	/**
	 * @param non-empty-string            $value    Submitted value.
	 * @param string                      $label    Display label.
	 * @param int|null                    $count    Matching courses, null when not computed.
	 * @param FilterOptionCollection|null $children Nested options.
	 *
	 * @throws InvalidArgumentException When the option has no value.
	 */
	private function __construct(
		public string $value,
		public string $label,
		public ?int $count = null,
		public ?FilterOptionCollection $children = null
	) {
		if ( '' === $value ) {
			throw new InvalidArgumentException( 'A filter option needs a value.' );
		}
	}

	public static function create(
		string $value,
		string $label,
		?int $count = null,
		?FilterOptionCollection $children = null
	): self {
		return new self( $value, '' === $label ? $value : $label, $count, $children );
	}

	public function withChildren( FilterOptionCollection $children ): self {
		return new self( $this->value, $this->label, $this->count, $children );
	}

	public function withCount( ?int $count ): self {
		return new self( $this->value, $this->label, $count, $this->children );
	}

	public function hasChildren(): bool {
		return $this->children instanceof FilterOptionCollection && ! $this->children->isEmpty();
	}

	public function children(): FilterOptionCollection {
		return $this->children ?? FilterOptionCollection::empty();
	}

	/**
	 * Label including the result count, when one is known.
	 */
	public function labelWithCount(): string {
		return null === $this->count ? $this->label : sprintf( '%s (%d)', $this->label, $this->count );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'value'    => $this->value,
			'label'    => $this->label,
			'count'    => $this->count,
			'children' => $this->hasChildren() ? $this->children()->toArrays() : array(),
		);
	}
}
