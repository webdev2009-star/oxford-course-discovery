<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

use Oxford\CourseDiscovery\Support\Hooks;

/**
 * The orderings offered in the UI and accepted from the request.
 *
 * An allow-list, so `?orderby=` can never reach SQL as an arbitrary string.
 * Extensions add a key here and a matching `oxcd/query/orderby` handler.
 */
final class Orderings {

	/**
	 * @return array<string, string> Key => label.
	 */
	public static function available(): array {
		$orderings = array(
			Ordering::START_DATE => __( 'Soonest start date', 'oxford-course-discovery' ),
			Ordering::RELEVANCE  => __( 'Relevance', 'oxford-course-discovery' ),
			Ordering::NAME       => __( 'Course name (A–Z)', 'oxford-course-discovery' ),
			Ordering::PRICE      => __( 'Price (low to high)', 'oxford-course-discovery' ),
			Ordering::NEWEST     => __( 'Recently added', 'oxford-course-discovery' ),
		);

		/**
		 * @see Hooks::ORDERINGS
		 */
		return (array) apply_filters( Hooks::ORDERINGS, $orderings );
	}

	/**
	 * The direction that makes sense for each ordering when the request does
	 * not specify one.
	 */
	public static function defaultDirectionFor( string $key ): SortDirection {
		return match ( $key ) {
			Ordering::RELEVANCE, Ordering::NEWEST => SortDirection::Descending,
			default => SortDirection::Ascending,
		};
	}

	private function __construct() {
	}
}
