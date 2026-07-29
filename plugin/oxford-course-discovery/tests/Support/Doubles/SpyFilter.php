<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Support\Doubles;

use Oxford\CourseDiscovery\Filter\Filter;
use Oxford\CourseDiscovery\Filter\FilterControl;
use Oxford\CourseDiscovery\Filter\FilterKey;

/**
 * A filter with identity and nothing else.
 *
 * Capabilities are opted into by using {@see SpyQueryFilter} or
 * {@see SpyOptionsFilter} instead — which is exactly the shape a third party
 * filter takes, so the tests exercise the real extension path.
 */
class SpyFilter implements Filter {

	public int $contributions = 0;

	public function __construct(
		private readonly string $key,
		private readonly string $label = 'Spy',
		private readonly int $priority = 50,
		private readonly FilterControl $control = FilterControl::Checkboxes
	) {
	}

	public function key(): FilterKey {
		return FilterKey::fromString( $this->key );
	}

	public function label(): string {
		return $this->label;
	}

	public function control(): FilterControl {
		return $this->control;
	}

	public function priority(): int {
		return $this->priority;
	}
}
