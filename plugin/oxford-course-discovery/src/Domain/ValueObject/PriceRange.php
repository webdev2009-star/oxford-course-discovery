<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

use InvalidArgumentException;

/**
 * A price band, e.g. courses whose cost varies by campus or intake.
 *
 * Not used by the default course mapper, but shipped to prove the abstraction:
 * an integration can return this from the {@see \Oxford\CourseDiscovery\Support\Hooks::COURSE_PRICE}
 * filter and every consumer keeps working.
 */
final readonly class PriceRange implements Price {

	private function __construct( private Money $low, private Money $high ) {
		if ( $high->lessThan( $low ) ) {
			throw new InvalidArgumentException( 'A price range cannot end below where it starts.' );
		}
	}

	public static function between( Money $low, Money $high ): self {
		return new self( $low, $high );
	}

	public function from(): Money {
		return $this->low;
	}

	public function to(): Money {
		return $this->high;
	}

	public function format(): string {
		if ( $this->low->equals( $this->high ) ) {
			return $this->low->format();
		}

		return sprintf(
			/* translators: 1: lowest price, 2: highest price. */
			__( '%1$s – %2$s', 'oxford-course-discovery' ),
			$this->low->format(),
			$this->high->format()
		);
	}
}
