<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query;

use Oxford\CourseDiscovery\Domain\Course;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Search\SearchResults;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\WordPress\CourseMapper;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use WP_Post;
use WP_Query;

/**
 * `WP_Query` backed implementation of {@see CourseRepository}.
 *
 * Lookup-table constraints have no `WP_Query` representation, so they are
 * injected through `posts_where` / `posts_orderby`. Two details make that safe
 * alongside other plugins filtering the same hooks: a one-shot token in the
 * query vars means the callbacks ignore every query but ours (no "is this the
 * main query?" guessing), and they are removed in a `finally` so a fatal inside
 * `WP_Query` cannot leave a stray filter attached.
 */
final class WpCourseRepository implements CourseRepository {

	private const TOKEN_VAR = 'oxcd_token';

	private int $tokenCounter = 0;

	public function __construct(
		private readonly QueryPlanner $planner,
		private readonly QueryCompiler $compiler,
		private readonly CourseMapper $mapper
	) {
	}

	public function search( SearchCriteria $criteria ): SearchResults {
		$plan     = $this->planner->plan( $criteria );
		$compiled = $this->compiler->compile( $plan );
		$query    = $this->run( $compiled );

		$posts = array_values( array_filter( $query->posts, static fn( $post ): bool => $post instanceof WP_Post ) );

		$results = SearchResults::create(
			$this->mapper->mapMany( $posts ),
			(int) $query->found_posts,
			$plan->pagination,
			$criteria
		);

		/**
		 * @see Hooks::RESULTS
		 */
		$filtered = apply_filters( Hooks::RESULTS, $results, $criteria );

		return $filtered instanceof SearchResults ? $filtered : $results;
	}

	public function count( SearchCriteria $criteria ): int {
		$plan     = $this->planner->plan( $criteria );
		$compiled = $this->compiler->compile( $plan );

		$counting = new CompiledQuery(
			array(
				...$compiled->args,
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'paged'                  => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			),
			$compiled->whereFragments,
			''
		);

		return (int) $this->run( $counting )->found_posts;
	}

	public function find( CourseId $id ): ?Course {
		$post = get_post( $id->toInt() );

		if ( ! $post instanceof WP_Post || CoursePostType::NAME !== $post->post_type ) {
			return null;
		}

		return $this->mapper->map( $post );
	}

	/**
	 * Execute a compiled query with its SQL fragments attached.
	 */
	private function run( CompiledQuery $compiled ): WP_Query {
		$token = sprintf( 'oxcd_%d_%d', getmypid(), ++$this->tokenCounter );
		$args  = array(
			...$compiled->args,
			self::TOKEN_VAR => $token,
		);

		$where = function ( string $sql, WP_Query $query ) use ( $token, $compiled ): string {
			return $query->get( self::TOKEN_VAR ) === $token
				? $sql . $compiled->whereClause()
				: $sql;
		};

		$orderby = function ( string $sql, WP_Query $query ) use ( $token, $compiled ): string {
			return $query->get( self::TOKEN_VAR ) === $token && $compiled->hasOrderBy()
				? $compiled->orderBy
				: $sql;
		};

		add_filter( 'posts_where', $where, 10, 2 );
		add_filter( 'posts_orderby', $orderby, 10, 2 );

		try {
			return new WP_Query( $args );
		} finally {
			remove_filter( 'posts_where', $where, 10 );
			remove_filter( 'posts_orderby', $orderby, 10 );
		}
	}
}
