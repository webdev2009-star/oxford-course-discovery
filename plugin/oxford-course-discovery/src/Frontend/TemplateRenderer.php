<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Frontend;

use Oxford\CourseDiscovery\Support\Hooks;
use RuntimeException;

/**
 * Resolves and renders a view, theme first.
 *
 * A theme can override any partial by dropping a file at
 * `oxford-course-discovery/<view>.php`, so integrators change markup without
 * touching the plugin or losing updates. Templates are rendered in an isolated
 * scope with an explicit `$data` array — no `extract()`, no leaking globals.
 */
final class TemplateRenderer {

	public function __construct(
		private readonly string $templateDirectory,
		private readonly string $themeSubdirectory = 'oxford-course-discovery'
	) {
	}

	/**
	 * @param string               $view View name, without extension, e.g. `partials/course-card`.
	 * @param array<string, mixed> $data Data available to the template as `$data`.
	 *
	 * @throws RuntimeException When no candidate template exists.
	 */
	public function render( string $view, array $data = array() ): string {
		$template = $this->locate( $view );

		if ( null === $template ) {
			throw new RuntimeException( sprintf( 'No template found for view "%s".', $view ) );
		}

		$render = static function ( string $__template, array $data ): void {
			include $__template;
		};

		ob_start();
		$render( $template, $data );

		return (string) ob_get_clean();
	}

	/**
	 * Render straight to the output buffer.
	 *
	 * @param array<string, mixed> $data Template data.
	 */
	public function output( string $view, array $data = array() ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates escape their own output.
		echo $this->render( $view, $data );
	}

	public function locate( string $view ): ?string {
		$view = trim( str_replace( array( '..', "\0" ), '', $view ), '/' );

		$candidates = array(
			get_stylesheet_directory() . '/' . $this->themeSubdirectory . '/' . $view . '.php',
			get_template_directory() . '/' . $this->themeSubdirectory . '/' . $view . '.php',
			rtrim( $this->templateDirectory, '/' ) . '/' . $view . '.php',
		);

		/**
		 * @see Hooks::TEMPLATE_CANDIDATES
		 */
		$candidates = (array) apply_filters( Hooks::TEMPLATE_CANDIDATES, $candidates, $view );

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && is_readable( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}
}
