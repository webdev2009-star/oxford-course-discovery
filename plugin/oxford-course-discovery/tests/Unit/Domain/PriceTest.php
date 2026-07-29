<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Domain;

use InvalidArgumentException;
use Oxford\CourseDiscovery\Domain\ValueObject\FixedPrice;
use Oxford\CourseDiscovery\Domain\ValueObject\Money;
use Oxford\CourseDiscovery\Domain\ValueObject\Price;
use Oxford\CourseDiscovery\Domain\ValueObject\PriceRange;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Oxford\CourseDiscovery\Domain\ValueObject\Money
 * @covers \Oxford\CourseDiscovery\Domain\ValueObject\FixedPrice
 * @covers \Oxford\CourseDiscovery\Domain\ValueObject\PriceRange
 */
final class PriceTest extends TestCase {

	public function test_money_is_stored_in_minor_units(): void {
		$money = Money::fromDecimal( 12.34 );

		self::assertSame( 1234, $money->minorUnits );
		self::assertSame( 12.34, $money->toDecimal() );
	}

	public function test_money_avoids_float_drift(): void {
		// 0.1 + 0.2 in floats is the classic failure; minor units cannot drift.
		$total = Money::fromMinorUnits( 10 )->minorUnits + Money::fromMinorUnits( 20 )->minorUnits;

		self::assertSame( 30, $total );
	}

	public function test_money_rejects_negative_amounts(): void {
		$this->expectException( InvalidArgumentException::class );

		Money::fromMinorUnits( -1 );
	}

	public function test_money_rejects_unknown_currency_format(): void {
		$this->expectException( InvalidArgumentException::class );

		Money::fromDecimal( 10, 'pounds' );
	}

	public function test_whole_amounts_format_without_decimals(): void {
		self::assertSame( '£9,500', Money::fromDecimal( 9500 )->format() );
		self::assertSame( '£9,500.50', Money::fromDecimal( 9500.5 )->format() );
	}

	public function test_fixed_price_exposes_the_same_bound_twice(): void {
		$price = FixedPrice::fromDecimal( 9500 );

		self::assertInstanceOf( Price::class, $price );
		self::assertTrue( $price->from()->equals( $price->to() ) );
	}

	public function test_free_courses_read_as_free(): void {
		self::assertSame( 'Free', FixedPrice::fromDecimal( 0 )->format() );
	}

	/**
	 * The extensibility claim in the brief: a range is a drop-in replacement
	 * for a single price everywhere the interface is consumed.
	 */
	public function test_a_range_satisfies_the_same_contract(): void {
		$range = PriceRange::between( Money::fromDecimal( 9000 ), Money::fromDecimal( 14000 ) );

		self::assertInstanceOf( Price::class, $range );
		self::assertSame( 9000.0, $range->from()->toDecimal() );
		self::assertSame( 14000.0, $range->to()->toDecimal() );
		self::assertStringContainsString( '£9,000', $range->format() );
		self::assertStringContainsString( '£14,000', $range->format() );
	}

	public function test_a_range_cannot_end_before_it_starts(): void {
		$this->expectException( InvalidArgumentException::class );

		PriceRange::between( Money::fromDecimal( 14000 ), Money::fromDecimal( 9000 ) );
	}
}
