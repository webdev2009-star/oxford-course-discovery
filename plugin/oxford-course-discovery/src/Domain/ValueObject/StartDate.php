<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

use InvalidArgumentException;

/**
 * A course intake, expressed to month precision.
 *
 * Editors type `{month}-{year}` and the UI must offer intakes chronologically,
 * so this owns three things a raw string cannot: validation, a stable sort key
 * and the display label. The sort key is the integer `YYYYMM` the lookup table
 * indexes on — string dates sort lexicographically and put `01-2027` before
 * `09-2026`.
 */
final readonly class StartDate {

	private const MIN_YEAR = 1970;
	private const MAX_YEAR = 2200;

	/**
	 * @param int<1, 12>      $month Month number.
	 * @param int<1970, 2200> $year  Four digit year.
	 *
	 * @throws InvalidArgumentException When outside the supported range.
	 */
	private function __construct( public int $month, public int $year ) {
		if ( $month < 1 || $month > 12 ) {
			throw new InvalidArgumentException( sprintf( 'Month must be between 1 and 12, %d given.', $month ) );
		}

		if ( $year < self::MIN_YEAR || $year > self::MAX_YEAR ) {
			throw new InvalidArgumentException( sprintf( 'Year %d is outside the supported range.', $year ) );
		}
	}

	public static function fromMonthAndYear( int $month, int $year ): self {
		return new self( $month, $year );
	}

	/**
	 * Parse the editor facing `{month}-{year}` format.
	 *
	 * Accepts `01-2026`, `1-2026`, `Jan-2026` and `January 2026` so that a
	 * spreadsheet import does not need to normalise first.
	 *
	 * @throws InvalidArgumentException When the value cannot be understood.
	 */
	public static function fromString( string $value ): self {
		$parsed = self::tryFromString( $value );

		if ( ! $parsed instanceof self ) {
			throw new InvalidArgumentException(
				sprintf( '"%s" is not a valid start date; expected {month}-{year}, e.g. 09-2026.', $value )
			);
		}

		return $parsed;
	}

	/**
	 * Lenient parse used when reading untrusted input.
	 */
	public static function tryFromString( string $value ): ?self {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		$parts = preg_split( '/[\s\-\/\.]+/', $value );

		if ( false === $parts ) {
			return null;
		}

		if ( 2 !== count( $parts ) ) {
			return null;
		}

		[ $rawMonth, $rawYear ] = $parts;

		// Tolerate a reversed "2026-09" as well.
		if ( 4 === strlen( $rawMonth ) && ctype_digit( $rawMonth ) ) {
			[ $rawMonth, $rawYear ] = array( $rawYear, $rawMonth );
		}

		$month = self::parseMonth( $rawMonth );
		$year  = ctype_digit( $rawYear ) ? (int) $rawYear : 0;

		if ( null === $month || $year < self::MIN_YEAR || $year > self::MAX_YEAR ) {
			return null;
		}

		return new self( $month, $year );
	}

	/**
	 * Build from the integer sort key used by the lookup table.
	 */
	public static function fromSortKey( int $sortKey ): self {
		return new self( $sortKey % 100, intdiv( $sortKey, 100 ) );
	}

	/**
	 * Chronologically sortable integer, `YYYYMM`.
	 */
	public function sortKey(): int {
		return $this->year * 100 + $this->month;
	}

	/**
	 * Canonical machine value, always zero padded: `09-2026`.
	 */
	public function toString(): string {
		return sprintf( '%02d-%d', $this->month, $this->year );
	}

	/**
	 * Localised display label, e.g. "September 2026".
	 */
	public function label(): string {
		return sprintf( '%s %d', self::monthNames()[ $this->month ], $this->year );
	}

	public function isBefore( self $other ): bool {
		return $this->sortKey() < $other->sortKey();
	}

	public function equals( self $other ): bool {
		return $this->sortKey() === $other->sortKey();
	}

	public function __toString(): string {
		return $this->toString();
	}

	/**
	 * @return array<int<1, 12>, string>
	 */
	private static function monthNames(): array {
		return array(
			1  => __( 'January', 'oxford-course-discovery' ),
			2  => __( 'February', 'oxford-course-discovery' ),
			3  => __( 'March', 'oxford-course-discovery' ),
			4  => __( 'April', 'oxford-course-discovery' ),
			5  => __( 'May', 'oxford-course-discovery' ),
			6  => __( 'June', 'oxford-course-discovery' ),
			7  => __( 'July', 'oxford-course-discovery' ),
			8  => __( 'August', 'oxford-course-discovery' ),
			9  => __( 'September', 'oxford-course-discovery' ),
			10 => __( 'October', 'oxford-course-discovery' ),
			11 => __( 'November', 'oxford-course-discovery' ),
			12 => __( 'December', 'oxford-course-discovery' ),
		);
	}

	/**
	 * @return int<1, 12>|null
	 */
	private static function parseMonth( string $raw ): ?int {
		if ( ctype_digit( $raw ) ) {
			$month = (int) $raw;

			return $month >= 1 && $month <= 12 ? $month : null;
		}

		$english = array(
			'jan' => 1,
			'feb' => 2,
			'mar' => 3,
			'apr' => 4,
			'may' => 5,
			'jun' => 6,
			'jul' => 7,
			'aug' => 8,
			'sep' => 9,
			'oct' => 10,
			'nov' => 11,
			'dec' => 12,
		);

		$needle = strtolower( substr( $raw, 0, 3 ) );

		return $english[ $needle ] ?? null;
	}
}
