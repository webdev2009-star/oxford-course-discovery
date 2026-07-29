<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

enum SortDirection: string {
	case Ascending  = 'asc';
	case Descending = 'desc';

	public static function fromRequest( mixed $raw ): self {
		return self::tryFrom( is_string( $raw ) ? strtolower( $raw ) : '' ) ?? self::Ascending;
	}

	/**
	 * @return 'ASC'|'DESC' Safe to interpolate into SQL: the enum guarantees it.
	 */
	public function toSql(): string {
		return self::Ascending === $this ? 'ASC' : 'DESC';
	}
}
