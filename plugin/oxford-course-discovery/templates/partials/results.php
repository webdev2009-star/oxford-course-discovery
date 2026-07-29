<?php
/**
 * Results region. Rendered server side and re-rendered by the REST endpoint,
 * so the enhanced and unenhanced experiences are byte-identical.
 *
 * @var array{result: \Oxford\CourseDiscovery\Frontend\FinderResult, renderer: \Oxford\CourseDiscovery\Frontend\TemplateRenderer} $data
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

use Oxford\CourseDiscovery\Domain\Course;
use Oxford\CourseDiscovery\Frontend\FinderResult;
use Oxford\CourseDiscovery\Frontend\TemplateRenderer;

/** @var FinderResult $result */
$result = $data['result'];
/** @var TemplateRenderer $renderer */
$renderer = $data['renderer'];
?>
<?php // tabindex="-1" so the script can move focus here after an async update. ?>
<p class="oxcd-results__summary" role="status" aria-live="polite" tabindex="-1" data-oxcd-summary>
	<?php echo esc_html( $result->summary() ); ?>
</p>

<?php if ( $result->results->isEmpty() ) : ?>
	<div class="oxcd-results__empty">
		<p><?php esc_html_e( 'Try removing a filter or searching for a broader term.', 'oxford-course-discovery' ); ?></p>
	</div>
<?php else : ?>
	<ul class="oxcd-results__list" aria-label="<?php esc_attr_e( 'Courses', 'oxford-course-discovery' ); ?>">
		<?php
		/** @var Course $course */
		foreach ( $result->results->courses as $course ) :
			?>
			<li class="oxcd-results__item">
				<?php $renderer->output( 'partials/course-card', array( 'course' => $course ) ); ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php $renderer->output( 'partials/pagination', array( 'result' => $result ) ); ?>
<?php endif; ?>
