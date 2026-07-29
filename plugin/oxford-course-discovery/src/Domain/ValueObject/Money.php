<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

use InvalidArgumentException;

/**
 * A monetary amount held in minor units to avoid float arithmetic.
 */
final readonly class Money {

	public const DEFAULT_CURRENCY = 'GBP';

	/**
	 * @param int    $minorUnits Amount in minor units (pence).
	 * @param string $currency   ISO-4217 alphabetic code.
	 *
	 * @throws InvalidArgumentException When the amount or currency is invalid.
	 */
	private function __construct( public int $minorUnits, public string $currency ) {
		if ( $minorUnits < 0 ) {
			throw new InvalidArgumentException( 'A monetary amount cannot be negative.' );
		}

		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw new InvalidArgumentException( 'Currency must be a three letter ISO-4217 code.' );
		}
	}

	public static function fromMinorUnits( int $minorUnits, string $currency = self::DEFAULT_CURRENCY ): self {
		return new self( $minorUnits, strtoupper( $currency ) );
	}

	public static function fromDecimal( float|int|string $amount, string $currency = self::DEFAULT_CURRENCY ): self {
		if ( ! is_numeric( $amount ) ) {
			throw new InvalidArgumentException( 'A monetary amount must be numeric.' );
		}

		return new self( (int) round( (float) $amount * 100 ), strtoupper( $currency ) );
	}

	public static function zero( string $currency = self::DEFAULT_CURRENCY ): self {
		return new self( 0, strtoupper( $currency ) );
	}

	public function toDecimal(): float {
		return $this->minorUnits / 100;
	}

	public function isZero(): bool {
		return 0 === $this->minorUnits;
	}

	public function equals( self $other ): bool {
		return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
	}

	public function lessThan( self $other ): bool {
		$this->assertSameCurrency( $other );

		return $this->minorUnits < $other->minorUnits;
	}

	public function format(): string {
		$symbols = array(
			'GBP' => '£',
			'EUR' => '€',
			'USD' => '$',
		);

		$symbol   = $symbols[ $this->currency ] ?? $this->currency . ' ';
		$decimals = 0 === $this->minorUnits % 100 ? 0 : 2;

		return $symbol . number_format( $this->toDecimal(), $decimals );
	}

	private function assertSameCurrency( self $other ): void {
		if ( $this->currency !== $other->currency ) {
			throw new InvalidArgumentException( 'Cannot compare amounts in different currencies.' );
		}
	}
}
