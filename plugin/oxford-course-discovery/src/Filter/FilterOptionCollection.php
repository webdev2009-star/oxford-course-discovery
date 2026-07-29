<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

use Oxford\CourseDiscovery\Support\TypedCollection;

/**
 * @extends TypedCollection<FilterOption>
 */
final class FilterOptionCollection extends TypedCollection {

	protected static function itemType(): string {
		return FilterOption::class;
	}

	/**
	 * @param array<string, string> $pairs Value => label.
	 */
	public static function fromPairs( array $pairs ): self {
		$options = array();

		foreach ( $pairs as $value => $label ) {
			$options[] = FilterOption::create( (string) $value, $label );
		}

		return new self( $options );
	}

	public function find( string $value ): ?FilterOption {
		foreach ( $this->items as $option ) {
			if ( $option->value === $value ) {
				return $option;
			}

			if ( $option->hasChildren() ) {
				$nested = $option->children()->find( $value );

				if ( $nested instanceof FilterOption ) {
					return $nested;
				}
			}
		}

		return null;
	}

	/**
	 * All option values, including nested ones. Used to validate a selection
	 * against what is actually offered.
	 *
	 * @return list<string>
	 */
	public function values(): array {
		$values = array();

		foreach ( $this->items as $option ) {
			$values[] = $option->value;

			foreach ( $option->children()->values() as $nested ) {
				$values[] = $nested;
			}
		}

		return $values;
	}

	/**
	 * Flattened for JSON and for the options cache. Kept distinct from the
	 * inherited `toArray()`, which returns the options themselves.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function toArrays(): array {
		return $this->map( static fn( FilterOption $option ): array => $option->toArray() );
	}
}
