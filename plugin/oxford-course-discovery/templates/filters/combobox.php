<?php
/**
 * Multi-select dropdown combobox.
 *
 * A native <details>/<summary> disclosure wrapping a checkbox group: keyboard
 * operable and screen reader friendly before any JavaScript runs, and still
 * usable if scripting fails — which a div-and-ARIA combobox is not. The
 * `data-oxcd-*` hooks below let the script add type-ahead filtering, arrow key
 * navigation, Escape-to-close and a live selection count.
 *
 * @var array{view: \Oxford\CourseDiscovery\Frontend\FilterView} $data
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Frontend\FilterView;

/** @var FilterView $view */
$view = $data['view'];

if ( ! $view->hasOptions() ) {
	return;
}

$selectedLabels = $view->selectedLabels();
$summaryText    = array() === $selectedLabels
	? __( 'Any', 'oxford-course-discovery' )
	: implode( ', ', $selectedLabels );
?>
<?php // The fieldset wraps the disclosure as well as the options, so the group's accessible name covers the whole control. ?>
<fieldset class="oxcd-filter oxcd-filter--combobox" data-oxcd-combobox>
	<legend class="screen-reader-text"><?php echo esc_html( $view->label() ); ?></legend>

	<?php // Left open when something is selected, so a reload does not hide the active filters. ?>
	<details class="oxcd-combobox" <?php echo array() === $selectedLabels ? '' : 'open'; ?>>
		<summary class="oxcd-combobox__summary" aria-describedby="<?php echo esc_attr( $view->id( 'selection' ) ); ?>">
			<span class="oxcd-filter__label"><?php echo esc_html( $view->label() ); ?></span>
			<span class="oxcd-combobox__value" id="<?php echo esc_attr( $view->id( 'selection' ) ); ?>" data-oxcd-combobox-summary>
				<?php echo esc_html( $summaryText ); ?>
			</span>
		</summary>

		<div class="oxcd-combobox__panel">
			<div class="oxcd-combobox__search">
				<label class="screen-reader-text" for="<?php echo esc_attr( $view->id( 'search' ) ); ?>">
					<?php
					printf(
						/* translators: %s: filter label, e.g. "Locations". */
						esc_html__( 'Filter the list of %s', 'oxford-course-discovery' ),
						esc_html( $view->label() )
					);
					?>
				</label>
				<input
					type="text"
					class="oxcd-combobox__input"
					id="<?php echo esc_attr( $view->id( 'search' ) ); ?>"
					data-oxcd-combobox-search
					autocomplete="off"
					hidden
					placeholder="<?php esc_attr_e( 'Type to narrow…', 'oxford-course-discovery' ); ?>"
					aria-controls="<?php echo esc_attr( $view->id( 'list' ) ); ?>"
				/>
			</div>

			<div class="oxcd-combobox__fieldset">
				<ul class="oxcd-combobox__list" id="<?php echo esc_attr( $view->id( 'list' ) ); ?>" data-oxcd-combobox-list>
					<?php
					/** @var FilterOption $option */
					foreach ( $view->options as $index => $option ) :
						$fieldId = $view->id( 'opt-' . $index );
						?>
						<li class="oxcd-combobox__option" data-oxcd-option data-label="<?php echo esc_attr( strtolower( $option->label ) ); ?>">
							<input
								type="checkbox"
								id="<?php echo esc_attr( $fieldId ); ?>"
								name="<?php echo esc_attr( $view->key() ); ?>[]"
								value="<?php echo esc_attr( $option->value ); ?>"
								<?php checked( $view->isSelected( $option->value ) ); ?>
							/>
							<label for="<?php echo esc_attr( $fieldId ); ?>">
								<?php echo esc_html( $option->label ); ?>
								<?php if ( null !== $option->count ) : ?>
									<span class="oxcd-filter__count" aria-hidden="true">(<?php echo esc_html( (string) $option->count ); ?>)</span>
								<?php endif; ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<p class="oxcd-combobox__empty" data-oxcd-combobox-empty hidden>
					<?php esc_html_e( 'No matching options', 'oxford-course-discovery' ); ?>
				</p>
			</div>
		</div>
	</details>
</fieldset>
