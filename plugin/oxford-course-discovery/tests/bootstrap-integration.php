<?php
/**
 * Bootstrap for the integration and feature suites.
 *
 * These run against a real WordPress instance from the official test library,
 * with a real database, so anything involving `WP_Query`, `dbDelta`, meta
 * storage or the REST server is exercised for real rather than mocked.
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! is_string( $_tests_dir ) || '' === $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WordPress is not loaded yet.
	fwrite(
		STDERR,
		"Could not find the WordPress test library at {$_tests_dir}.\n" .
		"Run: composer test:install (or bin/install-wp-tests.sh) first.\n"
	);

	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/oxford-course-discovery.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

// The plugin's activation routine normally creates the schema; the test suite
// never activates plugins, so run the migrations once for the whole suite.
\Oxford\CourseDiscovery\plugin()->container()->migrator()->migrate();
