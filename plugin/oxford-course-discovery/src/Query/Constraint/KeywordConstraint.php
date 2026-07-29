<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query\Constraint;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Schema;

/**
 * Free text matching over name, short description and long description.
 *
 * WordPress' `s` parameter uses unanchored `LIKE '%term%'` over `wp_posts` and
 * does not cover the short description at all, which lives in meta. This
 * matches the denormalised FULLTEXT index instead, falling back to `LIKE` on
 * the same table where FULLTEXT is unavailable. The same expression yields the
 * relevance score used for ordering, so ranking costs no extra query.
 */
final readonly class KeywordConstraint implements SqlConstraint {

	private const MIN_TERM_LENGTH = 2;

	private function __construct( public string $term ) {
	}

	public static function of( string $term ): self {
		return new self( trim( $term ) );
	}

	public function identity(): string {
		return 'keyword:' . $this->term;
	}

	public function isEmpty(): bool {
		return '' === $this->term;
	}

	public function toSql( DatabaseGateway $db ): string {
		if ( $this->isEmpty() ) {
			return '';
		}

		$table = $db->table( Schema::SEARCH_INDEX );

		if ( $this->canUseFullText( $db ) ) {
			return $db->prepare(
				sprintf(
					'%s.ID IN ( SELECT %s FROM %s WHERE MATCH ( name, short_description, long_description ) AGAINST ( %%s IN BOOLEAN MODE ) )',
					$db->postsTable(),
					Schema::COURSE_COLUMN,
					$table
				),
				array( $this->booleanModeQuery() )
			);
		}

		$like = '%' . $this->escapeLike( $db, $this->term ) . '%';

		return $db->prepare(
			sprintf(
				'%s.ID IN ( SELECT %s FROM %s WHERE name LIKE %%s OR short_description LIKE %%s OR long_description LIKE %%s )',
				$db->postsTable(),
				Schema::COURSE_COLUMN,
				$table
			),
			array( $like, $like, $like )
		);
	}

	/**
	 * Expression usable in `ORDER BY`, or null when relevance is not available.
	 */
	public function relevanceExpression( DatabaseGateway $db ): ?string {
		if ( $this->isEmpty() || ! $this->canUseFullText( $db ) ) {
			return null;
		}

		return $db->prepare(
			sprintf(
				'( SELECT MATCH ( s.name, s.short_description, s.long_description ) AGAINST ( %%s IN BOOLEAN MODE ) FROM %s s WHERE s.%s = %s.ID )',
				$db->table( Schema::SEARCH_INDEX ),
				Schema::COURSE_COLUMN,
				$db->postsTable()
			),
			array( $this->booleanModeQuery() )
		);
	}

	private function canUseFullText( DatabaseGateway $db ): bool {
		return $db->supportsFullText() && $db->tableExists( Schema::SEARCH_INDEX );
	}

	/**
	 * Turn a human phrase into a BOOLEAN MODE expression.
	 *
	 * Every word is required and prefix-matched (`+design*`): more words means
	 * fewer, better results, and "desig" still finds "design". Operators the
	 * user typed are stripped so a stray `-` or `"` cannot invert or break the
	 * query.
	 */
	public function booleanModeQuery(): string {
		$sanitised = preg_replace( '/[+\-><\(\)~*\"@]+/', ' ', $this->term ) ?? '';
		$split     = preg_split( '/\s+/', trim( $sanitised ) );
		$words     = false === $split ? array() : $split;
		$terms     = array();

		foreach ( $words as $word ) {
			if ( mb_strlen( $word ) < self::MIN_TERM_LENGTH ) {
				continue;
			}

			$terms[] = '+' . $word . '*';
		}

		return implode( ' ', $terms );
	}

	private function escapeLike( DatabaseGateway $db, string $value ): string {
		if ( function_exists( 'esc_sql' ) && $db instanceof \Oxford\CourseDiscovery\Database\WpdbGateway ) {
			return $db->wpdb()->esc_like( $value );
		}

		return addcslashes( $value, '%_\\' );
	}
}
