<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Filters;

use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Filter\ContributesQuery;
use Oxford\CourseDiscovery\Filter\Filter;
use Oxford\CourseDiscovery\Filter\FilterControl;
use Oxford\CourseDiscovery\Filter\FilterDefinition;
use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Filter\FilterValue;
use Oxford\CourseDiscovery\Filter\NormalisesValue;
use Oxford\CourseDiscovery\Filter\Options\OptionsSource;
use Oxford\CourseDiscovery\Filter\ProvidesOptions;
use Oxford\CourseDiscovery\Query\Constraint\LookupConstraint;
use Oxford\CourseDiscovery\Query\QueryPlan;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Multi-select combobox over locations.
 *
 * Locations are a derived field: they belong to the provider, and a course
 * inherits the union of its providers' locations. Nothing about that is
 * expressible in `WP_Query`, so the indexer materialises (course, location)
 * pairs and this filter queries them like any other lookup.
 */
final class LocationFilter implements Filter, ProvidesOptions, ContributesQuery, NormalisesValue {

	public const KEY = 'location';

	private readonly FilterDefinition $definition;

	public function __construct(
		private readonly OptionsSource $optionsSource,
		?FilterDefinition $definition = null
	) {
		$this->definition = $definition ?? FilterDefinition::create(
			self::KEY,
			__( 'Locations', 'oxford-course-discovery' ),
			FilterControl::Combobox,
			30
		);
	}

	public function key(): FilterKey {
		return $this->definition->key;
	}

	public function label(): string {
		return $this->definition->label;
	}

	public function control(): FilterControl {
		return $this->definition->control;
	}

	public function priority(): int {
		return $this->definition->priority;
	}

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		return $this->optionsSource->options( $criteria );
	}

	public function normalise( FilterValue $value ): FilterValue {
		return FilterValue::fromIterable(
			array_map( 'sanitize_title', $value->toArray() )
		);
	}

	public function contribute( FilterValue $value, QueryPlan $plan, SearchCriteria $criteria ): QueryPlan {
		return $plan->withSqlConstraint(
			LookupConstraint::in( Schema::LOCATIONS, 'location_slug', $value->toArray() )
		);
	}
}
