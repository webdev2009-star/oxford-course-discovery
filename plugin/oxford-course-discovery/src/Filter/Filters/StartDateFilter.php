<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Filters;

use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;
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
 * Multi-select combobox over course intakes.
 *
 * Values travel as the canonical `{month}-{year}` string the user sees, and
 * are converted to the integer sort key the lookup table indexes at the last
 * possible moment. Anything that does not parse is dropped in
 * {@see self::normalise()}, so a hand-edited URL cannot reach the query layer.
 */
final class StartDateFilter implements Filter, ProvidesOptions, ContributesQuery, NormalisesValue {

	public const KEY = 'start_date';

	private readonly FilterDefinition $definition;

	public function __construct(
		private readonly OptionsSource $optionsSource,
		?FilterDefinition $definition = null
	) {
		$this->definition = $definition ?? FilterDefinition::create(
			self::KEY,
			__( 'Start dates', 'oxford-course-discovery' ),
			FilterControl::Combobox,
			40
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
		$dates = array();

		foreach ( $value->toArray() as $raw ) {
			$date = StartDate::tryFromString( $raw );

			if ( $date instanceof StartDate ) {
				$dates[] = $date;
			}
		}

		usort( $dates, static fn( StartDate $a, StartDate $b ): int => $a->sortKey() <=> $b->sortKey() );

		return FilterValue::fromIterable(
			array_map( static fn( StartDate $date ): string => $date->toString(), $dates )
		);
	}

	public function contribute( FilterValue $value, QueryPlan $plan, SearchCriteria $criteria ): QueryPlan {
		$sortKeys = array();

		foreach ( $value->toArray() as $raw ) {
			$date = StartDate::tryFromString( $raw );

			if ( $date instanceof StartDate ) {
				$sortKeys[] = $date->sortKey();
			}
		}

		if ( array() === $sortKeys ) {
			return $plan;
		}

		return $plan->withSqlConstraint(
			LookupConstraint::integers( Schema::START_DATES, 'sort_key', $sortKeys )
		);
	}
}
