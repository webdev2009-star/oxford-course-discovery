<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Integration;

use Oxford\CourseDiscovery\Database\Migrator;
use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Tests\Support\CourseTestCase;

/**
 * @covers \Oxford\CourseDiscovery\Database\Migrator
 */
final class MigratorTest extends CourseTestCase {

	public function test_every_table_is_created(): void {
		$db = $this->container()->database();

		foreach ( Schema::tables() as $table ) {
			self::assertTrue( $db->tableExists( $table ), sprintf( 'Missing table %s', $table ) );
		}
	}

	public function test_the_recorded_version_matches_the_latest_migration(): void {
		$migrator = $this->container()->migrator();

		self::assertTrue( $migrator->isUpToDate() );
		self::assertSame( $migrator->latestVersion(), $migrator->currentVersion() );
	}

	public function test_migrating_twice_is_a_no_op(): void {
		self::assertSame( array(), $this->container()->migrator()->migrate() );
	}

	public function test_a_fresh_site_runs_every_migration_in_order(): void {
		delete_option( Migrator::VERSION_OPTION );

		$migrator = Migrator::withDefaults( $this->container()->database() );
		$pending  = $migrator->pending();

		$versions = array_map( static fn( $migration ): int => $migration->version(), $pending );
		$sorted   = $versions;
		sort( $sorted );

		self::assertSame( $sorted, $versions );
		self::assertSame( array( 1, 2, 3, 4 ), $versions );

		$ran = $migrator->migrate();

		self::assertCount( 4, $ran );
		self::assertTrue( $migrator->isUpToDate() );
	}

	public function test_the_start_date_table_enforces_uniqueness(): void {
		$db    = $this->container()->database();
		$table = $db->table( Schema::START_DATES );

		$db->execute(
			$db->prepare(
				"INSERT IGNORE INTO {$table} ( course_id, sort_key, start_month, start_year ) VALUES ( %d, %d, %d, %d )",
				array( 999999, 202609, 9, 2026 )
			)
		);
		$db->execute(
			$db->prepare(
				"INSERT IGNORE INTO {$table} ( course_id, sort_key, start_month, start_year ) VALUES ( %d, %d, %d, %d )",
				array( 999999, 202609, 9, 2026 )
			)
		);

		$count = $db->scalar(
			$db->prepare( "SELECT COUNT(*) FROM {$table} WHERE course_id = %d", array( 999999 ) )
		);

		self::assertSame( '1', $count );
	}
}
