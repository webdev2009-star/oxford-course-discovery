<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

/**
 * A single price point — the default implementation.
 */
final readonly class FixedPrice implements Price {

	private function __construct( private Money $amount ) {
	}

	public static function of( Money $amount ): self {
		return new self( $amount );
	}

	public static function fromDecimal( float|int|string $amount, string $currency = Money::DEFAULT_CURRENCY ): self {
		return new self( Money::fromDecimal( $amount, $currency ) );
	}

	public function from(): Money {
		return $this->amount;
	}

	public function to(): Money {
		return $this->amount;
	}

	public function format(): string {
		return $this->amount->isZero()
			? __( 'Free', 'oxford-course-discovery' )
			: $this->amount->format();
	}
}
