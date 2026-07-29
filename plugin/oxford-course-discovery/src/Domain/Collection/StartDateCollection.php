<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Collection;

use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;
use Oxford\CourseDiscovery\Support\TypedCollection;

/**
 * @extends TypedCollection<StartDate>
 */
final class StartDateCollection extends TypedCollection {

	protected static function itemType(): string {
		return StartDate::class;
	}

	/**
	 * Parse a delimited editor string such as `09-2026, 01-2027`.
	 *
	 * Unparseable fragments are skipped; validation of editor input happens at
	 * the admin boundary so that a bad row can never break a read path.
	 */
	public static function fromDelimitedString( string $value ): self {
		$dates = array();

		$fragments = preg_split(
			'/[,;
|]+/',
			$value
		);

		foreach ( false === $fragments ? array() : $fragments as $fragment ) {
			$date = StartDate::tryFromString( $fragment );

			if ( $date instanceof StartDate ) {
				$dates[] = $date;
			}
		}

		return ( new self( $dates ) )->chronological();
	}

	/**
	 * @param list<int> $sortKeys Sort keys as stored in the lookup table.
	 */
	public static function fromSortKeys( array $sortKeys ): self {
		return new self( array_map( StartDate::fromSortKey( ... ), $sortKeys ) );
	}

	/**
	 * De-duplicated and ordered oldest first — the order the brief requires in
	 * the start date combobox.
	 */
	public function chronological(): self {
		return $this
			->unique( static fn( StartDate $date ): int => $date->sortKey() )
			->sorted( static fn( StartDate $a, StartDate $b ): int => $a->sortKey() <=> $b->sortKey() );
	}

	/**
	 * The soonest intake at or after the given date.
	 */
	public function next( StartDate $from ): ?StartDate {
		foreach ( $this->chronological() as $date ) {
			if ( ! $date->isBefore( $from ) ) {
				return $date;
			}
		}

		return null;
	}

	/**
	 * @return list<int>
	 */
	public function sortKeys(): array {
		return $this->map( static fn( StartDate $date ): int => $date->sortKey() );
	}

	/**
	 * @return list<string>
	 */
	public function toStrings(): array {
		return $this->map( static fn( StartDate $date ): string => $date->toString() );
	}
}
