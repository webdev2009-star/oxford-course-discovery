<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Domain;

use InvalidArgumentException;
use Oxford\CourseDiscovery\Domain\Collection\StartDateCollection;
use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;
use PHPUnit\Framework\TestCase;

/**
 * Start dates are the highest risk value object in the system: they are typed
 * by hand as free text, must sort chronologically in a dropdown, and are the
 * one filter value that is transformed (string -> integer key) before it
 * reaches SQL.
 *
 * @covers \Oxford\CourseDiscovery\Domain\ValueObject\StartDate
 * @covers \Oxford\CourseDiscovery\Domain\Collection\StartDateCollection
 */
final class StartDateTest extends TestCase {

	/**
	 * @dataProvider provideParseableValues
	 */
	public function test_it_parses_editor_input( string $input, int $month, int $year ): void {
		$date = StartDate::fromString( $input );

		self::assertSame( $month, $date->month );
		self::assertSame( $year, $date->year );
	}

	/**
	 * @return array<string, array{string, int, int}>
	 */
	public static function provideParseableValues(): array {
		return array(
			'zero padded'    => array( '09-2026', 9, 2026 ),
			'not padded'     => array( '9-2026', 9, 2026 ),
			'space padded'   => array( '  01-2027 ', 1, 2027 ),
			'slash'          => array( '01/2027', 1, 2027 ),
			'short month'    => array( 'Sep-2026', 9, 2026 ),
			'long month'     => array( 'September 2026', 9, 2026 ),
			'reversed order' => array( '2026-09', 9, 2026 ),
		);
	}

	/**
	 * @dataProvider provideInvalidValues
	 */
	public function test_it_rejects_invalid_input( string $input ): void {
		self::assertNull( StartDate::tryFromString( $input ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provideInvalidValues(): array {
		return array(
			'empty'          => array( '' ),
			'month only'     => array( '09' ),
			'bad month'      => array( '13-2026' ),
			'zero month'     => array( '00-2026' ),
			'two digit year' => array( '09-26' ),
			'words'          => array( 'next autumn' ),
			'sql injection'  => array( "09-2026'; DROP TABLE wp_posts; --" ),
		);
	}

	public function test_it_throws_when_strict_parsing_fails(): void {
		$this->expectException( InvalidArgumentException::class );

		StartDate::fromString( 'not a date' );
	}

	public function test_sort_key_is_chronologically_ordered(): void {
		$september2026 = StartDate::fromString( '09-2026' );
		$january2027   = StartDate::fromString( '01-2027' );

		// The exact bug a string sort would introduce: "01-2027" < "09-2026".
		self::assertLessThan( $january2027->sortKey(), $september2026->sortKey() );
		self::assertTrue( $september2026->isBefore( $january2027 ) );
		self::assertSame( 202609, $september2026->sortKey() );
	}

	public function test_sort_key_round_trips(): void {
		$date = StartDate::fromString( '05-2029' );

		self::assertTrue( $date->equals( StartDate::fromSortKey( $date->sortKey() ) ) );
	}

	public function test_it_canonicalises_to_zero_padded_month(): void {
		self::assertSame( '01-2027', StartDate::fromString( '1-2027' )->toString() );
	}

	public function test_it_renders_a_human_label(): void {
		self::assertSame( 'September 2026', StartDate::fromString( '09-2026' )->label() );
	}

	public function test_collection_orders_chronologically_and_deduplicates(): void {
		$collection = StartDateCollection::fromDelimitedString( '01-2027, 09-2026, 01-2027, 05-2026' );

		self::assertSame(
			array( '05-2026', '09-2026', '01-2027' ),
			$collection->toStrings()
		);
	}

	public function test_collection_skips_unparseable_fragments(): void {
		$collection = StartDateCollection::fromDelimitedString( '09-2026, rubbish, 13-2026, 01-2027' );

		self::assertSame( array( '09-2026', '01-2027' ), $collection->toStrings() );
	}

	public function test_next_returns_the_soonest_intake_from_a_point_in_time(): void {
		$collection = StartDateCollection::fromDelimitedString( '01-2026, 09-2026, 01-2027' );

		$next = $collection->next( StartDate::fromString( '02-2026' ) );

		self::assertNotNull( $next );
		self::assertSame( '09-2026', $next->toString() );
	}

	public function test_next_returns_null_when_every_intake_has_passed(): void {
		$collection = StartDateCollection::fromDelimitedString( '01-2020' );

		self::assertNull( $collection->next( StartDate::fromString( '01-2026' ) ) );
	}
}
