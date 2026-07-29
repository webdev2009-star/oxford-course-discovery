<?php
/**
 * Plugin Name:       Oxford Course Discovery
 * Plugin URI:        https://example.org/oxford-course-discovery
 * Description:       An extensible, domain driven Course Discovery system: course/instructor/provider post types, composable filters and an accessible front end finder.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Oxford International — Technical Assessment
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oxford-course-discovery
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

namespace Oxford\CourseDiscovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION     = '1.0.0';
const PLUGIN_FILE = __FILE__;

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>Oxford Course Discovery requires PHP 8.2 or newer.</p></div>';
		}
	);

	return;
}

/**
 * Composer autoloader when available, otherwise a PSR-4 fallback so the plugin
 * can be dropped into wp-content/plugins without a build step.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $className ): void {
			$prefix = 'Oxford\\CourseDiscovery\\';

			if ( ! str_starts_with( $className, $prefix ) ) {
				return;
			}

			$relative = substr( $className, strlen( $prefix ) );
			$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * Shared plugin instance.
 */
function plugin(): Plugin {
	static $plugin = null;

	if ( ! $plugin instanceof Plugin ) {
		$plugin = new Plugin( new Container( __FILE__ ) );
	}

	return $plugin;
}

register_activation_hook( __FILE__, static fn() => plugin()->activate() );
register_deactivation_hook( __FILE__, static fn() => plugin()->deactivate() );

plugin()->boot();
