<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

use RuntimeException;

/**
 * The set of filters the system knows about.
 *
 * Third parties add to it during {@see \Oxford\CourseDiscovery\Support\Hooks::REGISTER_FILTERS}:
 *
 * ```php
 * add_action( 'oxcd/filters/register', function ( FilterRegistry $registry ): void {
 *     $registry->register( new PriceRangeFilter() );
 * } );
 * ```
 *
 * Registration is guarded — silently replacing a filter another plugin owns is
 * a debugging nightmare — so overriding a built-in is an explicit `replace()`.
 */
final class FilterRegistry {

	/**
	 * @var array<string, Filter>
	 */
	private array $filters = array();

	/**
	 * Insertion order, used to break priority ties deterministically.
	 *
	 * @var array<string, int>
	 */
	private array $sequence = array();

	private int $counter = 0;

	/**
	 * @param iterable<Filter> $filters Initial filters.
	 */
	public function __construct( iterable $filters = array() ) {
		foreach ( $filters as $filter ) {
			$this->register( $filter );
		}
	}

	/**
	 * @throws RuntimeException When the key is already taken.
	 */
	public function register( Filter $filter ): self {
		$key = $filter->key()->value;

		if ( isset( $this->filters[ $key ] ) ) {
			throw new RuntimeException(
				sprintf(
					'A filter with the key "%s" is already registered (%s). Use replace() to override it.',
					$key,
					$this->filters[ $key ]::class
				)
			);
		}

		$this->filters[ $key ]  = $filter;
		$this->sequence[ $key ] = $this->counter++;

		return $this;
	}

	/**
	 * Register, overriding any existing filter with the same key.
	 */
	public function replace( Filter $filter ): self {
		$this->unregister( $filter->key() );

		return $this->register( $filter );
	}

	public function unregister( FilterKey $key ): self {
		unset( $this->filters[ $key->value ], $this->sequence[ $key->value ] );

		return $this;
	}

	public function has( FilterKey $key ): bool {
		return isset( $this->filters[ $key->value ] );
	}

	public function get( FilterKey $key ): ?Filter {
		return $this->filters[ $key->value ] ?? null;
	}

	/**
	 * @throws RuntimeException When the filter is not registered.
	 */
	public function require( FilterKey $key ): Filter {
		$filter = $this->get( $key );

		if ( ! $filter instanceof Filter ) {
			throw new RuntimeException( sprintf( 'No filter is registered for "%s".', $key->value ) );
		}

		return $filter;
	}

	/**
	 * All filters, ordered by priority then registration order.
	 *
	 * @return list<Filter>
	 */
	public function all(): array {
		$filters = array_values( $this->filters );

		usort(
			$filters,
			fn( Filter $a, Filter $b ): int => array(
				$a->priority(),
				$this->sequence[ $a->key()->value ],
			) <=> array(
				$b->priority(),
				$this->sequence[ $b->key()->value ],
			)
		);

		return $filters;
	}

	/**
	 * Filters implementing a given capability, in execution order.
	 *
	 * @template TCapability of object
	 *
	 * @param class-string<TCapability> $capability Capability interface.
	 *
	 * @return list<Filter&TCapability>
	 */
	public function providing( string $capability ): array {
		return array_values(
			array_filter(
				$this->all(),
				static fn( Filter $filter ): bool => $filter instanceof $capability
			)
		);
	}

	/**
	 * @return list<string>
	 */
	public function keys(): array {
		return array_map( static fn( Filter $filter ): string => $filter->key()->value, $this->all() );
	}

	public function count(): int {
		return count( $this->filters );
	}
}
