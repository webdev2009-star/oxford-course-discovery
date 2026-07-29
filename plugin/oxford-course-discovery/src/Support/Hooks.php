<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Support;

/**
 * The complete public extension surface of the plugin.
 *
 * Every hook is declared here so the integration contract lives in one place
 * and can be asserted against in tests. Names use the `oxcd/` namespace and
 * a `noun/verb` shape.
 */
final class Hooks {

	/**
	 * Action: register filters.
	 *
	 * @param \Oxford\CourseDiscovery\Filter\FilterRegistry $registry Mutable registry.
	 */
	public const REGISTER_FILTERS = 'oxcd/filters/register';

	/**
	 * Filter: the ordered list of filters exposed to the UI and query pipeline.
	 *
	 * @param list<\Oxford\CourseDiscovery\Filter\Filter> $filters Filters.
	 */
	public const FILTERS = 'oxcd/filters';

	/**
	 * Filter: the options rendered for a single filter control.
	 *
	 * @param \Oxford\CourseDiscovery\Filter\FilterOptionCollection $options  Options.
	 * @param \Oxford\CourseDiscovery\Filter\FilterKey              $key      Filter key.
	 * @param \Oxford\CourseDiscovery\Search\SearchCriteria         $criteria Current criteria.
	 */
	public const FILTER_OPTIONS = 'oxcd/filter/options';

	/**
	 * Filter: raw request payload before it is parsed into criteria.
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public const REQUEST = 'oxcd/criteria/request';

	/**
	 * Filter: the immutable search criteria, after parsing, before querying.
	 *
	 * @param \Oxford\CourseDiscovery\Search\SearchCriteria $criteria Criteria.
	 */
	public const CRITERIA = 'oxcd/criteria';

	/**
	 * Filter: the query plan produced by the filter pipeline.
	 *
	 * @param \Oxford\CourseDiscovery\Query\QueryPlan      $plan     Plan.
	 * @param \Oxford\CourseDiscovery\Search\SearchCriteria $criteria Criteria.
	 */
	public const QUERY_PLAN = 'oxcd/query/plan';

	/**
	 * Filter: the compiled WP_Query arguments.
	 *
	 * @param array<string, mixed>                          $args     Query args.
	 * @param \Oxford\CourseDiscovery\Query\QueryPlan       $plan     Plan.
	 * @param \Oxford\CourseDiscovery\Search\SearchCriteria $criteria Criteria.
	 */
	public const QUERY_ARGS = 'oxcd/query/args';

	/**
	 * Filter: SQL fragments appended to the WHERE clause of a discovery query.
	 *
	 * @param list<string>                            $fragments SQL fragments.
	 * @param \Oxford\CourseDiscovery\Query\QueryPlan $plan      Plan.
	 */
	public const QUERY_WHERE = 'oxcd/query/where';

	/**
	 * Filter: the ORDER BY expression for a discovery query.
	 *
	 * @param string                                  $orderby ORDER BY expression.
	 * @param \Oxford\CourseDiscovery\Search\Ordering $ordering Requested ordering.
	 * @param \Oxford\CourseDiscovery\Query\QueryPlan $plan     Plan.
	 */
	public const QUERY_ORDERBY = 'oxcd/query/orderby';

	/**
	 * Filter: registered orderings, keyed by identifier.
	 *
	 * @param array<string, string> $orderings Identifier => human label.
	 */
	public const ORDERINGS = 'oxcd/orderings';

	/**
	 * Filter: a hydrated Course entity.
	 *
	 * @param \Oxford\CourseDiscovery\Domain\Course $course Course.
	 * @param \WP_Post                              $post   Source post.
	 */
	public const COURSE = 'oxcd/course';

	/**
	 * Filter: the price object for a course, so ranges or multi price models can
	 * replace the default single price.
	 *
	 * @param \Oxford\CourseDiscovery\Domain\ValueObject\Price|null $price Price.
	 * @param int                                                   $id    Course ID.
	 */
	public const COURSE_PRICE = 'oxcd/course/price';

	/**
	 * Filter: search results before they are returned to the caller.
	 *
	 * @param \Oxford\CourseDiscovery\Search\SearchResults  $results  Results.
	 * @param \Oxford\CourseDiscovery\Search\SearchCriteria $criteria Criteria.
	 */
	public const RESULTS = 'oxcd/results';

	/**
	 * Filter: template candidates resolved for a view.
	 *
	 * @param list<string> $candidates Absolute paths, first readable wins.
	 * @param string       $view       View name.
	 */
	public const TEMPLATE_CANDIDATES = 'oxcd/template/candidates';

	/**
	 * Action: fired after the lookup tables for a course have been rebuilt.
	 *
	 * @param int $course_id Course ID.
	 */
	public const COURSE_INDEXED = 'oxcd/course/indexed';

	/**
	 * Filter: cache lifetime, in seconds, for facet option queries. Return 0 to
	 * disable caching.
	 *
	 * @param int    $ttl Seconds.
	 * @param string $key Cache key.
	 */
	public const OPTIONS_CACHE_TTL = 'oxcd/cache/options_ttl';

	private function __construct() {
	}
}
