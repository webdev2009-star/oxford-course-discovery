<?php
/**
 * Hierarchical checkbox filter control.
 *
 * Nested <ul>s inside one fieldset: the tree structure is conveyed by the
 * markup itself, so assistive technology announces depth without any ARIA.
 *
 * @var array{view: \Oxford\CourseDiscovery\Frontend\FilterView} $data
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Frontend\FilterView;

/** @var FilterView $view */
$view = $data['view'];

if ( ! $view->hasOptions() ) {
	return;
}

/**
 * Render one level of the tree.
 *
 * @param FilterOptionCollection $options Options at this level.
 * @param FilterView             $view    Owning filter view.
 * @param string                 $path    Unique id path.
 */
$renderLevel = static function ( FilterOptionCollection $options, FilterView $view, string $path ) use ( &$renderLevel ): void {
	echo '<ul class="oxcd-filter__options">';

	/** @var FilterOption $option */
	foreach ( $options as $index => $option ) {
		$id = $view->id( $path . $index );

		echo '<li class="oxcd-filter__option">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s" %4$s />',
			esc_attr( $id ),
			esc_attr( $view->key() ),
			esc_attr( $option->value ),
			checked( $view->isSelected( $option->value ), true, false )
		);
		printf(
			'<label for="%1$s">%2$s</label>',
			esc_attr( $id ),
			esc_html( $option->labelWithCount() )
		);

		if ( $option->hasChildren() ) {
			$renderLevel( $option->children(), $view, $path . $index . '-' );
		}

		echo '</li>';
	}

	echo '</ul>';
};
?>
<fieldset class="oxcd-filter oxcd-filter--tree">
	<legend class="oxcd-filter__label"><?php echo esc_html( $view->label() ); ?></legend>
	<?php $renderLevel( $view->options, $view, 'cat-' ); ?>
</fieldset>
