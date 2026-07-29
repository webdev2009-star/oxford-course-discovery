<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query\Constraint;

use InvalidArgumentException;

/**
 * A term constraint delegated to WordPress' own `tax_query`.
 *
 * Term relationships are already well indexed, so there is nothing to gain
 * from bypassing them. One constraint = one clause; the compiler sets the
 * relation between clauses to `AND`.
 */
final readonly class TaxonomyConstraint {

	/**
	 * @param string       $taxonomy         Taxonomy name.
	 * @param list<string> $terms            Term slugs (or IDs when $field is 'term_id').
	 * @param string       $field            `slug` or `term_id`.
	 * @param bool         $includeChildren  Whether descendants also match.
	 *
	 * @throws InvalidArgumentException When no terms are given, or the field is unsupported.
	 */
	private function __construct(
		public string $taxonomy,
		public array $terms,
		public string $field = 'slug',
		public bool $includeChildren = true
	) {
		if ( array() === $terms ) {
			throw new InvalidArgumentException( 'A taxonomy constraint needs at least one term.' );
		}

		if ( ! in_array( $field, array( 'slug', 'term_id', 'name' ), true ) ) {
			throw new InvalidArgumentException( sprintf( '"%s" is not a supported term field.', $field ) );
		}
	}

	/**
	 * @param list<string> $slugs Term slugs.
	 */
	public static function slugs( string $taxonomy, array $slugs, bool $includeChildren = true ): self {
		return new self( $taxonomy, array_values( $slugs ), 'slug', $includeChildren );
	}

	/**
	 * @param list<int> $ids Term IDs.
	 */
	public static function ids( string $taxonomy, array $ids, bool $includeChildren = true ): self {
		return new self(
			$taxonomy,
			array_values( array_map( 'strval', $ids ) ),
			'term_id',
			$includeChildren
		);
	}

	public function identity(): string {
		return sprintf( 'tax:%s:%s:%s', $this->taxonomy, $this->field, implode( ',', $this->terms ) );
	}

	/**
	 * @return array{taxonomy: string, field: string, terms: list<string>, operator: string, include_children: bool}
	 */
	public function toClause(): array {
		return array(
			'taxonomy'         => $this->taxonomy,
			'field'            => $this->field,
			'terms'            => $this->terms,
			'operator'         => 'IN',
			'include_children' => $this->includeChildren,
		);
	}
}
