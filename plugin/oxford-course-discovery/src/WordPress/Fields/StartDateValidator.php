<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Fields;

use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;

/**
 * Shared validation for the comma separated intake field, used by both the ACF
 * field group and the fallback metabox so the two editing paths cannot diverge.
 */
final class StartDateValidator {

	/**
	 * @param string $value Raw editor input.
	 *
	 * @return list<string> Fragments that could not be parsed.
	 */
	public static function invalidFragments( string $value ): array {
		$invalid = array();

		$fragments = preg_split(
			'/[,;
|]+/',
			$value
		);

		foreach ( false === $fragments ? array() : $fragments as $fragment ) {
			$fragment = trim( $fragment );

			if ( '' === $fragment ) {
				continue;
			}

			if ( ! StartDate::tryFromString( $fragment ) instanceof StartDate ) {
				$invalid[] = $fragment;
			}
		}

		return $invalid;
	}

	/**
	 * Canonicalise editor input to zero padded, chronologically ordered,
	 * de-duplicated `MM-YYYY` values. Invalid fragments are dropped.
	 */
	public static function canonicalise( string $value ): string {
		return implode(
			', ',
			\Oxford\CourseDiscovery\Domain\Collection\StartDateCollection::fromDelimitedString( $value )->toStrings()
		);
	}

	private function __construct() {
	}
}
