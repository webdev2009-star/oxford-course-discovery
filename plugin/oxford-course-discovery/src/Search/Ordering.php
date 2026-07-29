<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

use InvalidArgumentException;

/**
 * How a result set should be sorted.
 *
 * The key is an opaque identifier, not a column name. Translating it into SQL
 * is the compiler's job, and third parties can register their own key plus a
 * matching {@see \Oxford\CourseDiscovery\Support\Hooks::QUERY_ORDERBY} handler
 * without the domain knowing anything about the storage.
 */
final readonly class Ordering {

	public const RELEVANCE  = 'relevance';
	public const START_DATE = 'start_date';
	public const NAME       = 'name';
	public const PRICE      = 'price';
	public const NEWEST     = 'newest';

	/**
	 * @param bool $isDefault True only for the implicit ordering nobody asked
	 *                        for. Lets a transformer (the keyword filter
	 *                        switching to relevance) refine the sort without
	 *                        overriding an explicit user choice.
	 *
	 * @throws InvalidArgumentException When the ordering key is malformed.
	 */
	private function __construct(
		public string $key,
		public SortDirection $direction,
		public bool $isDefault = false
	) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $key ) ) {
			throw new InvalidArgumentException( sprintf( '"%s" is not a valid ordering key.', $key ) );
		}
	}

	public static function of( string $key, SortDirection $direction = SortDirection::Ascending ): self {
		return new self( $key, $direction );
	}

	/**
	 * The default ordering: soonest intake first, which is what a prospective
	 * student is usually shopping for.
	 */
	public static function default(): self {
		return new self( self::START_DATE, SortDirection::Ascending, true );
	}

	public static function relevance(): self {
		return new self( self::RELEVANCE, SortDirection::Descending );
	}

	/**
	 * @param array<string, string> $allowed Registered key => label map.
	 */
	public static function fromRequest( mixed $raw, array $allowed ): ?self {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$parts = explode( ':', strtolower( $raw ), 2 );
		$key   = $parts[0];

		if ( ! isset( $allowed[ $key ] ) ) {
			return null;
		}

		$direction = isset( $parts[1] ) && '' !== $parts[1]
			? SortDirection::fromRequest( $parts[1] )
			: Orderings::defaultDirectionFor( $key );

		return new self( $key, $direction );
	}

	public function is( string $key ): bool {
		return $this->key === $key;
	}

	/**
	 * Round trips through {@see self::fromRequest()}.
	 */
	public function toRequestValue(): string {
		return $this->key . ':' . $this->direction->value;
	}
}
