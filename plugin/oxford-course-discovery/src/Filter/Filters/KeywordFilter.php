<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Filters;

use Oxford\CourseDiscovery\Filter\ContributesQuery;
use Oxford\CourseDiscovery\Filter\Filter;
use Oxford\CourseDiscovery\Filter\FilterControl;
use Oxford\CourseDiscovery\Filter\FilterDefinition;
use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterValue;
use Oxford\CourseDiscovery\Filter\NormalisesValue;
use Oxford\CourseDiscovery\Filter\TransformsCriteria;
use Oxford\CourseDiscovery\Query\Constraint\KeywordConstraint;
use Oxford\CourseDiscovery\Query\QueryPlan;
use Oxford\CourseDiscovery\Search\Ordering;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Plain text search across name, short description and long description.
 *
 * Also the one filter that legitimately rewrites the whole search: when a
 * keyword is present and the user has not chosen a sort order, results are
 * ranked by relevance instead of by next intake. That behaviour lives in
 * {@see self::transform()} — a capability, not a special case in the pipeline.
 */
final class KeywordFilter implements Filter, ContributesQuery, NormalisesValue, TransformsCriteria {

	public const KEY = 'q';

	private const MAX_LENGTH = 120;

	private readonly FilterDefinition $definition;

	public function __construct( ?FilterDefinition $definition = null ) {
		$this->definition = $definition ?? FilterDefinition::create(
			self::KEY,
			__( 'Search courses', 'oxford-course-discovery' ),
			FilterControl::Text,
			10
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

	public function normalise( FilterValue $value ): FilterValue {
		$term = trim( (string) $value->first() );

		if ( '' === $term ) {
			return FilterValue::none();
		}

		return FilterValue::fromScalar( mb_substr( $term, 0, self::MAX_LENGTH ) );
	}

	public function contribute( FilterValue $value, QueryPlan $plan, SearchCriteria $criteria ): QueryPlan {
		$constraint = KeywordConstraint::of( (string) $value->first() );

		return $constraint->isEmpty() ? $plan : $plan->withSqlConstraint( $constraint );
	}

	public function transform( SearchCriteria $criteria ): SearchCriteria {
		$hasKeyword = ! $criteria->valueFor( $this->key() )->isEmpty();

		if ( ! $hasKeyword || ! $criteria->ordering->isDefault ) {
			return $criteria;
		}

		return $criteria->withOrdering( Ordering::relevance() );
	}
}
