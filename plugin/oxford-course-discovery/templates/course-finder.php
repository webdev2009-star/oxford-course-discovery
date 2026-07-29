<?php
/**
 * Course finder shell.
 *
 * @var array{result: \Oxford\CourseDiscovery\Frontend\FinderResult, renderer: \Oxford\CourseDiscovery\Frontend\TemplateRenderer, heading: string, action: string} $data
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

use Oxford\CourseDiscovery\Frontend\FilterView;
use Oxford\CourseDiscovery\Frontend\FinderResult;
use Oxford\CourseDiscovery\Frontend\TemplateRenderer;

/** @var FinderResult $result */
$result = $data['result'];
/** @var TemplateRenderer $renderer */
$renderer   = $data['renderer'];
$heading    = (string) ( $data['heading'] ?? '' );
$formAction = (string) ( $data['action'] ?? '' );
$criteria   = $result->criteria;
?>
<div class="oxcd-finder" data-oxcd-finder>
	<?php if ( '' !== $heading ) : ?>
		<h2 class="oxcd-finder__heading" id="oxcd-finder-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<form
		class="oxcd-finder__form"
		method="get"
		action="<?php echo esc_url( $formAction ); ?>"
		role="search"
		aria-label="<?php esc_attr_e( 'Course search and filters', 'oxford-course-discovery' ); ?>"
		data-oxcd-form
	>
		<div class="oxcd-filters">
			<?php
			/** @var FilterView $view */
			foreach ( $result->filters as $view ) :
				$renderer->output( $view->control()->template(), array( 'view' => $view ) );
			endforeach;
			?>
		</div>

		<div class="oxcd-finder__actions">
			<button type="submit" class="oxcd-button oxcd-button--primary">
				<?php esc_html_e( 'Show results', 'oxford-course-discovery' ); ?>
			</button>

			<?php // Always rendered: with JavaScript only the results region is re-rendered, so a control that appeared only when filtered would never appear at all. ?>
			<a class="oxcd-button oxcd-button--link" href="<?php echo esc_url( '' === $formAction ? '?' : $formAction ); ?>" data-oxcd-reset>
				<?php esc_html_e( 'Clear all filters', 'oxford-course-discovery' ); ?>
			</a>

			<p class="oxcd-finder__sort">
				<label for="oxcd-orderby"><?php esc_html_e( 'Sort by', 'oxford-course-discovery' ); ?></label>
				<select id="oxcd-orderby" name="orderby" data-oxcd-orderby>
					<?php foreach ( $result->orderings as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $criteria->ordering->key, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<noscript>
					<button type="submit" class="oxcd-button"><?php esc_html_e( 'Apply', 'oxford-course-discovery' ); ?></button>
				</noscript>
			</p>
		</div>
	</form>

	<div class="oxcd-finder__results" data-oxcd-results aria-busy="false">
		<?php
		$renderer->output(
			'partials/results',
			array(
				'result'   => $result,
				'renderer' => $renderer,
			)
		);
		?>
	</div>
</div>
