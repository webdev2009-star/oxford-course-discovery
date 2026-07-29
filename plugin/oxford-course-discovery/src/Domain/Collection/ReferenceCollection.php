<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Collection;

use Oxford\CourseDiscovery\Domain\ValueObject\Reference;
use Oxford\CourseDiscovery\Support\TypedCollection;

/**
 * @extends TypedCollection<Reference>
 */
final class ReferenceCollection extends TypedCollection {

	protected static function itemType(): string {
		return Reference::class;
	}

	/**
	 * @return list<int>
	 */
	public function ids(): array {
		return $this->map( static fn( Reference $reference ): int => $reference->id );
	}

	/**
	 * @return list<string>
	 */
	public function slugs(): array {
		return $this->map( static fn( Reference $reference ): string => $reference->slug );
	}

	/**
	 * @return list<string>
	 */
	public function names(): array {
		return $this->map( static fn( Reference $reference ): string => $reference->name );
	}

	/**
	 * De-duplicate by identity and sort alphabetically — the shape most UI
	 * surfaces want.
	 */
	public function normalised(): self {
		return $this
			->unique( static fn( Reference $reference ): int => $reference->id )
			->sorted( static fn( Reference $a, Reference $b ): int => strcasecmp( $a->name, $b->name ) );
	}
}
