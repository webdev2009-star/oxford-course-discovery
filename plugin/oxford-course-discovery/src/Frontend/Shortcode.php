<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Frontend;

/**
 * `[course_finder]` — the whole discovery UI on any page.
 *
 * Renders complete results server side from `$_GET`, so the finder works with
 * JavaScript disabled, is crawlable, and every search has a shareable URL. The
 * script layer only upgrades it to fetch-and-replace.
 */
final class Shortcode {

	public const TAG = 'course_finder';

	public function __construct(
		private readonly CourseFinder $finder,
		private readonly TemplateRenderer $renderer,
		private readonly Assets $assets
	) {
	}

	public function boot(): void {
		add_shortcode( self::TAG, $this->render( ... ) );
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'per_page' => '',
				'orderby'  => '',
				'category' => '',
				'heading'  => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$this->assets->enqueue();

		$result = $this->finder->find( $this->request( $atts ) );

		return $this->renderer->render(
			'course-finder',
			array(
				'result'   => $result,
				'renderer' => $this->renderer,
				'heading'  => (string) $atts['heading'],
				'action'   => $this->currentUrl(),
			)
		);
	}

	/**
	 * Shortcode attributes act as defaults; anything in the query string wins,
	 * so a landing page can pre-filter without trapping the visitor.
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 *
	 * @return array<string, mixed>
	 */
	private function request( array $atts ): array {
		$defaults = array();

		foreach ( array( 'per_page', 'orderby', 'category' ) as $key ) {
			if ( '' !== $atts[ $key ] ) {
				$defaults[ $key ] = 'category' === $key
					? array_map( 'trim', explode( ',', $atts[ $key ] ) )
					: $atts[ $key ];
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, read-only search form.
		$query = wp_unslash( $_GET );

		return array( ...$defaults, ...(array) $query );
	}

	private function currentUrl(): string {
		$permalink = get_permalink();

		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}

		return home_url( '/' );
	}
}
