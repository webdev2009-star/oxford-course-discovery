<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query;

use Oxford\CourseDiscovery\Query\Constraint\KeywordConstraint;
use Oxford\CourseDiscovery\Query\Constraint\MetaConstraint;
use Oxford\CourseDiscovery\Query\Constraint\SqlConstraint;
use Oxford\CourseDiscovery\Query\Constraint\TaxonomyConstraint;
use Oxford\CourseDiscovery\Search\Ordering;
use Oxford\CourseDiscovery\Search\Pagination;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;

/**
 * A storage-agnostic description of the query to run.
 *
 * Filters build it up, each adding one constraint, and it is compiled to
 * `WP_Query` arguments and SQL in a single place. Immutable, so no filter can
 * undo another's work and the plan can be asserted on without a database; and
 * declarative, so `oxcd/query/plan` listeners rewrite constraints as data
 * rather than by string-munging SQL.
 */
final readonly class QueryPlan {

	/**
	 * @param string                   $postType            Post type to query.
	 * @param list<string>             $postStatus          Statuses to include.
	 * @param list<SqlConstraint>      $sqlConstraints      Lookup/keyword constraints.
	 * @param list<TaxonomyConstraint> $taxonomyConstraints Term constraints.
	 * @param list<MetaConstraint>     $metaConstraints     Meta constraints.
	 * @param Ordering                 $ordering            Requested ordering.
	 * @param Pagination               $pagination          Requested window.
	 * @param array<string, mixed>     $extraArgs           Raw WP_Query overrides.
	 */
	private function __construct(
		public string $postType,
		public array $postStatus,
		public array $sqlConstraints,
		public array $taxonomyConstraints,
		public array $metaConstraints,
		public Ordering $ordering,
		public Pagination $pagination,
		public array $extraArgs
	) {
	}

	public static function forCourses( ?Ordering $ordering = null, ?Pagination $pagination = null ): self {
		return new self(
			CoursePostType::NAME,
			array( 'publish' ),
			array(),
			array(),
			array(),
			$ordering ?? Ordering::default(),
			$pagination ?? Pagination::firstPage(),
			array()
		);
	}

	public function withSqlConstraint( SqlConstraint $constraint ): self {
		return $this->copy( sqlConstraints: array( ...$this->sqlConstraints, $constraint ) );
	}

	public function withTaxonomyConstraint( TaxonomyConstraint $constraint ): self {
		return $this->copy( taxonomyConstraints: array( ...$this->taxonomyConstraints, $constraint ) );
	}

	public function withMetaConstraint( MetaConstraint $constraint ): self {
		return $this->copy( metaConstraints: array( ...$this->metaConstraints, $constraint ) );
	}

	/**
	 * Drop constraints by identity prefix — the supported way for an extension
	 * to remove something a built-in filter added.
	 */
	public function withoutConstraintsMatching( string $identityPrefix ): self {
		$matches = static fn( object $constraint ): bool =>
			! str_starts_with( $constraint->identity(), $identityPrefix );

		return $this->copy(
			sqlConstraints: array_values( array_filter( $this->sqlConstraints, $matches ) ),
			taxonomyConstraints: array_values( array_filter( $this->taxonomyConstraints, $matches ) ),
			metaConstraints: array_values( array_filter( $this->metaConstraints, $matches ) ),
		);
	}

	public function withOrdering( Ordering $ordering ): self {
		return $this->copy( ordering: $ordering );
	}

	public function withPagination( Pagination $pagination ): self {
		return $this->copy( pagination: $pagination );
	}

	/**
	 * @param list<string> $statuses Post statuses.
	 */
	public function withPostStatus( array $statuses ): self {
		return $this->copy( postStatus: array_values( $statuses ) );
	}

	/**
	 * @param array<string, mixed> $args Raw WP_Query arguments merged last.
	 */
	public function withExtraArgs( array $args ): self {
		return $this->copy( extraArgs: array( ...$this->extraArgs, ...$args ) );
	}

	public function keywordConstraint(): ?KeywordConstraint {
		foreach ( $this->sqlConstraints as $constraint ) {
			if ( $constraint instanceof KeywordConstraint && ! $constraint->isEmpty() ) {
				return $constraint;
			}
		}

		return null;
	}

	public function hasKeyword(): bool {
		return $this->keywordConstraint() instanceof KeywordConstraint;
	}

	public function isUnconstrained(): bool {
		return array() === $this->sqlConstraints
			&& array() === $this->taxonomyConstraints
			&& array() === $this->metaConstraints;
	}

	/**
	 * Every constraint identity, sorted — a cache key for the result set.
	 *
	 * @return list<string>
	 */
	public function identities(): array {
		$identities = array_map(
			static fn( object $constraint ): string => $constraint->identity(),
			array( ...$this->sqlConstraints, ...$this->taxonomyConstraints, ...$this->metaConstraints )
		);

		sort( $identities );

		return array_values( $identities );
	}

	/**
	 * @param list<SqlConstraint>|null      $sqlConstraints      Replacement constraints.
	 * @param list<TaxonomyConstraint>|null $taxonomyConstraints Replacement constraints.
	 * @param list<MetaConstraint>|null     $metaConstraints     Replacement constraints.
	 * @param array<string, mixed>|null     $extraArgs           Replacement args.
	 * @param list<string>|null             $postStatus          Replacement statuses.
	 */
	private function copy(
		?array $sqlConstraints = null,
		?array $taxonomyConstraints = null,
		?array $metaConstraints = null,
		?Ordering $ordering = null,
		?Pagination $pagination = null,
		?array $extraArgs = null,
		?array $postStatus = null
	): self {
		return new self(
			$this->postType,
			$postStatus ?? $this->postStatus,
			$sqlConstraints ?? $this->sqlConstraints,
			$taxonomyConstraints ?? $this->taxonomyConstraints,
			$metaConstraints ?? $this->metaConstraints,
			$ordering ?? $this->ordering,
			$pagination ?? $this->pagination,
			$extraArgs ?? $this->extraArgs
		);
	}
}
