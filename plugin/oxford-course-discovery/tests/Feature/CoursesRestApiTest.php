<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Feature;

use Oxford\CourseDiscovery\Filter\ContributesQuery;
use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Http\CoursesController;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\Tests\Support\CourseTestCase;
use Oxford\CourseDiscovery\Tests\Support\Doubles\SpyQueryFilter;
use WP_REST_Request;
use WP_REST_Server;

/**
 * The JSON contract the enhanced front end depends on.
 *
 * @covers \Oxford\CourseDiscovery\Http\CoursesController
 */
final class CoursesRestApiTest extends CourseTestCase {

	private WP_REST_Server $server;

	protected function setUp(): void {
		parent::setUp();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );

		$provider = $this->makeProvider( 'UOSD', array( 'Oxford' ) );

		$this->makeCourse(
			'Graphic Design Foundation',
			array(
				'providers'         => array( $provider ),
				'start_dates'       => '09-2030',
				'price'             => 9500,
				'short_description' => 'Typography and branding.',
			)
		);

		$this->makeCourse( 'International Business', array( 'providers' => array( $provider ) ) );
	}

	/**
	 * @param array<string, mixed> $params Query parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function get( array $params = array() ): array {
		$request = new WP_REST_Request( 'GET', '/' . CoursesController::NAMESPACE . '/' . CoursesController::ROUTE );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->server->dispatch( $request );

		self::assertSame( 200, $response->get_status() );

		return (array) $response->get_data();
	}

	public function test_the_route_is_registered_and_public(): void {
		$routes = $this->server->get_routes();

		self::assertArrayHasKey( '/' . CoursesController::NAMESPACE . '/' . CoursesController::ROUTE, $routes );
	}

	public function test_it_returns_courses_pagination_and_markup(): void {
		$data = $this->get();

		self::assertCount( 2, $data['courses'] );
		self::assertSame( 2, $data['pagination']['total'] );
		self::assertStringContainsString( 'Graphic Design Foundation', $data['html'] );
		self::assertStringContainsString( 'Showing 1–2 of 2 courses', $data['summary'] );
	}

	public function test_a_course_payload_is_fully_typed(): void {
		// Ordered by name so the assertion targets a known course without
		// depending on FULLTEXT, which needs committed rows (see KeywordSearchTest).
		$course = $this->get( array( 'orderby' => 'name' ) )['courses'][0];

		self::assertIsInt( $course['id'] );
		self::assertSame( 'Graphic Design Foundation', $course['name'] );
		self::assertSame( 9500.0, $course['price']['from'] );
		self::assertSame( 'GBP', $course['price']['currency'] );
		self::assertSame( '£9,500', $course['price']['formatted'] );
		self::assertSame( array( 'UOSD' ), array_column( $course['providers'], 'name' ) );
		self::assertSame( array( 'Oxford' ), array_column( $course['locations'], 'name' ) );
		self::assertSame(
			array(
				array(
					'value' => '09-2030',
					'label' => 'September 2030',
				),
			),
			$course['startDates']
		);
	}

	public function test_it_reports_the_available_filters_and_options(): void {
		$filters = $this->get()['filters'];
		$keys    = array_column( $filters, 'key' );

		self::assertSame( array( 'q', 'provider', 'location', 'start_date', 'category' ), $keys );

		$location = $filters[ array_search( 'location', $keys, true ) ];

		self::assertSame( 'combobox', $location['control'] );
		self::assertSame( array( 'oxford' ), array_column( $location['options'], 'value' ) );
	}

	public function test_it_filters_by_repeated_parameters(): void {
		$data = $this->get( array( 'provider' => array( 'uosd' ) ) );

		self::assertSame( 2, $data['pagination']['total'] );
	}

	public function test_it_accepts_comma_separated_values(): void {
		$data = $this->get( array( 'location' => 'oxford,leicester' ) );

		self::assertSame( 2, $data['pagination']['total'] );
	}

	public function test_it_paginates(): void {
		$data = $this->get(
			array(
				'per_page' => 1,
				'paged'    => 2,
			)
		);

		self::assertCount( 1, $data['courses'] );
		self::assertSame( 2, $data['pagination']['page'] );
		self::assertSame( 2, $data['pagination']['totalPages'] );
		self::assertTrue( $data['pagination']['hasPrevious'] );
		self::assertFalse( $data['pagination']['hasNext'] );
	}

	public function test_an_oversized_page_request_is_clamped(): void {
		$request = new WP_REST_Request( 'GET', '/' . CoursesController::NAMESPACE . '/' . CoursesController::ROUTE );
		$request->set_param( 'per_page', 5000 );

		$response = $this->server->dispatch( $request );

		// Rejected by the route schema rather than reaching the database.
		self::assertSame( 400, $response->get_status() );
	}

	/**
	 * A filter registered by a third party must be queryable over REST with no
	 * controller change — the endpoint reads the registry, not a hard-coded list.
	 */
	public function test_a_registered_filter_becomes_a_rest_argument(): void {
		add_action(
			Hooks::REGISTER_FILTERS,
			static function ( FilterRegistry $registry ): void {
				$registry->register( new SpyQueryFilter( 'delivery_mode', priority: 70 ) );
			}
		);

		// The container memoises its registry, so build one the same way it
		// does: construct, then open it up to integrations.
		$registry = new FilterRegistry();
		do_action( Hooks::REGISTER_FILTERS, $registry );

		$controller = new CoursesController(
			$this->container()->finder(),
			$registry,
			$this->container()->renderer()
		);

		self::assertArrayHasKey( 'delivery_mode', $controller->args() );
		self::assertInstanceOf(
			ContributesQuery::class,
			$registry->require( FilterKey::fromString( 'delivery_mode' ) )
		);
	}
}
