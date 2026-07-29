<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database;

/**
 * Names of the custom tables, in one place.
 *
 * Three of the four filters cannot be answered efficiently from WordPress'
 * native storage: providers are a serialised array in `postmeta`, start dates
 * are free text that will not sort chronologically, and locations are not on
 * the course at all — they are derived through the provider. Each gets a narrow
 * lookup table, plus a FULLTEXT index for keyword search. See
 * docs/PERFORMANCE.md.
 */
final class Schema {

	public const START_DATES  = 'oxcd_course_start_dates';
	public const PROVIDERS    = 'oxcd_course_providers';
	public const LOCATIONS    = 'oxcd_course_locations';
	public const SEARCH_INDEX = 'oxcd_course_search';

	public const COURSE_COLUMN = 'course_id';

	/**
	 * @return list<string>
	 */
	public static function tables(): array {
		return array( self::START_DATES, self::PROVIDERS, self::LOCATIONS, self::SEARCH_INDEX );
	}

	private function __construct() {
	}
}
