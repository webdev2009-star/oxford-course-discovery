<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Frontend;

use Oxford\CourseDiscovery\Http\CoursesController;

/**
 * Front end assets.
 *
 * No build step and no framework: one stylesheet and one ES module, enqueued
 * only on pages that actually render the finder.
 */
final class Assets {

	public const HANDLE = 'oxcd-course-finder';

	private bool $enqueued = false;

	public function __construct(
		private readonly string $baseUrl,
		private readonly string $baseDir,
		private readonly string $version
	) {
	}

	public function boot(): void {
		add_action( 'wp_enqueue_scripts', $this->register( ... ) );
		add_filter( 'script_loader_tag', $this->asModule( ... ), 10, 3 );
	}

	public function register(): void {
		wp_register_style(
			self::HANDLE,
			$this->baseUrl . 'assets/css/course-finder.css',
			array(),
			$this->assetVersion( 'assets/css/course-finder.css' )
		);

		wp_register_script(
			self::HANDLE,
			$this->baseUrl . 'assets/js/course-finder.js',
			array(),
			$this->assetVersion( 'assets/js/course-finder.js' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			'oxcdSettings',
			array(
				'endpoint' => rest_url( CoursesController::NAMESPACE . '/' . CoursesController::ROUTE ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'strings'  => array(
					'loading'    => __( 'Loading courses…', 'oxford-course-discovery' ),
					'error'      => __( 'Sorry, the courses could not be loaded. Please try again.', 'oxford-course-discovery' ),
					/* translators: %d: number of selected filter options. */
					'selected'   => __( '%d selected', 'oxford-course-discovery' ),
					'noneChosen' => __( 'Any', 'oxford-course-discovery' ),
					'noMatches'  => __( 'No matching options', 'oxford-course-discovery' ),
				),
			)
		);
	}

	public function enqueue(): void {
		if ( $this->enqueued ) {
			return;
		}

		// Registration normally happens on wp_enqueue_scripts; a shortcode in a
		// widget or block can run earlier, so make it idempotent.
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			$this->register();
		}

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		$this->enqueued = true;
	}

	/**
	 * Serve the front end script as a real ES module.
	 */
	public function asModule( string $tag, string $handle, string $src ): string {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		// Not a new script: this is the already-enqueued handle, re-tagged as a
		// module because wp_enqueue_script() cannot emit type="module".
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		return sprintf(
			'<script type="module" src="%s" id="%s-js"></script>' . "\n",
			esc_url( $src ),
			esc_attr( $handle )
		);
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	private function assetVersion( string $relativePath ): string {
		$path = $this->baseDir . $relativePath;

		return is_readable( $path ) ? (string) filemtime( $path ) : $this->version;
	}
}
