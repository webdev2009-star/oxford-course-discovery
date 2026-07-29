<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

use InvalidArgumentException;

/**
 * A requested window over the result set.
 *
 * Clamped rather than trusted: page size is an attack surface (`per_page=99999`
 * is a cheap way to make MySQL sort the whole table) so the ceiling lives in
 * the value object where it cannot be bypassed.
 */
final readonly class Pagination {

	public const DEFAULT_PER_PAGE = 12;
	public const MAX_PER_PAGE     = 60;

	/**
	 * @param positive-int $page    1 based page number.
	 * @param positive-int $perPage Results per page.
	 *
	 * @throws InvalidArgumentException When a pagination value is not positive.
	 */
	private function __construct( public int $page, public int $perPage ) {
		if ( $page < 1 || $perPage < 1 ) {
			throw new InvalidArgumentException( 'Pagination values must be positive.' );
		}
	}

	public static function of( int $page, int $perPage = self::DEFAULT_PER_PAGE ): self {
		return new self(
			max( 1, $page ),
			min( self::MAX_PER_PAGE, max( 1, $perPage ) )
		);
	}

	public static function firstPage(): self {
		return new self( 1, self::DEFAULT_PER_PAGE );
	}

	public function withPage( int $page ): self {
		return self::of( $page, $this->perPage );
	}

	public function offset(): int {
		return ( $this->page - 1 ) * $this->perPage;
	}

	public function totalPages( int $totalResults ): int {
		return (int) max( 1, ceil( $totalResults / $this->perPage ) );
	}
}
