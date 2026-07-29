<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

use InvalidArgumentException;

/**
 * A resolved pointer to another entity — a provider, instructor, category or
 * location.
 *
 * Read models need three things about a related entity: something to key on,
 * something to show, and somewhere to link. Loading whole entities for a
 * results grid would be wasteful, and passing bare IDs around would push
 * lookups into templates. A reference is the middle ground.
 */
final readonly class Reference {

	/**
	 * @param positive-int $id    Post or term ID.
	 * @param string       $slug  URL safe identifier used as the filter value.
	 * @param string       $name  Human readable label.
	 * @param string       $url   Canonical URL, empty when not linkable.
	 *
	 * @throws InvalidArgumentException When the identifier or slug is missing.
	 */
	private function __construct(
		public int $id,
		public string $slug,
		public string $name,
		public string $url = ''
	) {
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'A reference needs a positive identifier.' );
		}

		if ( '' === $slug ) {
			throw new InvalidArgumentException( 'A reference needs a slug.' );
		}
	}

	public static function create( int $id, string $slug, string $name, string $url = '' ): self {
		return new self( $id, $slug, '' === $name ? $slug : $name, $url );
	}

	public function equals( self $other ): bool {
		return $this->id === $other->id;
	}
}
