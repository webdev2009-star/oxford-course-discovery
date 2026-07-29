<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

/**
 * The minimum a filter must know about itself: who it is and how it is drawn.
 *
 * Everything else a filter *might* do — offer options, constrain the query,
 * rewrite the criteria, validate input — lives in separate capability
 * interfaces ({@see ProvidesOptions}, {@see ContributesQuery},
 * {@see TransformsCriteria}, {@see NormalisesValue}). A filter opts into only
 * what it needs and the pipeline discovers capabilities with `instanceof`,
 * which is what makes new filters additive: a price filter that constrains but
 * offers no options is one class implementing two interfaces, with no edit to
 * any existing filter.
 */
interface Filter {

	/**
	 * Stable identity, also the request parameter name.
	 */
	public function key(): FilterKey;

	/**
	 * Human readable label for the control's legend or `<label>`.
	 */
	public function label(): string;

	/**
	 * How the front end should render this filter.
	 */
	public function control(): FilterControl;

	/**
	 * Display and execution order; lower runs and renders first.
	 */
	public function priority(): int;
}
