<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Support;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Immutable, runtime type checked collection.
 *
 * PHP has no generics, so the element type is asserted at construction and
 * documented with `@template` annotations for static analysis.
 *
 * @template TItem of object
 * @implements IteratorAggregate<int, TItem>
 */
abstract class TypedCollection implements IteratorAggregate, Countable {

	/**
	 * @var list<TItem>
	 */
	protected readonly array $items;

	/**
	 * @param iterable<TItem> $items Items of the declared element type.
	 *
	 * @throws InvalidArgumentException When an item is not of the declared type.
	 */
	final public function __construct( iterable $items = array() ) {
		$expected = static::itemType();
		$values   = array();

		foreach ( $items as $item ) {
			if ( ! $item instanceof $expected ) {
				throw new InvalidArgumentException(
					sprintf(
						'%s only accepts instances of %s, %s given.',
						static::class,
						$expected,
						get_debug_type( $item )
					)
				);
			}

			$values[] = $item;
		}

		$this->items = $values;
	}

	/**
	 * Fully qualified class (or interface) name every element must satisfy.
	 *
	 * @return class-string<TItem>
	 */
	abstract protected static function itemType(): string;

	/**
	 * @param iterable<TItem> $items Items to wrap.
	 *
	 * @return static
	 */
	public static function of( iterable $items ): static {
		return new static( $items );
	}

	/**
	 * @return static
	 */
	public static function empty(): static {
		return new static( array() );
	}

	/**
	 * @param TItem ...$items Items to append.
	 *
	 * @return static New collection; the receiver is never mutated.
	 */
	public function with( object ...$items ): static {
		return new static( array( ...$this->items, ...$items ) );
	}

	/**
	 * @param static $other Collection to append.
	 *
	 * @return static
	 */
	public function merge( self $other ): static {
		return new static( array( ...$this->items, ...$other->items ) );
	}

	/**
	 * @param callable(TItem, int): bool $predicate Filter callback.
	 *
	 * @return static
	 */
	public function filter( callable $predicate ): static {
		$kept = array();

		foreach ( $this->items as $index => $item ) {
			if ( $predicate( $item, $index ) ) {
				$kept[] = $item;
			}
		}

		return new static( $kept );
	}

	/**
	 * @template TMapped
	 *
	 * @param callable(TItem, int): TMapped $callback Mapper.
	 *
	 * @return list<TMapped> Plain list: the mapped type is no longer TItem.
	 */
	public function map( callable $callback ): array {
		$mapped = array();

		foreach ( $this->items as $index => $item ) {
			$mapped[] = $callback( $item, $index );
		}

		return $mapped;
	}

	/**
	 * @param callable(TItem, TItem): int $comparator Sort comparator.
	 *
	 * @return static
	 */
	public function sorted( callable $comparator ): static {
		$items = $this->items;
		usort( $items, $comparator );

		return new static( $items );
	}

	/**
	 * @param callable(TItem): (string|int) $identity Identity extractor.
	 *
	 * @return static
	 */
	public function unique( callable $identity ): static {
		$seen = array();
		$kept = array();

		foreach ( $this->items as $item ) {
			$key = $identity( $item );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$kept[]       = $item;
		}

		return new static( $kept );
	}

	/**
	 * @param callable(TItem): bool $predicate Predicate.
	 */
	public function contains( callable $predicate ): bool {
		foreach ( $this->items as $item ) {
			if ( $predicate( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return TItem|null
	 */
	public function first(): ?object {
		return $this->items[0] ?? null;
	}

	/**
	 * @return list<TItem>
	 */
	public function toArray(): array {
		return $this->items;
	}

	public function isEmpty(): bool {
		return array() === $this->items;
	}

	public function count(): int {
		return count( $this->items );
	}

	/**
	 * @return Traversable<int, TItem>
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->items );
	}
}
