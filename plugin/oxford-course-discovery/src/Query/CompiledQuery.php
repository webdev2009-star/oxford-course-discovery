<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query;

/**
 * The output of {@see QueryCompiler}: everything needed to run one search.
 *
 * Split into "arguments WP_Query understands" and "SQL we have to inject
 * ourselves", because the semi-joins against our lookup tables have no
 * `WP_Query` vocabulary. Keeping them in a value object means the repository's
 * only job is to attach them to the right query instance and detach again.
 */
final readonly class CompiledQuery {

	/**
	 * @param array<string, mixed> $args           WP_Query arguments.
	 * @param list<string>         $whereFragments Boolean expressions AND'd onto WHERE.
	 * @param string               $orderBy        ORDER BY expression, may be empty.
	 */
	public function __construct(
		public array $args,
		public array $whereFragments = array(),
		public string $orderBy = ''
	) {
	}

	public function hasWhereFragments(): bool {
		return array() !== $this->whereFragments;
	}

	public function hasOrderBy(): bool {
		return '' !== $this->orderBy;
	}

	/**
	 * The fragments as a single expression, ready to append to a WHERE clause.
	 */
	public function whereClause(): string {
		if ( ! $this->hasWhereFragments() ) {
			return '';
		}

		return ' AND ( ' . implode( ' ) AND ( ', $this->whereFragments ) . ' ) ';
	}
}
