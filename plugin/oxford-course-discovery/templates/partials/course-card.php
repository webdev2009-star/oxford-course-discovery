<?php
/**
 * A single course result.
 *
 * @var array{course: \Oxford\CourseDiscovery\Domain\Course} $data
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

use Oxford\CourseDiscovery\Domain\Course;
use Oxford\CourseDiscovery\Domain\ValueObject\Reference;
use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;

/** @var Course $course */
$course     = $data['course'];
$nextIntake = $course->nextStartDate();
?>
<article class="oxcd-card" aria-labelledby="oxcd-card-<?php echo esc_attr( (string) $course->id->toInt() ); ?>">
	<?php if ( '' !== $course->imageUrl ) : ?>
		<img class="oxcd-card__image" src="<?php echo esc_url( $course->imageUrl ); ?>" alt="" loading="lazy" decoding="async" />
	<?php endif; ?>

	<div class="oxcd-card__body">
		<h3 class="oxcd-card__title" id="oxcd-card-<?php echo esc_attr( (string) $course->id->toInt() ); ?>">
			<a href="<?php echo esc_url( $course->url ); ?>"><?php echo esc_html( $course->name->value ); ?></a>
		</h3>

		<?php if ( ! $course->providers->isEmpty() ) : ?>
			<p class="oxcd-card__providers">
				<span class="oxcd-card__meta-label"><?php esc_html_e( 'Provider:', 'oxford-course-discovery' ); ?></span>
				<?php echo esc_html( implode( ', ', $course->providers->names() ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! $course->shortDescription->isEmpty() ) : ?>
			<p class="oxcd-card__excerpt"><?php echo esc_html( $course->shortDescription->excerpt( 28 ) ); ?></p>
		<?php endif; ?>

		<dl class="oxcd-card__facts">
			<?php if ( ! $course->locations->isEmpty() ) : ?>
				<div class="oxcd-card__fact">
					<dt><?php esc_html_e( 'Locations', 'oxford-course-discovery' ); ?></dt>
					<dd><?php echo esc_html( implode( ', ', $course->locations->names() ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( $nextIntake instanceof StartDate ) : ?>
				<div class="oxcd-card__fact">
					<dt><?php esc_html_e( 'Next start', 'oxford-course-discovery' ); ?></dt>
					<dd><?php echo esc_html( $nextIntake->label() ); ?></dd>
				</div>
			<?php endif; ?>

			<div class="oxcd-card__fact">
				<dt><?php esc_html_e( 'Price', 'oxford-course-discovery' ); ?></dt>
				<dd><?php echo esc_html( $course->formattedPrice() ); ?></dd>
			</div>
		</dl>

		<?php if ( ! $course->categories->isEmpty() ) : ?>
			<ul class="oxcd-card__tags" aria-label="<?php esc_attr_e( 'Categories', 'oxford-course-discovery' ); ?>">
				<?php
				/** @var Reference $category */
				foreach ( $course->categories as $category ) :
					?>
					<li class="oxcd-card__tag"><?php echo esc_html( $category->name ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</article>
