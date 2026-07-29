<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database\Migrations;

/**
 * Thin wrapper around `dbDelta()`.
 *
 * `dbDelta()` lives in an admin-only include and is picky about formatting, so
 * the include and the call are done in exactly one place.
 */
final class MigrationRunner {

	/**
	 * @param string $sql A single `CREATE TABLE` statement in dbDelta's dialect.
	 *
	 * @return list<string> dbDelta's report of what it changed.
	 */
	public static function createTable( string $sql ): array {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		return array_values( (array) dbDelta( $sql ) );
	}

	private function __construct() {
	}
}
