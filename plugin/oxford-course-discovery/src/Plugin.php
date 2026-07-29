<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery;

use Oxford\CourseDiscovery\Cli\Commands;
use Oxford\CourseDiscovery\Frontend\Shortcode;
use Oxford\CourseDiscovery\WordPress\Fields\AcfFields;

/**
 * Boots the plugin.
 *
 * Nothing but wiring: every behaviour lives in a collaborator, so the boot
 * sequence reads as a table of contents and each part can be exercised in
 * isolation.
 */
final class Plugin {

	public const FINDER_PAGE_OPTION = 'oxcd_finder_page_id';

	public function __construct( private readonly Container $container ) {
	}

	public function container(): Container {
		return $this->container;
	}

	public function boot(): void {
		add_action( 'plugins_loaded', $this->onPluginsLoaded( ... ) );
		add_action( 'init', $this->loadTextdomain( ... ), 1 );

		$this->container->postTypes()->boot();
		$this->container->taxonomies()->boot();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register( $this->container );
		}
	}

	public function onPluginsLoaded(): void {
		// Schema drift can happen without activation ever firing (file deploys,
		// multisite, plugin updates), so check on load and self-heal.
		$migrator = $this->container->migrator();

		if ( ! $migrator->isUpToDate() ) {
			$migrator->migrate();
		}

		$this->container->indexer()->boot();
		$this->container->assets()->boot();

		// The shortcode and REST controller are resolved lazily. Building them
		// here would build the filter registry, whose labels call __() — and
		// translating during `plugins_loaded`, before `init`, is what triggers
		// WordPress' "textdomain loaded too early" notice. Deferring costs
		// nothing: neither service is needed until a request actually renders
		// the finder or hits the API.
		add_shortcode(
			Shortcode::TAG,
			fn( array|string $atts = array() ): string => $this->container->shortcode()->render( $atts )
		);

		add_action( 'rest_api_init', fn() => $this->container->restController()->register() );

		// Registered on `init` — late enough for translations, early enough
		// that the `request` filter (applied during parse_request) still sees it.
		add_action( 'init', fn() => $this->container->queryVarGuard()->boot(), 20 );

		if ( AcfFields::isAvailable() ) {
			$this->container->acfFields()->boot();
		} else {
			$this->container->metaBoxFields()->boot();
		}

		if ( is_admin() ) {
			$this->container->courseAdmin()->boot();
		}
	}

	public function loadTextdomain(): void {
		load_plugin_textdomain(
			'oxford-course-discovery',
			false,
			dirname( plugin_basename( $this->container->pluginFile() ) ) . '/languages'
		);
	}

	public function activate(): void {
		$this->container->postTypes()->register();
		$this->container->taxonomies()->register();
		$this->container->migrator()->migrate();
		$this->ensureFinderPage();

		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Create a "Find a course" page on first activation so a fresh install has
	 * somewhere to look, without ever fighting the editor for control of it
	 * afterwards.
	 */
	private function ensureFinderPage(): int {
		$existing = (int) get_option( self::FINDER_PAGE_OPTION, 0 );

		if ( $existing > 0 && 'page' === get_post_type( $existing ) ) {
			return $existing;
		}

		$pageId = wp_insert_post(
			array(
				'post_title'   => __( 'Find a course', 'oxford-course-discovery' ),
				'post_name'    => 'find-a-course',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[' . Shortcode::TAG . ']',
			)
		);

		if ( is_int( $pageId ) && $pageId > 0 ) {
			update_option( self::FINDER_PAGE_OPTION, $pageId, true );

			return $pageId;
		}

		return 0;
	}
}
