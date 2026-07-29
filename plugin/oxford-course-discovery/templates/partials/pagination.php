<?php
/**
 * Pagination.
 *
 * @var array{result: \Oxford\CourseDiscovery\Frontend\FinderResult} $data
 *
 * @package Oxford\CourseDiscovery
 */

declare(strict_types=1);

use Oxford\CourseDiscovery\Frontend\FinderResult;
use Oxford\CourseDiscovery\Frontend\QueryString;

/** @var FinderResult $result */
$result   = $data['result'];
$criteria = $result->criteria;
$current  = $result->results->currentPage();
$total    = $result->results->totalPages();

if ( $total < 2 ) {
	return;
}

$window = array_filter(
	range( max( 1, $current - 2 ), min( $total, $current + 2 ) ),
	static fn( int $pageNumber ): bool => $pageNumber >= 1 && $pageNumber <= $total
);
?>
<nav class="oxcd-pagination" aria-label="<?php esc_attr_e( 'Course results pages', 'oxford-course-discovery' ); ?>">
	<ul class="oxcd-pagination__list">
		<?php if ( $result->results->hasPreviousPage() ) : ?>
			<li>
				<a class="oxcd-pagination__link" rel="prev" href="<?php echo esc_url( QueryString::forPage( $criteria, $current - 1 ) ); ?>">
					<span aria-hidden="true">&larr;</span>
					<?php esc_html_e( 'Previous', 'oxford-course-discovery' ); ?>
				</a>
			</li>
		<?php endif; ?>

		<?php foreach ( $window as $pageNumber ) : ?>
			<li>
				<?php if ( $pageNumber === $current ) : ?>
					<span class="oxcd-pagination__link is-current" aria-current="page">
						<span class="screen-reader-text"><?php esc_html_e( 'Page', 'oxford-course-discovery' ); ?></span>
						<?php echo esc_html( (string) $pageNumber ); ?>
					</span>
				<?php else : ?>
					<a class="oxcd-pagination__link" href="<?php echo esc_url( QueryString::forPage( $criteria, $pageNumber ) ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Page', 'oxford-course-discovery' ); ?></span>
						<?php echo esc_html( (string) $pageNumber ); ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>

		<?php if ( $result->results->hasNextPage() ) : ?>
			<li>
				<a class="oxcd-pagination__link" rel="next" href="<?php echo esc_url( QueryString::forPage( $criteria, $current + 1 ) ); ?>">
					<?php esc_html_e( 'Next', 'oxford-course-discovery' ); ?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			</li>
		<?php endif; ?>
	</ul>
</nav>
