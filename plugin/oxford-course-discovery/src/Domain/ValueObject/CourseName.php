<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

use InvalidArgumentException;

/**
 * The public, human facing name of a course.
 */
final readonly class CourseName {

	public const MAX_LENGTH = 255;

	/**
	 * @param non-empty-string $value Course name.
	 *
	 * @throws InvalidArgumentException When empty or over length.
	 */
	private function __construct( public string $value ) {
		if ( '' === trim( $value ) ) {
			throw new InvalidArgumentException( 'A course name cannot be empty.' );
		}

		if ( mb_strlen( $value ) > self::MAX_LENGTH ) {
			throw new InvalidArgumentException(
				sprintf( 'A course name cannot exceed %d characters.', self::MAX_LENGTH )
			);
		}
	}

	public static function fromString( string $value ): self {
		return new self( trim( $value ) );
	}

	public function __toString(): string {
		return $this->value;
	}
}
