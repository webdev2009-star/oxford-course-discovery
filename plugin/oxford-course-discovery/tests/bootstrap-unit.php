<?php
/**
 * Bootstrap for the pure unit suite.
 *
 * The domain, filter and query-building layers are deliberately free of
 * WordPress dependencies apart from a handful of leaf functions (translation,
 * sanitisation, the hook API). Polyfilling those — rather than booting a whole
 * WordPress install — keeps this suite at sub-second runtime, which is what
 * makes it useful to run on every save.
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// This file deliberately declares stand-ins for a handful of WordPress
// functions so the unit suite can run without WordPress. The sniffs below
// exist to stop plugins doing exactly that in production code.
// phpcs:disable WordPress.WP.AlternativeFunctions, Universal.Files.SeparateFunctionsFromOO, Universal.NamingConventions.NoReservedKeywordParameterNames, Universal.Operators.DisallowShortTernary

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Minimal, inspectable hook registry.
 *
 * Real enough to test the extension points: callbacks run in priority order
 * and the filtered value is threaded through them.
 */
final class OxcdTestHooks {

	/**
	 * @var array<string, array<int, list<callable>>>
	 */
	public static array $filters = array();

	/**
	 * @var array<string, int>
	 */
	public static array $actions = array();

	public static function reset(): void {
		self::$filters = array();
		self::$actions = array();
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted = 1 ): bool {
		OxcdTestHooks::$filters[ $hook ][ $priority ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted = 1 ): bool {
		return add_filter( $hook, $callback, $priority, $accepted );
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( string $hook, callable $callback, int $priority = 10 ): bool {
		unset( OxcdTestHooks::$filters[ $hook ][ $priority ] );

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		$callbacks = OxcdTestHooks::$filters[ $hook ] ?? array();
		ksort( $callbacks );

		foreach ( $callbacks as $priority => $group ) {
			foreach ( $group as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, mixed ...$args ): void {
		OxcdTestHooks::$actions[ $hook ] = ( OxcdTestHooks::$actions[ $hook ] ?? 0 ) + 1;

		$callbacks = OxcdTestHooks::$filters[ $hook ] ?? array();
		ksort( $callbacks );

		foreach ( $callbacks as $group ) {
			foreach ( $group as $callback ) {
				$callback( ...$args );
			}
		}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		$title = strtolower( trim( $title ) );
		$title = preg_replace( '/[^a-z0-9_\-]+/', '-', $title ) ?? '';

		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return trim( strip_tags( $text ) );
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( string $text, int $words = 55, ?string $more = null ): string {
		$parts = preg_split( '/\s+/', trim( $text ) ) ?: array();

		if ( count( $parts ) <= $words ) {
			return implode( ' ', $parts );
		}

		return implode( ' ', array_slice( $parts, 0, $words ) ) . ( $more ?? '…' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data ): string|false {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value, bool $autoload = true ): bool {
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ): mixed {
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, mixed $value, int $ttl = 0 ): bool {
		return true;
	}
}
