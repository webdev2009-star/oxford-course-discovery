<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Options;

use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * A fixed option set. Used by tests, and by integrations whose choices are
 * configuration rather than content.
 */
final class StaticOptions implements OptionsSource {

	public function __construct(
		private readonly FilterOptionCollection $options,
		private readonly string $key = 'static'
	) {
	}

	/**
	 * @param array<string, string> $pairs Value => label.
	 */
	public static function fromPairs( array $pairs, string $key = 'static' ): self {
		return new self( FilterOptionCollection::fromPairs( $pairs ), $key );
	}

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		return $this->options;
	}

	public function cacheKey( SearchCriteria $criteria ): string {
		return 'static:' . $this->key;
	}
}
