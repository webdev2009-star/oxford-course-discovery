<?php
/**
 * Checkbox group filter control.
 *
 * Native inputs inside a fieldset: keyboard operable and announced correctly
 * with no scripting at all.
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
?>
<fieldset class="oxcd-filter oxcd-filter--checkboxes">
	<legend class="oxcd-filter__label"><?php echo esc_html( $view->label() ); ?></legend>

	<ul class="oxcd-filter__options">
		<?php
		/** @var FilterOption $option */
		foreach ( $view->options as $index => $option ) :
			$fieldId = $view->id( 'opt-' . $index );
			?>
			<li class="oxcd-filter__option">
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
						<span class="screen-reader-text">
							<?php
							printf(
								/* translators: %d: number of matching courses. */
								esc_html( _n( '%d course', '%d courses', (int) $option->count, 'oxford-course-discovery' ) ),
								(int) $option->count
							);
							?>
						</span>
					<?php endif; ?>
				</label>
			</li>
		<?php endforeach; ?>
	</ul>
</fieldset>
