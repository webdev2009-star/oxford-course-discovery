<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Http;

use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Frontend\CourseFinder;
use Oxford\CourseDiscovery\Frontend\TemplateRenderer;
use Oxford\CourseDiscovery\Search\Pagination;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Read-only JSON API behind the enhanced UI.
 *
 * Route arguments are generated from the filter registry, so a third party
 * filter is queryable over REST the moment it registers — no controller change,
 * no second schema to keep in step. The response carries both data and server
 * rendered HTML for the results region, keeping one source of truth for markup
 * so the JavaScript never reimplements a course card.
 */
final class CoursesController {

	public const NAMESPACE = 'oxcd/v1';
	public const ROUTE     = 'courses';

	public function __construct(
		private readonly CourseFinder $finder,
		private readonly FilterRegistry $filters,
		private readonly TemplateRenderer $renderer
	) {
	}

	public function boot(): void {
		add_action( 'rest_api_init', $this->register( ... ) );
	}

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => $this->handle( ... ),
				'permission_callback' => '__return_true',
				'args'                => $this->args(),
			)
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->finder->find( (array) $request->get_query_params() );

		$html = $this->renderer->render(
			'partials/results',
			array(
				'result'   => $result,
				'renderer' => $this->renderer,
			)
		);

		return new WP_REST_Response(
			array(
				...$result->toArray(),
				'html' => $html,
			)
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function args(): array {
		$args = array(
			'paged'    => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => Pagination::DEFAULT_PER_PAGE,
				'minimum' => 1,
				'maximum' => Pagination::MAX_PER_PAGE,
			),
			'orderby'  => array(
				'type'    => 'string',
				'default' => '',
			),
		);

		foreach ( $this->filters->all() as $filter ) {
			$args[ $filter->key()->value ] = array(
				'description' => $filter->label(),
				'required'    => false,
				// Values may arrive as a repeated parameter or as a comma
				// separated string; the filter's own normaliser validates them.
				'type'        => array( 'string', 'array' ),
			);
		}

		return $args;
	}
}
