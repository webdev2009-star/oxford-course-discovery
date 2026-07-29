<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Query;

use InvalidArgumentException;
use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterValue;
use Oxford\CourseDiscovery\Query\Constraint\KeywordConstraint;
use Oxford\CourseDiscovery\Query\Constraint\LookupConstraint;
use Oxford\CourseDiscovery\Query\QueryPlan;
use Oxford\CourseDiscovery\Search\Pagination;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Tests\Support\FakeDatabaseGateway;
use PHPUnit\Framework\TestCase;

/**
 * Immutability is what makes it safe to hand the plan and the criteria to
 * arbitrary third party code, so it is asserted rather than assumed.
 *
 * @covers \Oxford\CourseDiscovery\Query\QueryPlan
 * @covers \Oxford\CourseDiscovery\Query\Constraint\LookupConstraint
 * @covers \Oxford\CourseDiscovery\Search\SearchCriteria
 */
final class QueryPlanTest extends TestCase {

	public function test_adding_a_constraint_returns_a_new_plan(): void {
		$original = QueryPlan::forCourses();
		$modified = $original->withSqlConstraint(
			LookupConstraint::in( Schema::PROVIDERS, 'provider_slug', array( 'uosd' ) )
		);

		self::assertNotSame( $original, $modified );
		self::assertSame( array(), $original->sqlConstraints );
		self::assertCount( 1, $modified->sqlConstraints );
	}

	public function test_criteria_mutations_return_new_instances(): void {
		$original = SearchCriteria::empty();
		$modified = $original->withFilter( FilterKey::fromString( 'provider' ), FilterValue::fromScalar( 'uosd' ) );

		self::assertFalse( $original->isFiltered() );
		self::assertTrue( $modified->isFiltered() );
	}

	public function test_setting_an_empty_value_clears_the_filter(): void {
		$criteria = SearchCriteria::empty()
			->withFilter( FilterKey::fromString( 'provider' ), FilterValue::fromScalar( 'uosd' ) )
			->withFilter( FilterKey::fromString( 'provider' ), FilterValue::none() );

		self::assertFalse( $criteria->hasFilter( FilterKey::fromString( 'provider' ) ) );
	}

	public function test_identities_are_stable_regardless_of_constraint_order(): void {
		$providers = LookupConstraint::in( Schema::PROVIDERS, 'provider_slug', array( 'uosd' ) );
		$locations = LookupConstraint::in( Schema::LOCATIONS, 'location_slug', array( 'india' ) );

		$first  = QueryPlan::forCourses()->withSqlConstraint( $providers )->withSqlConstraint( $locations );
		$second = QueryPlan::forCourses()->withSqlConstraint( $locations )->withSqlConstraint( $providers );

		self::assertSame( $first->identities(), $second->identities() );
	}

	public function test_a_lookup_constraint_requires_values(): void {
		$this->expectException( InvalidArgumentException::class );

		LookupConstraint::in( Schema::PROVIDERS, 'provider_slug', array() );
	}

	public function test_a_lookup_constraint_rejects_unsafe_identifiers(): void {
		$this->expectException( InvalidArgumentException::class );

		LookupConstraint::in( 'courses; DROP TABLE wp_posts', 'slug', array( 'x' ) );
	}

	public function test_a_lookup_constraint_compiles_to_a_semi_join(): void {
		$sql = LookupConstraint::integers( Schema::START_DATES, 'sort_key', array( 202609, 202701 ) )
			->toSql( new FakeDatabaseGateway() );

		self::assertSame(
			'wp_posts.ID IN ( SELECT course_id FROM wp_oxcd_course_start_dates WHERE sort_key IN ( 202609, 202701 ) )',
			$sql
		);
	}

	public function test_an_empty_keyword_contributes_no_sql(): void {
		self::assertSame( '', KeywordConstraint::of( '   ' )->toSql( new FakeDatabaseGateway() ) );
	}

	public function test_keyword_operators_are_stripped_before_boolean_mode(): void {
		$query = KeywordConstraint::of( '-design +"marketing" @foo' )->booleanModeQuery();

		self::assertSame( '+design* +marketing* +foo*', $query );
	}

	public function test_single_characters_are_dropped_from_the_boolean_query(): void {
		self::assertSame( '+design*', KeywordConstraint::of( 'a design' )->booleanModeQuery() );
	}

	public function test_pagination_offsets_are_derived_not_supplied(): void {
		$pagination = Pagination::of( 3, 12 );

		self::assertSame( 24, $pagination->offset() );
		self::assertSame( 4, $pagination->totalPages( 40 ) );
	}
}
