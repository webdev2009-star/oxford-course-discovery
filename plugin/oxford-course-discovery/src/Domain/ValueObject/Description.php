<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

/**
 * A block of descriptive copy. Both the short and long descriptions are the
 * same concept with different presentation rules, so they share a type and
 * differ only in how the reader asks for an excerpt.
 */
final readonly class Description {

	private function __construct( public string $value ) {
	}

	public static function fromString( string $value ): self {
		return new self( $value );
	}

	public static function empty(): self {
		return new self( '' );
	}

	public function isEmpty(): bool {
		return '' === trim( wp_strip_all_tags( $this->value ) );
	}

	/**
	 * Plain text excerpt, word safe.
	 *
	 * @param int $words Maximum number of words.
	 */
	public function excerpt( int $words = 30 ): string {
		return wp_trim_words( wp_strip_all_tags( $this->value ), max( 1, $words ) );
	}

	public function plain(): string {
		return trim( wp_strip_all_tags( $this->value ) );
	}

	public function __toString(): string {
		return $this->value;
	}
}
