<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

/**
 * The selection a user has made for one filter.
 *
 * Always a list, even for single value controls: "one selected value" is the
 * degenerate case of "many", and modelling it that way removes a whole class of
 * `is_array()` branching from filters, templates and the query compiler.
 * Multiple values within one filter are OR'd; separate filters are AND'd.
 */
final readonly class FilterValue {

	/**
	 * @param list<string> $values Raw, trimmed, de-duplicated values.
	 */
	private function __construct( public array $values ) {
	}

	/**
	 * @param iterable<mixed> $values Candidate values.
	 */
	public static function fromIterable( iterable $values ): self {
		$clean = array();

		foreach ( $values as $value ) {
			if ( is_array( $value ) || is_object( $value ) || null === $value ) {
				continue;
			}

			$string = trim( (string) $value );

			if ( '' === $string || in_array( $string, $clean, true ) ) {
				continue;
			}

			$clean[] = $string;
		}

		return new self( $clean );
	}

	public static function fromScalar( mixed $value ): self {
		return self::fromIterable( array( $value ) );
	}

	/**
	 * Normalise anything that arrived on the request: a scalar, an array, or a
	 * comma separated string (which is what a combobox posts).
	 */
	public static function fromRequest( mixed $raw ): self {
		if ( is_array( $raw ) ) {
			return self::fromIterable( $raw );
		}

		if ( is_string( $raw ) && str_contains( $raw, ',' ) ) {
			return self::fromIterable( explode( ',', $raw ) );
		}

		return self::fromScalar( $raw );
	}

	public static function none(): self {
		return new self( array() );
	}

	public function isEmpty(): bool {
		return array() === $this->values;
	}

	public function first(): ?string {
		return $this->values[0] ?? null;
	}

	public function contains( string $value ): bool {
		return in_array( $value, $this->values, true );
	}

	/**
	 * @return list<int> Positive integers only.
	 */
	public function toInts(): array {
		$ints = array();

		foreach ( $this->values as $value ) {
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$ints[] = (int) $value;
			}
		}

		return $ints;
	}

	/**
	 * @return list<string>
	 */
	public function toArray(): array {
		return $this->values;
	}

	public function count(): int {
		return count( $this->values );
	}
}
