<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterValue;

/**
 * Immutable map of filter key => selected value.
 *
 * @implements \IteratorAggregate<string, FilterValue>
 */
final class FilterValues implements \IteratorAggregate, \Countable {

	/**
	 * @param array<string, FilterValue> $values Keyed by filter key.
	 */
	private function __construct( private readonly array $values ) {
	}

	/**
	 * @param array<string, FilterValue> $values Keyed by filter key.
	 */
	public static function fromArray( array $values ): self {
		$clean = array();

		foreach ( $values as $key => $value ) {
			if ( ! $value instanceof FilterValue || $value->isEmpty() ) {
				continue;
			}

			$clean[ FilterKey::fromString( (string) $key )->value ] = $value;
		}

		return new self( $clean );
	}

	public static function empty(): self {
		return new self( array() );
	}

	public function with( FilterKey $key, FilterValue $value ): self {
		$values = $this->values;

		if ( $value->isEmpty() ) {
			unset( $values[ $key->value ] );
		} else {
			$values[ $key->value ] = $value;
		}

		return new self( $values );
	}

	public function without( FilterKey $key ): self {
		$values = $this->values;
		unset( $values[ $key->value ] );

		return new self( $values );
	}

	public function get( FilterKey $key ): FilterValue {
		return $this->values[ $key->value ] ?? FilterValue::none();
	}

	public function has( FilterKey $key ): bool {
		return isset( $this->values[ $key->value ] );
	}

	public function isEmpty(): bool {
		return array() === $this->values;
	}

	public function count(): int {
		return count( $this->values );
	}

	/**
	 * @return array<string, list<string>> Query string friendly shape.
	 */
	public function toArray(): array {
		return array_map( static fn( FilterValue $value ): array => $value->toArray(), $this->values );
	}

	/**
	 * @return \Traversable<string, FilterValue>
	 */
	public function getIterator(): \Traversable {
		return new \ArrayIterator( $this->values );
	}
}
