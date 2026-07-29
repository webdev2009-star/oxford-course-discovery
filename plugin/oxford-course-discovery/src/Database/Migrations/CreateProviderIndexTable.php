<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database\Migrations;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Migration;
use Oxford\CourseDiscovery\Database\Schema;

/**
 * One row per (course, provider), flattened from the ACF relationship field.
 *
 * The slug is denormalised alongside the ID so the filter can match the value
 * that appears in the URL without a term/post lookup on every request. It is
 * kept in step by the indexer, which re-runs whenever a provider is saved.
 */
final class CreateProviderIndexTable implements Migration {

	public function version(): int {
		return 2;
	}

	public function name(): string {
		return 'create_course_providers_table';
	}

	public function up( DatabaseGateway $db ): void {
		$table = $db->table( Schema::PROVIDERS );

		MigrationRunner::createTable(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				provider_id BIGINT UNSIGNED NOT NULL,
				provider_slug VARCHAR(200) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				UNIQUE KEY course_provider (course_id, provider_id),
				KEY provider_lookup (provider_slug, course_id),
				KEY provider_id (provider_id)
			) {$db->charsetCollate()};"
		);
	}
}
