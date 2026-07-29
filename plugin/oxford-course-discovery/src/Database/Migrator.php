<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database;

use Oxford\CourseDiscovery\Database\Migrations\CreateLocationIndexTable;
use Oxford\CourseDiscovery\Database\Migrations\CreateProviderIndexTable;
use Oxford\CourseDiscovery\Database\Migrations\CreateSearchIndexTable;
use Oxford\CourseDiscovery\Database\Migrations\CreateStartDatesTable;

/**
 * Runs pending migrations and records how far the site has got.
 *
 * Deliberately not tied to plugin activation alone: activation hooks do not
 * fire on a `wp plugin update`, on a multisite network activation for a site
 * added later, or when the plugin is deployed by copying files. The version
 * check therefore also runs cheaply on every load (one autoloaded option read)
 * and is exposed as `wp oxcd migrate` for deploy scripts.
 */
final class Migrator {

	public const VERSION_OPTION = 'oxcd_schema_version';

	/**
	 * @param list<Migration> $migrations Registered migrations.
	 */
	public function __construct(
		private readonly DatabaseGateway $db,
		private readonly array $migrations
	) {
	}

	public static function withDefaults( DatabaseGateway $db ): self {
		return new self(
			$db,
			array(
				new CreateStartDatesTable(),
				new CreateProviderIndexTable(),
				new CreateLocationIndexTable(),
				new CreateSearchIndexTable(),
			)
		);
	}

	/**
	 * @return list<string> Names of the migrations that ran.
	 */
	public function migrate(): array {
		$current = $this->currentVersion();
		$ran     = array();

		foreach ( $this->pending() as $migration ) {
			$migration->up( $this->db );

			$ran[]   = $migration->name();
			$current = max( $current, $migration->version() );
		}

		if ( array() !== $ran ) {
			update_option( self::VERSION_OPTION, $current, true );
		}

		return $ran;
	}

	/**
	 * @return list<Migration> Migrations newer than the recorded version.
	 */
	public function pending(): array {
		$current = $this->currentVersion();

		$pending = array_values(
			array_filter(
				$this->migrations,
				static fn( Migration $migration ): bool => $migration->version() > $current
			)
		);

		usort( $pending, static fn( Migration $a, Migration $b ): int => $a->version() <=> $b->version() );

		return $pending;
	}

	public function isUpToDate(): bool {
		return array() === $this->pending();
	}

	public function currentVersion(): int {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	public function latestVersion(): int {
		return array_reduce(
			$this->migrations,
			static fn( int $carry, Migration $migration ): int => max( $carry, $migration->version() ),
			0
		);
	}

	/**
	 * Drop every plugin table. Used by uninstall and by the test harness.
	 */
	public function drop(): void {
		foreach ( Schema::tables() as $table ) {
			$this->db->execute( sprintf( 'DROP TABLE IF EXISTS %s', $this->db->table( $table ) ) );
		}

		delete_option( self::VERSION_OPTION );
	}
}
