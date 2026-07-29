<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database\Migrations;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Migration;
use Oxford\CourseDiscovery\Database\Schema;

/**
 * Denormalised, FULLTEXT indexed copy of the three searchable fields.
 *
 * The brief asks for keyword matching across name, short description and long
 * description. Two of those live in `wp_posts` and one in `wp_postmeta`, so a
 * native search is a `LIKE '%term%'` union across two tables — no index, and
 * it degrades linearly with catalogue size. A single indexed row per course
 * turns it into a FULLTEXT lookup that also yields a relevance score for free.
 *
 * The table uses the site's default engine (InnoDB, which has supported
 * FULLTEXT since MySQL 5.6). It is entirely disposable — `wp oxcd reindex`
 * rebuilds it from `wp_posts` — and {@see \Oxford\CourseDiscovery\Query\Constraint\KeywordConstraint}
 * degrades to `LIKE` against the same table if FULLTEXT is unavailable, so no
 * deployment is left without working search.
 */
final class CreateSearchIndexTable implements Migration {

	public function version(): int {
		return 4;
	}

	public function name(): string {
		return 'create_course_search_index_table';
	}

	public function up( DatabaseGateway $db ): void {
		$table = $db->table( Schema::SEARCH_INDEX );

		MigrationRunner::createTable(
			"CREATE TABLE {$table} (
				course_id BIGINT UNSIGNED NOT NULL,
				name TEXT NOT NULL,
				short_description TEXT NOT NULL,
				long_description LONGTEXT NOT NULL,
				updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (course_id),
				FULLTEXT KEY course_search (name, short_description, long_description)
			) {$db->charsetCollate()};"
		);
	}
}
