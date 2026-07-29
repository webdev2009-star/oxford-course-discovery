<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter;

/**
 * The interaction pattern a filter is drawn with.
 *
 * Kept deliberately small and presentational-only: it tells the renderer which
 * partial to include and the JS which enhancement to attach. Adding a new
 * control means adding a case plus a template, not touching filter logic.
 */
enum FilterControl: string {
	/** Free text input. */
	case Text = 'text';

	/** A visible group of checkboxes; good for short, browsable lists. */
	case Checkboxes = 'checkboxes';

	/** Multi-select combobox with type-ahead; required for locations and start dates. */
	case Combobox = 'combobox';

	/** Nested checkboxes for hierarchical taxonomies. */
	case Tree = 'tree';

	public function isMultiValue(): bool {
		return self::Text !== $this;
	}

	/**
	 * Template partial that renders this control.
	 */
	public function template(): string {
		return 'filters/' . $this->value;
	}
}
