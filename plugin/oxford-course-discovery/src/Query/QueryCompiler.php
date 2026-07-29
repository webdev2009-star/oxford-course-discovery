<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Search\Ordering;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\WordPress\Fields\FieldKeys;

/**
 * Turns a {@see QueryPlan} into {@see CompiledQuery}.
 *
 * The single place where the domain meets SQL. Nothing above it knows about
 * `WP_Query`, `tax_query` or `ORDER BY`; nothing below it knows about filters.
 */
final class QueryCompiler {

	public function __construct( private readonly DatabaseGateway $db ) {
	}

	public function compile( QueryPlan $plan ): CompiledQuery {
		$args = array(
			'post_type'              => $plan->postType,
			'post_status'            => $plan->postStatus,
			'posts_per_page'         => $plan->pagination->perPage,
			'paged'                  => $plan->pagination->page,
			'offset'                 => null,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			// Ordering is injected through `posts_orderby` so it can reference
			// correlated subqueries WP_Query cannot express.
			'orderby'                => 'none',
		);

		unset( $args['offset'] );

		if ( array() !== $plan->taxonomyConstraints ) {
			$args['tax_query'] = array_merge(
				array( 'relation' => 'AND' ),
				array_map(
					static fn( $constraint ): array => $constraint->toClause(),
					$plan->taxonomyConstraints
				)
			);
		}

		if ( array() !== $plan->metaConstraints ) {
			$args['meta_query'] = array_merge(
				array( 'relation' => 'AND' ),
				array_map(
					static fn( $constraint ): array => $constraint->toClause(),
					$plan->metaConstraints
				)
			);
		}

		$args = array( ...$args, ...$plan->extraArgs );

		/**
		 * @see Hooks::QUERY_ARGS
		 */
		$args = (array) apply_filters( Hooks::QUERY_ARGS, $args, $plan );

		return new CompiledQuery( $args, $this->whereFragments( $plan ), $this->orderBy( $plan ) );
	}

	/**
	 * @return list<string>
	 */
	private function whereFragments( QueryPlan $plan ): array {
		$fragments = array();

		foreach ( $plan->sqlConstraints as $constraint ) {
			$sql = $constraint->toSql( $this->db );

			if ( '' !== trim( $sql ) ) {
				$fragments[] = $sql;
			}
		}

		/**
		 * @see Hooks::QUERY_WHERE
		 */
		return array_values( (array) apply_filters( Hooks::QUERY_WHERE, $fragments, $plan ) );
	}

	private function orderBy( QueryPlan $plan ): string {
		$ordering  = $plan->ordering;
		$direction = $ordering->direction->toSql();
		$posts     = $this->db->postsTable();

		$orderBy = match ( $ordering->key ) {
			Ordering::RELEVANCE  => $this->relevanceOrderBy( $plan, $direction ),
			Ordering::NAME       => sprintf( '%s.post_title %s', $posts, $direction ),
			Ordering::NEWEST     => sprintf( '%s.post_date %s', $posts, $direction ),
			Ordering::PRICE      => $this->priceOrderBy( $direction ),
			Ordering::START_DATE => $this->startDateOrderBy( $direction ),
			default              => '',
		};

		// Stable tie-break so pagination cannot repeat or drop a row.
		if ( '' !== $orderBy ) {
			$orderBy .= sprintf( ', %s.ID ASC', $posts );
		}

		/**
		 * @see Hooks::QUERY_ORDERBY
		 */
		return (string) apply_filters( Hooks::QUERY_ORDERBY, $orderBy, $ordering, $plan );
	}

	/**
	 * Soonest upcoming intake first; courses with no future intake sort last
	 * rather than first, which is what `NULL` would otherwise do in `ASC`.
	 */
	private function startDateOrderBy( string $direction ): string {
		$sql = sprintf(
			'COALESCE( ( SELECT MIN( sd.sort_key ) FROM %s sd WHERE sd.%s = %s.ID AND sd.sort_key >= %%d ), %%d ) %s',
			$this->db->table( Schema::START_DATES ),
			Schema::COURSE_COLUMN,
			$this->db->postsTable(),
			$direction
		);

		return $this->db->prepare( $sql, array( $this->currentSortKey(), 999999 ) );
	}

	private function priceOrderBy( string $direction ): string {
		global $wpdb;

		$metaTable = $wpdb instanceof \wpdb ? $wpdb->postmeta : 'wp_postmeta';

		$sql = sprintf(
			'COALESCE( ( SELECT CAST( pm.meta_value AS DECIMAL(12,2) ) FROM %s pm WHERE pm.post_id = %s.ID AND pm.meta_key = %%s LIMIT 1 ), 0 ) %s',
			$metaTable,
			$this->db->postsTable(),
			$direction
		);

		return $this->db->prepare( $sql, array( FieldKeys::PRICE ) );
	}

	private function relevanceOrderBy( QueryPlan $plan, string $direction ): string {
		$keyword = $plan->keywordConstraint();

		if ( null === $keyword ) {
			return $this->startDateOrderBy( 'ASC' );
		}

		$expression = $keyword->relevanceExpression( $this->db );

		// Without FULLTEXT there is no score to sort on; fall back to name so
		// results are at least deterministic.
		return null === $expression
			? sprintf( '%s.post_title ASC', $this->db->postsTable() )
			: sprintf( '%s %s', $expression, $direction );
	}

	private function currentSortKey(): int {
		return (int) gmdate( 'Y' ) * 100 + (int) gmdate( 'n' );
	}
}
