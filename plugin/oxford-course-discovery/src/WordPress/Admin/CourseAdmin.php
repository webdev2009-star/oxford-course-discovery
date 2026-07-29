<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Admin;

use Oxford\CourseDiscovery\Database\Migrator;
use Oxford\CourseDiscovery\Domain\Course;
use Oxford\CourseDiscovery\Domain\ValueObject\Reference;
use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;
use Oxford\CourseDiscovery\Indexing\CourseIndexer;
use Oxford\CourseDiscovery\WordPress\CourseMapper;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use WP_Post;

/**
 * The course management screens: list table columns plus the maintenance
 * actions an operator needs when something looks stale.
 */
final class CourseAdmin {

	private const REINDEX_ACTION = 'oxcd_reindex';

	public function __construct(
		private readonly CourseMapper $mapper,
		private readonly CourseIndexer $indexer,
		private readonly Migrator $migrator
	) {
	}

	public function boot(): void {
		add_filter( 'manage_' . CoursePostType::NAME . '_posts_columns', $this->columns( ... ) );
		add_action( 'manage_' . CoursePostType::NAME . '_posts_custom_column', $this->renderColumn( ... ), 10, 2 );
		add_action( 'admin_menu', $this->registerToolsPage( ... ) );
		add_action( 'admin_post_' . self::REINDEX_ACTION, $this->handleReindex( ... ) );
		add_action( 'admin_notices', $this->schemaNotice( ... ) );
	}

	/**
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );

		$columns['oxcd_providers']  = __( 'Providers', 'oxford-course-discovery' );
		$columns['oxcd_locations']  = __( 'Locations', 'oxford-course-discovery' );
		$columns['oxcd_next_start'] = __( 'Next start', 'oxford-course-discovery' );
		$columns['oxcd_price']      = __( 'Price', 'oxford-course-discovery' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	public function renderColumn( string $column, int $postId ): void {
		if ( ! str_starts_with( $column, 'oxcd_' ) ) {
			return;
		}

		$post = get_post( $postId );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$course = $this->mapper->map( $post );

		echo esc_html( $this->columnValue( $column, $course ) );
	}

	private function columnValue( string $column, Course $course ): string {
		$nextStart = $course->nextStartDate();

		$value = match ( $column ) {
			'oxcd_providers'  => $this->joinNames( $course->providers->toArray() ),
			'oxcd_locations'  => $this->joinNames( $course->locations->toArray() ),
			'oxcd_next_start' => $nextStart instanceof StartDate ? $nextStart->label() : '',
			'oxcd_price'      => $course->formattedPrice(),
			default           => '',
		};

		return '' === $value ? '—' : $value;
	}

	/**
	 * @param list<Reference> $references References to join.
	 */
	private function joinNames( array $references ): string {
		return implode( ', ', array_map( static fn( Reference $reference ): string => $reference->name, $references ) );
	}

	public function registerToolsPage(): void {
		add_submenu_page(
			'edit.php?post_type=' . CoursePostType::NAME,
			__( 'Discovery tools', 'oxford-course-discovery' ),
			__( 'Discovery tools', 'oxford-course-discovery' ),
			'manage_options',
			'oxcd-tools',
			$this->renderToolsPage( ... )
		);
	}

	public function renderToolsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Course discovery tools', 'oxford-course-discovery' ) . '</h1>';

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: current schema version, 2: latest schema version. */
					__( 'Schema version %1$d of %2$d.', 'oxford-course-discovery' ),
					$this->migrator->currentVersion(),
					$this->migrator->latestVersion()
				)
			)
		);

		echo '<p>' . esc_html__( 'Rebuild the lookup tables that power the provider, location, start date and keyword filters. Safe to run at any time.', 'oxford-course-discovery' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::REINDEX_ACTION ) . '" />';
		wp_nonce_field( self::REINDEX_ACTION );
		submit_button( __( 'Rebuild course index', 'oxford-course-discovery' ) );
		echo '</form></div>';
	}

	public function handleReindex(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'oxford-course-discovery' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::REINDEX_ACTION );

		$this->migrator->migrate();
		$indexed = $this->indexer->reindexAll();

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'    => CoursePostType::NAME,
					'page'         => 'oxcd-tools',
					'oxcd_indexed' => $indexed,
				),
				admin_url( 'edit.php' )
			)
		);

		exit;
	}

	public function schemaNotice(): void {
		if ( ! current_user_can( 'manage_options' ) || $this->migrator->isUpToDate() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		esc_html_e(
			'Oxford Course Discovery has pending database migrations. Visit Courses → Discovery tools, or run "wp oxcd migrate".',
			'oxford-course-discovery'
		);
		echo '</p></div>';
	}
}
