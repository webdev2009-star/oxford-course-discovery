<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query\Constraint;

use InvalidArgumentException;
use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Schema;

/**
 * "The course appears in this lookup table with one of these values."
 *
 * A semi-join (`ID IN (SELECT ...)`) rather than an `INNER JOIN`: joining a
 * one-to-many table multiplies result rows and forces `DISTINCT`, which
 * defeats index-ordered scans. Values inside one constraint are OR'd by `IN`;
 * separate constraints are AND'd by the compiler.
 */
final readonly class LookupConstraint implements SqlConstraint {

	/**
	 * @param string           $table       Unprefixed lookup table name.
	 * @param string           $column      Column to match on.
	 * @param list<int|string> $values      Accepted values; OR'd together.
	 * @param string           $courseColumn Column holding the course ID.
	 *
	 * @throws InvalidArgumentException When no values are given, or an identifier is unsafe.
	 */
	private function __construct(
		public string $table,
		public string $column,
		public array $values,
		public string $courseColumn = Schema::COURSE_COLUMN
	) {
		if ( array() === $values ) {
			throw new InvalidArgumentException( 'A lookup constraint needs at least one value.' );
		}

		foreach ( array( $table, $column, $courseColumn ) as $identifier ) {
			if ( 1 !== preg_match( '/^[a-z_][a-z0-9_]*$/i', $identifier ) ) {
				throw new InvalidArgumentException(
					sprintf( '"%s" is not a safe SQL identifier.', $identifier )
				);
			}
		}
	}

	/**
	 * @param list<int|string> $values Accepted values.
	 */
	public static function in( string $table, string $column, array $values ): self {
		return new self( $table, $column, array_values( $values ) );
	}

	/**
	 * @param list<int> $ids Course IDs.
	 */
	public static function integers( string $table, string $column, array $ids ): self {
		return new self( $table, $column, array_values( array_map( 'intval', $ids ) ) );
	}

	public function identity(): string {
		return sprintf( '%s.%s:%s', $this->table, $this->column, implode( ',', $this->values ) );
	}

	public function toSql( DatabaseGateway $db ): string {
		$placeholders = implode(
			', ',
			array_map(
				static fn( int|string $value ): string => is_int( $value ) ? '%d' : '%s',
				$this->values
			)
		);

		$sql = sprintf(
			'%s.ID IN ( SELECT %s FROM %s WHERE %s IN ( %s ) )',
			$db->postsTable(),
			$this->courseColumn,
			$db->table( $this->table ),
			$this->column,
			$placeholders
		);

		return $db->prepare( $sql, $this->values );
	}
}
