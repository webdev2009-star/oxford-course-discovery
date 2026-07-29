<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Filter;

use Oxford\CourseDiscovery\Filter\ContributesQuery;
use Oxford\CourseDiscovery\Filter\Filter;
use Oxford\CourseDiscovery\Filter\FilterControl;
use Oxford\CourseDiscovery\Filter\FilterDefinition;
use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Filter\FilterValue;
use Oxford\CourseDiscovery\Filter\ProvidesOptions;
use Oxford\CourseDiscovery\Tests\Support\Doubles\SpyFilter;
use Oxford\CourseDiscovery\Tests\Support\Doubles\SpyOptionsFilter;
use Oxford\CourseDiscovery\Tests\Support\Doubles\SpyQueryFilter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The registry is the extension point third parties touch first, so its
 * contract — ordering, capability discovery, refusal to silently clobber — is
 * covered directly.
 *
 * @covers \Oxford\CourseDiscovery\Filter\FilterRegistry
 */
final class FilterRegistryTest extends TestCase {

	public function test_filters_are_ordered_by_priority(): void {
		$registry = new FilterRegistry(
			array(
				new SpyFilter( 'third', priority: 30 ),
				new SpyFilter( 'first', priority: 10 ),
				new SpyFilter( 'second', priority: 20 ),
			)
		);

		self::assertSame( array( 'first', 'second', 'third' ), $registry->keys() );
	}

	public function test_equal_priorities_keep_registration_order(): void {
		$registry = new FilterRegistry(
			array(
				new SpyFilter( 'alpha', priority: 10 ),
				new SpyFilter( 'beta', priority: 10 ),
			)
		);

		self::assertSame( array( 'alpha', 'beta' ), $registry->keys() );
	}

	public function test_registering_a_duplicate_key_is_an_error(): void {
		$registry = new FilterRegistry( array( new SpyFilter( 'provider' ) ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/already registered/' );

		$registry->register( new SpyFilter( 'provider' ) );
	}

	public function test_replace_overrides_a_built_in_filter(): void {
		$registry = new FilterRegistry( array( new SpyFilter( 'provider', label: 'Providers' ) ) );

		$registry->replace( new SpyFilter( 'provider', label: 'Partner institutions' ) );

		self::assertSame( 1, $registry->count() );
		self::assertSame( 'Partner institutions', $registry->require( FilterKey::fromString( 'provider' ) )->label() );
	}

	public function test_a_filter_can_be_removed_entirely(): void {
		$registry = new FilterRegistry( array( new SpyFilter( 'provider' ) ) );

		$registry->unregister( FilterKey::fromString( 'provider' ) );

		self::assertFalse( $registry->has( FilterKey::fromString( 'provider' ) ) );
		self::assertNull( $registry->get( FilterKey::fromString( 'provider' ) ) );
	}

	public function test_requiring_an_unknown_filter_fails_loudly(): void {
		$registry = new FilterRegistry();

		$this->expectException( RuntimeException::class );

		$registry->require( FilterKey::fromString( 'nope' ) );
	}

	/**
	 * Capability discovery is what lets the pipeline stay open for extension:
	 * it asks "who can do X", never "which class are you".
	 */
	public function test_it_selects_filters_by_capability(): void {
		$registry = new FilterRegistry(
			array(
				new SpyFilter( 'plain', priority: 10 ),
				new SpyQueryFilter( 'queryable', priority: 20 ),
				new SpyOptionsFilter( 'optioned', priority: 30 ),
			)
		);

		$contributors = $registry->providing( ContributesQuery::class );
		$providers    = $registry->providing( ProvidesOptions::class );

		self::assertCount( 1, $contributors );
		self::assertSame( 'queryable', $contributors[0]->key()->value );
		self::assertCount( 1, $providers );
		self::assertSame( 'optioned', $providers[0]->key()->value );
	}

	public function test_capability_selection_preserves_priority_order(): void {
		$registry = new FilterRegistry(
			array(
				new SpyQueryFilter( 'late', priority: 90 ),
				new SpyQueryFilter( 'early', priority: 5 ),
			)
		);

		self::assertSame(
			array( 'early', 'late' ),
			array_map(
				static fn( Filter $filter ): string => $filter->key()->value,
				$registry->providing( ContributesQuery::class )
			)
		);
	}

	public function test_filter_definitions_are_immutable(): void {
		$definition = FilterDefinition::create( 'provider', 'Providers', FilterControl::Checkboxes, 20 );
		$relabelled = $definition->withLabel( 'Partners' );

		self::assertSame( 'Providers', $definition->label );
		self::assertSame( 'Partners', $relabelled->label );
		self::assertSame( 20, $relabelled->priority );
	}

	public function test_filter_values_are_never_empty_strings(): void {
		$value = FilterValue::fromRequest( array( 'london', '', '  ', 'oxford', 'london' ) );

		self::assertSame( array( 'london', 'oxford' ), $value->toArray() );
	}
}
