<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

use InvalidArgumentException;

/**
 * The stable identifier of a filter.
 *
 * It is simultaneously the registry key, the query string parameter, the form
 * field name and the REST argument, so it is validated once here rather than
 * sanitised at four call sites.
 */
final readonly class FilterKey {

	/**
	 * @param non-empty-string $value Lower snake case identifier.
	 *
	 * @throws InvalidArgumentException When the key is not URL and form safe.
	 */
	private function __construct( public string $value ) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $value ) ) {
			throw new InvalidArgumentException(
				sprintf( '"%s" is not a valid filter key; use lower snake case, e.g. start_date.', $value )
			);
		}
	}

	public static function fromString( string $value ): self {
		return new self( $value );
	}

	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	public function __toString(): string {
		return $this->value;
	}
}
