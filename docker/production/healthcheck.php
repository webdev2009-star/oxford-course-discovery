<?php
/**
 * Platform health check.
 *
 * Railway, Render and the compose healthcheck poll this instead of `/`, for
 * three reasons:
 *
 *   - `/` boots all of WordPress, runs the finder query and renders a page.
 *     Polling that every 30 seconds is real load for no information.
 *   - `/` is the front page, so it redirects and returns a full HTML document;
 *     a health probe wants a small, unambiguous status.
 *   - a 200 from `/` says nothing useful about the database, which is the part
 *     that actually fails in production.
 *
 * The entrypoint only starts Apache once provisioning has succeeded, so any
 * response at all means install, migrations and seeding completed. This
 * endpoint's ongoing job is to catch what can break *afterwards*: the database
 * disappearing, or PHP failing to execute.
 *
 * Deliberately does not load WordPress. Reading the environment and pinging
 * MySQL directly keeps the check at a few milliseconds and means a fatal error
 * inside a plugin cannot make a healthy container look unhealthy.
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store, no-cache, must-revalidate' );
header( 'X-Robots-Tag: noindex, nofollow' );

/**
 * First non-empty environment variable from a list of candidates.
 *
 * @param list<string> $names Variable names, in order of preference.
 */
function oxcd_env( array $names, string $fallback = '' ): string {
	foreach ( $names as $name ) {
		$value = getenv( $name );

		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
	}

	return $fallback;
}

/**
 * Are WordPress core and its configuration actually on disk?
 *
 * Catches a misconfiguration the database check cannot: a volume mounted over
 * `/var/www/html` instead of `wp-content/uploads` hides everything the image
 * shipped, and the site serves the platform's default page rather than failing.
 *
 * @return array{ok: bool, detail: string}
 */
function oxcd_check_files(): array {
	$required = array(
		'/var/www/html/wp-config.php',
		'/var/www/html/wp-includes/version.php',
	);

	foreach ( $required as $path ) {
		if ( ! file_exists( $path ) ) {
			return array(
				'ok'     => false,
				'detail' => 'core files missing',
			);
		}
	}

	return array(
		'ok'     => true,
		'detail' => 'present',
	);
}

/**
 * @return array{ok: bool, detail: string}
 */
function oxcd_check_database(): array {
	if ( ! function_exists( 'mysqli_connect' ) ) {
		return array(
			'ok'     => false,
			'detail' => 'mysqli unavailable',
		);
	}

	$host = oxcd_env( array( 'WORDPRESS_DB_HOST', 'MYSQLHOST' ) );
	$user = oxcd_env( array( 'WORDPRESS_DB_USER', 'MYSQLUSER' ) );
	$pass = oxcd_env( array( 'WORDPRESS_DB_PASSWORD', 'MYSQLPASSWORD' ) );
	$name = oxcd_env( array( 'WORDPRESS_DB_NAME', 'MYSQLDATABASE' ) );

	if ( '' === $host ) {
		// Nothing to check against. Report healthy rather than failing a deploy
		// over a missing optional variable — the entrypoint already refuses to
		// start without real credentials.
		return array(
			'ok'     => true,
			'detail' => 'not configured',
		);
	}

	$port = (int) oxcd_env( array( 'MYSQLPORT' ), '3306' );

	if ( str_contains( $host, ':' ) ) {
		[ $host, $maybePort ] = explode( ':', $host, 2 );

		if ( ctype_digit( $maybePort ) ) {
			$port = (int) $maybePort;
		}
	}

	mysqli_report( MYSQLI_REPORT_OFF );

	$connection = @mysqli_connect( $host, $user, $pass, $name, $port );

	if ( ! $connection instanceof mysqli ) {
		return array(
			'ok'     => false,
			'detail' => 'connection failed',
		);
	}

	$alive = @mysqli_query( $connection, 'SELECT 1' );
	mysqli_close( $connection );

	return array(
		'ok'     => false !== $alive,
		'detail' => false !== $alive ? 'reachable' : 'query failed',
	);
}

$files    = oxcd_check_files();
$database = $files['ok'] ? oxcd_check_database() : array(
	'ok'     => false,
	'detail' => 'not checked',
);

$healthy = $files['ok'] && $database['ok'];

http_response_code( $healthy ? 200 : 503 );

echo (string) wp_json_encode_fallback(
	array(
		'status'   => $healthy ? 'ok' : 'unhealthy',
		'files'    => $files['detail'],
		'database' => $database['detail'],
		'php'      => PHP_VERSION,
	)
);

/**
 * `wp_json_encode()` is not available without WordPress loaded.
 *
 * @param array<string, mixed> $data Payload.
 */
function wp_json_encode_fallback( array $data ): string {
	$encoded = json_encode( $data, JSON_UNESCAPED_SLASHES );

	return false === $encoded ? '{"status":"unknown"}' : $encoded;
}
