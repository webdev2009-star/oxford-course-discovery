<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Filters;

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
use Oxford\CourseDiscovery\Query\Constraint\TaxonomyConstraint;
use Oxford\CourseDiscovery\Query\QueryPlan;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\WordPress\Taxonomy\CourseCategoryTaxonomy;

/**
 * Hierarchical subject categories.
 *
 * The one built-in filter that does *not* need a lookup table: term
 * relationships are already indexed, and `tax_query` gets descendant matching
 * (select "Design", get "Graphic Design") for free. Sharing the same
 * {@see ContributesQuery} contract as the lookup-backed filters is the point —
 * the query layer does not care where a constraint is satisfied.
 */
final class CategoryFilter implements Filter, ProvidesOptions, ContributesQuery, NormalisesValue {

	public const KEY = 'category';

	private readonly FilterDefinition $definition;

	public function __construct(
		private readonly OptionsSource $optionsSource,
		private readonly string $taxonomy = CourseCategoryTaxonomy::NAME,
		?FilterDefinition $definition = null
	) {
		$this->definition = $definition ?? FilterDefinition::create(
			self::KEY,
			__( 'Categories', 'oxford-course-discovery' ),
			FilterControl::Tree,
			50
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
		return $plan->withTaxonomyConstraint(
			TaxonomyConstraint::slugs( $this->taxonomy, $value->toArray() )
		);
	}
}
