<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Query;

use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Filter\Filters\CategoryFilter;
use Oxford\CourseDiscovery\Filter\Filters\KeywordFilter;
use Oxford\CourseDiscovery\Filter\Filters\LocationFilter;
use Oxford\CourseDiscovery\Filter\Filters\ProviderFilter;
use Oxford\CourseDiscovery\Filter\Filters\StartDateFilter;
use Oxford\CourseDiscovery\Filter\Options\StaticOptions;
use Oxford\CourseDiscovery\Query\QueryCompiler;
use Oxford\CourseDiscovery\Query\QueryPlan;
use Oxford\CourseDiscovery\Query\QueryPlanner;
use Oxford\CourseDiscovery\Search\CriteriaFactory;
use Oxford\CourseDiscovery\Search\Ordering;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\Tests\Support\Doubles\SpyQueryFilter;
use Oxford\CourseDiscovery\Tests\Support\FakeDatabaseGateway;
use PHPUnit\Framework\TestCase;

/**
 * The behaviour the brief is most specific about:
 *
 *   (provider = uosd OR provider = dmu)
 *   AND (location = india OR location = china)
 *   AND (category = graphic-design)
 *
 * These tests drive the real filters through the real planner and compiler
 * against a fake database, so they assert the actual SQL and query arguments
 * without needing WordPress. This is the regression net for every future
 * filter: the grouping rule is implemented once, here it is proven once.
 *
 * @covers \Oxford\CourseDiscovery\Query\QueryPlanner
 * @covers \Oxford\CourseDiscovery\Query\QueryCompiler
 * @covers \Oxford\CourseDiscovery\Search\CriteriaFactory
 */
final class FilterCompositionTest extends TestCase {

	private FilterRegistry $registry;
	private FakeDatabaseGateway $db;

	protected function setUp(): void {
		parent::setUp();

		\OxcdTestHooks::reset();

		$this->db       = new FakeDatabaseGateway();
		$this->registry = new FilterRegistry(
			array(
				new KeywordFilter(),
				new ProviderFilter(
					StaticOptions::fromPairs(
						array(
							'uosd' => 'UOSD',
							'dmu'  => 'DMU',
						)
					)
				),
				new LocationFilter(
					StaticOptions::fromPairs(
						array(
							'india' => 'India',
							'china' => 'China',
						)
					)
				),
				new StartDateFilter( StaticOptions::fromPairs( array( '09-2026' => 'September 2026' ) ) ),
				new CategoryFilter( StaticOptions::fromPairs( array( 'graphic-design' => 'Graphic Design' ) ) ),
			)
		);
	}

	protected function tearDown(): void {
		\OxcdTestHooks::reset();

		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $request Request parameters.
	 */
	private function planFor( array $request ): QueryPlan {
		$criteria = ( new CriteriaFactory( $this->registry ) )->fromRequest( $request );

		return ( new QueryPlanner( $this->registry ) )->plan( $criteria );
	}

	public function test_each_filter_contributes_exactly_one_constraint(): void {
		$plan = $this->planFor(
			array(
				'provider'   => array( 'uosd', 'dmu' ),
				'location'   => array( 'india', 'china' ),
				'category'   => array( 'graphic-design' ),
				'start_date' => array( '09-2026' ),
			)
		);

		// Three lookup constraints (provider, location, start date) and one
		// taxonomy constraint (category) — never one per selected value.
		self::assertCount( 3, $plan->sqlConstraints );
		self::assertCount( 1, $plan->taxonomyConstraints );
	}

	public function test_values_within_one_filter_are_ored(): void {
		$plan     = $this->planFor( array( 'provider' => array( 'uosd', 'dmu' ) ) );
		$compiled = ( new QueryCompiler( $this->db ) )->compile( $plan );

		self::assertCount( 1, $compiled->whereFragments );
		self::assertStringContainsString( "IN ( 'uosd', 'dmu' )", $compiled->whereFragments[0] );
		self::assertStringContainsString( 'wp_oxcd_course_providers', $compiled->whereFragments[0] );
	}

	public function test_separate_filters_are_anded(): void {
		$plan     = $this->planFor(
			array(
				'provider' => array( 'uosd', 'dmu' ),
				'location' => array( 'india', 'china' ),
			)
		);
		$compiled = ( new QueryCompiler( $this->db ) )->compile( $plan );

		self::assertCount( 2, $compiled->whereFragments );
		self::assertStringContainsString( ' ) AND ( ', $compiled->whereClause() );
		self::assertStringStartsWith( ' AND ( ', $compiled->whereClause() );
	}

	public function test_categories_are_anded_through_the_tax_query(): void {
		$plan     = $this->planFor( array( 'category' => array( 'graphic-design', 'marketing' ) ) );
		$compiled = ( new QueryCompiler( $this->db ) )->compile( $plan );

		self::assertSame( 'AND', $compiled->args['tax_query']['relation'] );
		self::assertSame( 'course_category', $compiled->args['tax_query'][0]['taxonomy'] );
		self::assertSame( array( 'graphic-design', 'marketing' ), $compiled->args['tax_query'][0]['terms'] );
		self::assertSame( 'IN', $compiled->args['tax_query'][0]['operator'] );
	}

	public function test_start_dates_are_converted_to_integer_sort_keys(): void {
		$plan     = $this->planFor( array( 'start_date' => array( '09-2026', '01-2027' ) ) );
		$compiled = ( new QueryCompiler( $this->db ) )->compile( $plan );

		self::assertStringContainsString( 'sort_key IN ( 202609, 202701 )', $compiled->whereFragments[0] );
	}

	public function test_unparseable_start_dates_never_reach_the_query(): void {
		$plan = $this->planFor( array( 'start_date' => array( 'whenever', '13-2026' ) ) );

		self::assertTrue( $plan->isUnconstrained() );
	}

	public function test_unknown_parameters_are_ignored(): void {
		$plan = $this->planFor(
			array(
				'provider'   => array( 'uosd' ),
				'evil'       => array( 'DROP TABLE wp_posts' ),
				'meta_query' => array( 'anything' ),
			)
		);

		self::assertCount( 1, $plan->sqlConstraints );
		self::assertSame( array(), $plan->metaConstraints );
	}

	public function test_values_are_escaped_when_compiled(): void {
		$plan     = $this->planFor( array( 'location' => array( "london' OR 1=1 --" ) ) );
		$compiled = ( new QueryCompiler( $this->db ) )->compile( $plan );

		// sanitize_title() strips the payload; whatever survives is quoted.
		self::assertStringNotContainsString( 'OR 1=1', $compiled->whereFragments[0] );
	}

	public function test_a_keyword_switches_the_default_ordering_to_relevance(): void {
		$criteria = ( new CriteriaFactory( $this->registry ) )->fromRequest( array( 'q' => 'graphic design' ) );

		self::assertTrue( $criteria->ordering->is( Ordering::RELEVANCE ) );
	}

	public function test_an_explicit_ordering_survives_a_keyword_search(): void {
		$criteria = ( new CriteriaFactory( $this->registry ) )->fromRequest(
			array(
				'q'       => 'graphic design',
				'orderby' => 'name',
			)
		);

		self::assertTrue( $criteria->ordering->is( Ordering::NAME ) );
	}

	public function test_keyword_search_uses_the_full_text_index(): void {
		$plan     = $this->planFor( array( 'q' => 'graphic design' ) );
		$compiled = ( new QueryCompiler( $this->db ) )->compile( $plan );

		self::assertStringContainsString( 'MATCH ( name, short_description, long_description )', $compiled->whereFragments[0] );
		self::assertStringContainsString( "'+graphic* +design*'", $compiled->whereFragments[0] );
	}

	public function test_keyword_search_falls_back_to_like_without_full_text(): void {
		$db       = new FakeDatabaseGateway( fullText: false );
		$plan     = $this->planFor( array( 'q' => 'design' ) );
		$compiled = ( new QueryCompiler( $db ) )->compile( $plan );

		self::assertStringContainsString( 'LIKE', $compiled->whereFragments[0] );
		self::assertStringNotContainsString( 'MATCH', $compiled->whereFragments[0] );
	}

	public function test_default_ordering_prefers_the_soonest_upcoming_intake(): void {
		$plan     = $this->planFor( array() );
		$compiled = ( new QueryCompiler( $this->db ) )->compile( $plan );

		self::assertStringContainsString( 'MIN( sd.sort_key )', $compiled->orderBy );
		self::assertStringContainsString( 'wp_posts.ID ASC', $compiled->orderBy );
	}

	public function test_pagination_is_clamped(): void {
		$criteria = ( new CriteriaFactory( $this->registry ) )->fromRequest(
			array(
				'paged'    => '-3',
				'per_page' => '10000',
			)
		);

		self::assertSame( 1, $criteria->pagination->page );
		self::assertSame( 60, $criteria->pagination->perPage );
	}

	/**
	 * A new filter must be able to join the pipeline without any existing
	 * filter, or the pipeline itself, being modified.
	 */
	public function test_a_third_party_filter_participates_without_core_changes(): void {
		$this->registry->register( new SpyQueryFilter( 'delivery_mode', priority: 60 ) );

		$plan = $this->planFor(
			array(
				'provider'      => array( 'uosd' ),
				'delivery_mode' => array( 'online', 'campus' ),
			)
		);

		self::assertCount( 1, $plan->metaConstraints );
		self::assertSame(
			array( 'online', 'campus' ),
			$plan->metaConstraints[0]->toClause()['value']
		);
	}

	public function test_the_plan_can_be_rewritten_by_a_hook(): void {
		add_filter(
			Hooks::QUERY_PLAN,
			static fn( QueryPlan $plan ): QueryPlan => $plan->withoutConstraintsMatching( 'oxcd_course_providers' )
		);

		$plan = $this->planFor( array( 'provider' => array( 'uosd' ) ) );

		self::assertSame( array(), $plan->sqlConstraints );
	}

	public function test_query_arguments_can_be_rewritten_by_a_hook(): void {
		add_filter(
			Hooks::QUERY_ARGS,
			static function ( array $args ): array {
				$args['post_status'] = array( 'publish', 'private' );

				return $args;
			}
		);

		$compiled = ( new QueryCompiler( $this->db ) )->compile( $this->planFor( array() ) );

		self::assertSame( array( 'publish', 'private' ), $compiled->args['post_status'] );
	}

	public function test_criteria_can_be_transformed_by_a_hook(): void {
		add_filter(
			Hooks::CRITERIA,
			static fn( $criteria ) => $criteria->withOrdering( Ordering::of( Ordering::PRICE ) )
		);

		$criteria = ( new CriteriaFactory( $this->registry ) )->fromRequest( array() );

		self::assertTrue( $criteria->ordering->is( Ordering::PRICE ) );
	}

	public function test_the_search_round_trips_through_its_query_string(): void {
		$factory  = new CriteriaFactory( $this->registry );
		$original = $factory->fromRequest(
			array(
				'provider' => array( 'uosd', 'dmu' ),
				'location' => array( 'india' ),
				'paged'    => 3,
			)
		);

		$restored = $factory->fromRequest( $original->toQueryVars() );

		self::assertSame( $original->fingerprint(), $restored->fingerprint() );
		self::assertSame( 3, $restored->pagination->page );
	}
}
