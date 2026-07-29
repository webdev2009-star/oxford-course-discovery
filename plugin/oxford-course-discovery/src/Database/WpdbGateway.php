<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database;

use wpdb;

/**
 * `$wpdb` backed implementation.
 */
final class WpdbGateway implements DatabaseGateway {

	private ?bool $fullTextSupported = null;

	/**
	 * @var array<string, bool>
	 */
	private array $tableCache = array();

	public function __construct( private readonly wpdb $wpdb ) {
	}

	public static function fromGlobals(): self {
		global $wpdb;

		return new self( $wpdb );
	}

	public function table( string $name ): string {
		return $this->wpdb->prefix . $name;
	}

	public function postsTable(): string {
		return $this->wpdb->posts;
	}

	public function termsTable(): string {
		return $this->wpdb->terms;
	}

	public function prepare( string $sql, array $args ): string {
		if ( array() === $args ) {
			return $sql;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a literal template supplied by our own constraint objects.
		return (string) $this->wpdb->prepare( $sql, ...$args );
	}

	public function results( string $sql ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	public function column( string $sql ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$values = $this->wpdb->get_col( $sql );

		return is_array( $values ) ? array_values( array_map( 'strval', $values ) ) : array();
	}

	public function scalar( string $sql ): ?string {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$value = $this->wpdb->get_var( $sql );

		return null === $value ? null : (string) $value;
	}

	public function execute( string $sql ): int {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->query( $sql );

		return is_int( $result ) ? $result : 0;
	}

	public function supportsFullText(): bool {
		if ( null !== $this->fullTextSupported ) {
			return $this->fullTextSupported;
		}

		$version = $this->wpdb->db_version();

		// InnoDB gained FULLTEXT in MySQL 5.6; MariaDB reports 5.5 compatibility
		// strings, so also treat any 10.x as supported.
		$this->fullTextSupported = version_compare( $version, '5.6', '>=' );

		return $this->fullTextSupported;
	}

	public function tableExists( string $name ): bool {
		$table = $this->table( $name );

		if ( isset( $this->tableCache[ $table ] ) ) {
			return $this->tableCache[ $table ];
		}

		$found = $this->scalar( $this->prepare( 'SHOW TABLES LIKE %s', array( $table ) ) );

		$this->tableCache[ $table ] = $found === $table;

		return $this->tableCache[ $table ];
	}

	public function charsetCollate(): string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Escape a value for use inside an already prepared statement.
	 */
	public function escape( string $value ): string {
		return $this->wpdb->_real_escape( $value );
	}

	public function wpdb(): wpdb {
		return $this->wpdb;
	}
}
