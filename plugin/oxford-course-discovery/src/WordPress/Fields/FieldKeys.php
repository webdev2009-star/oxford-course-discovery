<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Fields;

/**
 * Meta keys written by the admin UI and read by the mapper.
 *
 * ACF is the editing experience, not the storage contract: these keys are
 * plain post meta, so the plugin reads correctly whether the values were
 * written by ACF, by the built-in fallback metabox, by WP-CLI or by an import.
 * Nothing outside {@see \Oxford\CourseDiscovery\WordPress\CourseMapper} and the
 * indexer should touch them.
 */
final class FieldKeys {

	/** Course: short description (string). */
	public const SHORT_DESCRIPTION = 'oxcd_short_description';

	/** Course: price as a decimal string. Long description lives in post_content. */
	public const PRICE = 'oxcd_price';

	/** Course: ISO currency code; defaults to GBP when absent. */
	public const CURRENCY = 'oxcd_currency';

	/** Course: instructor post IDs (array<int>). */
	public const INSTRUCTORS = 'oxcd_instructors';

	/** Course: provider post IDs (array<int>). */
	public const PROVIDERS = 'oxcd_providers';

	/** Course: comma separated `{month}-{year}` intakes. */
	public const START_DATES = 'oxcd_start_dates';

	/** Instructor: job title. */
	public const INSTRUCTOR_ROLE = 'oxcd_instructor_role';

	/** Provider: website URL. */
	public const PROVIDER_URL = 'oxcd_provider_url';

	/**
	 * @return list<string> Course meta keys, for cleanup and export.
	 */
	public static function courseKeys(): array {
		return array(
			self::SHORT_DESCRIPTION,
			self::PRICE,
			self::CURRENCY,
			self::INSTRUCTORS,
			self::PROVIDERS,
			self::START_DATES,
		);
	}

	private function __construct() {
	}
}
