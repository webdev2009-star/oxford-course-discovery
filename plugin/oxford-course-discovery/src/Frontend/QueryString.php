<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Frontend;

use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Builds the query strings that make a search shareable.
 *
 * Links are emitted path-relative (`?q=design&paged=2`) so the same markup is
 * correct whether it was rendered by the shortcode or injected by the REST
 * response, without the server having to know which page embedded the finder.
 */
final class QueryString {

	/**
	 * @param array<string, mixed> $vars Query variables.
	 */
	public static function url( array $vars ): string {
		if ( array() === $vars ) {
			return '?';
		}

		$query = http_build_query( $vars );

		// `a%5B0%5D=x` -> `a%5B%5D=x`: repeated parameters read better in a URL
		// bar and round trip identically.
		return '?' . (string) preg_replace( '/%5B\d+%5D/', '%5B%5D', $query );
	}

	public static function forCriteria( SearchCriteria $criteria ): string {
		return self::url( $criteria->toQueryVars() );
	}

	public static function forPage( SearchCriteria $criteria, int $page ): string {
		return self::url( $criteria->withPage( $page )->toQueryVars() );
	}

	/**
	 * The same search with one value removed — powers the "remove filter" chips.
	 */
	public static function withoutValue( SearchCriteria $criteria, string $filterKey, string $value ): string {
		$vars = $criteria->toQueryVars();

		if ( isset( $vars[ $filterKey ] ) && is_array( $vars[ $filterKey ] ) ) {
			$vars[ $filterKey ] = array_values(
				array_filter( $vars[ $filterKey ], static fn( string $candidate ): bool => $candidate !== $value )
			);

			if ( array() === $vars[ $filterKey ] ) {
				unset( $vars[ $filterKey ] );
			}
		}

		unset( $vars['paged'] );

		return self::url( $vars );
	}

	private function __construct() {
	}
}
