<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Frontend;

use Oxford\CourseDiscovery\Filter\Filter;
use Oxford\CourseDiscovery\Filter\FilterControl;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Filter\FilterValue;

/**
 * One filter, ready to render: what it is, what it offers, what is selected.
 *
 * Keeps templates free of service calls — a template that can reach the
 * registry ends up querying inside a loop.
 */
final readonly class FilterView {

	public function __construct(
		public Filter $filter,
		public FilterOptionCollection $options,
		public FilterValue $selected
	) {
	}

	public function key(): string {
		return $this->filter->key()->value;
	}

	public function label(): string {
		return $this->filter->label();
	}

	public function control(): FilterControl {
		return $this->filter->control();
	}

	public function isSelected( string $value ): bool {
		return $this->selected->contains( $value );
	}

	public function selectedCount(): int {
		return $this->selected->count();
	}

	public function hasOptions(): bool {
		return ! $this->options->isEmpty();
	}

	/**
	 * Element id for the control, unique per filter.
	 */
	public function id( string $suffix = '' ): string {
		return 'oxcd-' . $this->key() . ( '' === $suffix ? '' : '-' . $suffix );
	}

	/**
	 * Labels of the current selection, for the summary line of a combobox.
	 *
	 * @return list<string>
	 */
	public function selectedLabels(): array {
		$labels = array();

		foreach ( $this->selected->toArray() as $value ) {
			$option   = $this->options->find( $value );
			$labels[] = null === $option ? $value : $option->label;
		}

		return $labels;
	}
}
