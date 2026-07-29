<?php
/**
 * Free text filter control.
 *
 * @var array{view: \Oxford\CourseDiscovery\Frontend\FilterView} $data
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

use Oxford\CourseDiscovery\Frontend\FilterView;

/** @var FilterView $view */
$view    = $data['view'];
$fieldId = $view->id( 'input' );
?>
<div class="oxcd-filter oxcd-filter--text">
	<label class="oxcd-filter__label" for="<?php echo esc_attr( $fieldId ); ?>">
		<?php echo esc_html( $view->label() ); ?>
	</label>
	<input
		type="search"
		id="<?php echo esc_attr( $fieldId ); ?>"
		name="<?php echo esc_attr( $view->key() ); ?>"
		value="<?php echo esc_attr( (string) $view->selected->first() ); ?>"
		class="oxcd-filter__input"
		autocomplete="off"
		placeholder="<?php esc_attr_e( 'e.g. graphic design', 'oxford-course-discovery' ); ?>"
		aria-describedby="<?php echo esc_attr( $view->id( 'hint' ) ); ?>"
	/>
	<span class="oxcd-filter__hint" id="<?php echo esc_attr( $view->id( 'hint' ) ); ?>">
		<?php esc_html_e( 'Matches course names and descriptions.', 'oxford-course-discovery' ); ?>
	</span>
</div>
