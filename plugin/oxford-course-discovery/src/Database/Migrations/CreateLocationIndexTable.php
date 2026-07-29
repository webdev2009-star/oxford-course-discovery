<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database\Migrations;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Migration;
use Oxford\CourseDiscovery\Database\Schema;

/**
 * One row per (course, location), derived transitively:
 * course → providers → provider location terms.
 *
 * This is the table that makes "locations are a derived field" a real,
 * queryable thing rather than a PHP-side loop. `provider_id` is retained so
 * the indexer can explain, and selectively rebuild, where a location came from.
 */
final class CreateLocationIndexTable implements Migration {

	public function version(): int {
		return 3;
	}

	public function name(): string {
		return 'create_course_locations_table';
	}

	public function up( DatabaseGateway $db ): void {
		$table = $db->table( Schema::LOCATIONS );

		MigrationRunner::createTable(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				location_slug VARCHAR(200) NOT NULL DEFAULT '',
				provider_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY course_location (course_id, location_id, provider_id),
				KEY location_lookup (location_slug, course_id),
				KEY location_id (location_id)
			) {$db->charsetCollate()};"
		);
	}
}
