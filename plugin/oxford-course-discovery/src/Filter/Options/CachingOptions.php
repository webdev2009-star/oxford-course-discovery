<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Options;

use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Support\Hooks;

/**
 * Transient backed decorator around any {@see OptionsSource}.
 *
 * Facet queries are the same for every visitor and change only when content
 * changes, so they are the cheapest meaningful caching win in the system. The
 * cache group is versioned: the indexer bumps the version on every write, so
 * invalidation is a single option update rather than a hunt for keys.
 */
final class CachingOptions implements OptionsSource {

	private const VERSION_OPTION = 'oxcd_options_cache_version';
	private const DEFAULT_TTL    = 15 * MINUTE_IN_SECONDS;

	public function __construct(
		private readonly OptionsSource $inner,
		private readonly int $ttl = self::DEFAULT_TTL
	) {
	}

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		$key = $this->cacheKey( $criteria );

		/**
		 * @see Hooks::OPTIONS_CACHE_TTL
		 */
		$ttl = (int) apply_filters( Hooks::OPTIONS_CACHE_TTL, $this->ttl, $key );

		if ( $ttl <= 0 ) {
			return $this->inner->options( $criteria );
		}

		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $this->hydrate( $cached );
		}

		$options = $this->inner->options( $criteria );

		set_transient( $key, $options->toArrays(), $ttl );

		return $options;
	}

	public function cacheKey( SearchCriteria $criteria ): string {
		return sprintf(
			'oxcd_opt_%d_%s',
			self::version(),
			md5( $this->inner->cacheKey( $criteria ) )
		);
	}

	/**
	 * Invalidate every cached option set.
	 */
	public static function flush(): void {
		update_option( self::VERSION_OPTION, self::version() + 1, false );
	}

	private static function version(): int {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * @param list<array<string, mixed>> $rows Serialised options.
	 */
	private function hydrate( array $rows ): FilterOptionCollection {
		$options = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['value'] ) ) {
				continue;
			}

			$children = isset( $row['children'] ) && is_array( $row['children'] ) && array() !== $row['children']
				? $this->hydrate( $row['children'] )
				: null;

			$options[] = FilterOption::create(
				(string) $row['value'],
				(string) ( $row['label'] ?? '' ),
				isset( $row['count'] ) ? (int) $row['count'] : null,
				$children
			);
		}

		return FilterOptionCollection::of( $options );
	}
}
