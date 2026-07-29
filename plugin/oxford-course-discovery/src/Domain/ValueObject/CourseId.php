<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Identity of a course. Wrapping the WordPress post ID keeps "any old integer"
 * out of the domain and gives us one place to validate identity.
 */
final readonly class CourseId {

	/**
	 * @param positive-int $value Post ID.
	 *
	 * @throws InvalidArgumentException When the ID is not positive.
	 */
	private function __construct( public int $value ) {
		if ( $value < 1 ) {
			throw new InvalidArgumentException( 'A course ID must be a positive integer.' );
		}
	}

	public static function fromInt( int $value ): self {
		return new self( $value );
	}

	/**
	 * @return self|null Null when the value cannot be an identity.
	 */
	public static function tryFrom( mixed $value ): ?self {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$int = (int) $value;

		return $int > 0 ? new self( $int ) : null;
	}

	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	public function toInt(): int {
		return $this->value;
	}
}
