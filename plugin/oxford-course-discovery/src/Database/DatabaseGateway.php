<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database;

/**
 * The narrow slice of `$wpdb` this plugin actually needs.
 *
 * Depending on an interface rather than the global keeps SQL building unit
 * testable: {@see \Oxford\CourseDiscovery\Tests\Unit\Query} compiles real
 * constraints against an in-memory fake and asserts on the generated SQL,
 * with no database and no WordPress bootstrap.
 */
interface DatabaseGateway {

	/**
	 * Fully qualified table name for a plugin table, e.g. `wp_oxcd_course_locations`.
	 *
	 * @param string $name Unprefixed table name.
	 */
	public function table( string $name ): string;

	/**
	 * The `wp_posts` table name.
	 */
	public function postsTable(): string;

	/**
	 * The `wp_terms` table name.
	 */
	public function termsTable(): string;

	/**
	 * `$wpdb::prepare()`; placeholders use the WordPress dialect (%s, %d, %f).
	 *
	 * @param string      $sql  SQL with placeholders.
	 * @param list<mixed> $args Values.
	 */
	public function prepare( string $sql, array $args ): string;

	/**
	 * @param string $sql Query.
	 *
	 * @return list<array<string, string|null>> Rows as associative arrays.
	 */
	public function results( string $sql ): array;

	/**
	 * @param string $sql Query.
	 *
	 * @return list<string> First column of every row.
	 */
	public function column( string $sql ): array;

	/**
	 * @param string $sql Query.
	 */
	public function scalar( string $sql ): ?string;

	/**
	 * Run a statement that returns no rows.
	 *
	 * @param string $sql Statement.
	 *
	 * @return int Affected rows.
	 */
	public function execute( string $sql ): int;

	/**
	 * Whether the storage engine can serve `MATCH ... AGAINST`. MyISAM and
	 * InnoDB 5.6+ can; a caller must fall back to `LIKE` when it cannot.
	 */
	public function supportsFullText(): bool;

	public function tableExists( string $name ): bool;

	/**
	 * `CHARACTER SET ... COLLATE ...` clause for `CREATE TABLE`.
	 */
	public function charsetCollate(): string;
}
