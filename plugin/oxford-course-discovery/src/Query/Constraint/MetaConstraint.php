<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query\Constraint;

use InvalidArgumentException;

/**
 * A `meta_query` clause.
 *
 * Provided for completeness — third party filters over scalar meta (a price
 * band, a duration) can use it without needing a lookup table. The built-in
 * filters deliberately do not: see docs/PERFORMANCE.md for why meta queries
 * stop scaling.
 */
final readonly class MetaConstraint {

	private const OPERATORS = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'EXISTS', 'NOT EXISTS' );
	private const TYPES     = array( 'CHAR', 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'DATE', 'DATETIME' );

	/**
	 * @param string                                  $key      Meta key.
	 * @param string|int|float|list<string|int|float> $value    Comparison value.
	 * @param string                                  $operator Comparison operator.
	 * @param string                                  $type     Cast type.
	 *
	 * @throws InvalidArgumentException When the operator or cast type is unsupported.
	 */
	private function __construct(
		public string $key,
		public string|int|float|array $value,
		public string $operator = '=',
		public string $type = 'CHAR'
	) {
		if ( ! in_array( $operator, self::OPERATORS, true ) ) {
			throw new InvalidArgumentException( sprintf( '"%s" is not a supported meta operator.', $operator ) );
		}

		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new InvalidArgumentException( sprintf( '"%s" is not a supported meta cast type.', $type ) );
		}
	}

	/**
	 * @param string|int|float|list<string|int|float> $value Comparison value.
	 */
	public static function where( string $key, string|int|float|array $value, string $operator = '=', string $type = 'CHAR' ): self {
		return new self( $key, $value, $operator, $type );
	}

	/**
	 * @param list<string|int|float> $values Accepted values, OR'd.
	 */
	public static function anyOf( string $key, array $values, string $type = 'CHAR' ): self {
		return new self( $key, array_values( $values ), 'IN', $type );
	}

	public static function between( string $key, float $low, float $high ): self {
		return new self( $key, array( $low, $high ), 'BETWEEN', 'DECIMAL' );
	}

	public function identity(): string {
		return sprintf(
			'meta:%s:%s:%s',
			$this->key,
			$this->operator,
			is_array( $this->value ) ? implode( ',', $this->value ) : (string) $this->value
		);
	}

	/**
	 * @return array{key: string, value: mixed, compare: string, type: string}
	 */
	public function toClause(): array {
		return array(
			'key'     => $this->key,
			'value'   => $this->value,
			'compare' => $this->operator,
			'type'    => $this->type,
		);
	}
}
