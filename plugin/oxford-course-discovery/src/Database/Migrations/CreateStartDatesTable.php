<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database\Migrations;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Migration;
use Oxford\CourseDiscovery\Database\Schema;

/**
 * One row per (course, intake).
 *
 * `sort_key` is the integer `YYYYMM` form of `{month}-{year}`. Storing it as an
 * integer is what makes the chronological combobox, the "soonest start date"
 * ordering and the intake filter all index-only operations. The unique key
 * doubles as the de-duplication rule; the `(sort_key, course_id)` key is
 * covering for both the facet query and the filter.
 */
final class CreateStartDatesTable implements Migration {

	public function version(): int {
		return 1;
	}

	public function name(): string {
		return 'create_course_start_dates_table';
	}

	public function up( DatabaseGateway $db ): void {
		$table = $db->table( Schema::START_DATES );

		MigrationRunner::createTable(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				sort_key MEDIUMINT UNSIGNED NOT NULL,
				start_month TINYINT UNSIGNED NOT NULL,
				start_year SMALLINT UNSIGNED NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY course_intake (course_id, sort_key),
				KEY intake_lookup (sort_key, course_id)
			) {$db->charsetCollate()};"
		);
	}
}
