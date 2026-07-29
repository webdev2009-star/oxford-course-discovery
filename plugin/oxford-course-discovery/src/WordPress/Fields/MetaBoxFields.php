<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Fields;

use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use Oxford\CourseDiscovery\WordPress\PostType\InstructorPostType;
use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;
use WP_Post;

/**
 * Native metabox editing for the same fields ACF would provide.
 *
 * Registered only when ACF is absent. The brief allows ACF, but a discovery
 * system that becomes unusable — and, worse, silently un-editable — when a
 * single plugin is deactivated is a fragile dependency. Both paths write the
 * identical {@see FieldKeys} meta, so content survives switching either way.
 */
final class MetaBoxFields {

	private const NONCE = 'oxcd_fields_nonce';

	public function boot(): void {
		add_action( 'add_meta_boxes', $this->register( ... ) );
		add_action( 'save_post', $this->save( ... ), 10, 2 );
	}

	public function register(): void {
		add_meta_box(
			'oxcd_course_fields',
			__( 'Course details', 'oxford-course-discovery' ),
			$this->renderCourse( ... ),
			CoursePostType::NAME,
			'normal',
			'high'
		);

		add_meta_box(
			'oxcd_provider_fields',
			__( 'Provider details', 'oxford-course-discovery' ),
			$this->renderProvider( ... ),
			ProviderPostType::NAME,
			'normal',
			'default'
		);

		add_meta_box(
			'oxcd_instructor_fields',
			__( 'Instructor details', 'oxford-course-discovery' ),
			$this->renderInstructor( ... ),
			InstructorPostType::NAME,
			'normal',
			'default'
		);
	}

	public function renderCourse( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$shortDescription = (string) get_post_meta( $post->ID, FieldKeys::SHORT_DESCRIPTION, true );
		$price            = (string) get_post_meta( $post->ID, FieldKeys::PRICE, true );
		$currency         = (string) get_post_meta( $post->ID, FieldKeys::CURRENCY, true );
		$startDates       = (string) get_post_meta( $post->ID, FieldKeys::START_DATES, true );

		echo '<p><label for="oxcd_short_description"><strong>' . esc_html__( 'Short description', 'oxford-course-discovery' ) . '</strong></label><br />';
		echo '<textarea class="widefat" rows="3" maxlength="400" id="oxcd_short_description" name="' . esc_attr( FieldKeys::SHORT_DESCRIPTION ) . '">' . esc_textarea( $shortDescription ) . '</textarea></p>';

		echo '<p><label for="oxcd_price"><strong>' . esc_html__( 'Price', 'oxford-course-discovery' ) . '</strong></label><br />';
		echo '<input type="number" step="0.01" min="0" id="oxcd_price" name="' . esc_attr( FieldKeys::PRICE ) . '" value="' . esc_attr( $price ) . '" /> ';
		echo '<select name="' . esc_attr( FieldKeys::CURRENCY ) . '" aria-label="' . esc_attr__( 'Currency', 'oxford-course-discovery' ) . '">';

		foreach ( array( 'GBP', 'EUR', 'USD' ) as $code ) {
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $code ),
				selected( '' === $currency ? 'GBP' : $currency, $code, false )
			);
		}

		echo '</select></p>';

		echo '<p><label for="oxcd_start_dates"><strong>' . esc_html__( 'Start dates', 'oxford-course-discovery' ) . '</strong></label><br />';
		echo '<input type="text" class="widefat" id="oxcd_start_dates" name="' . esc_attr( FieldKeys::START_DATES ) . '" value="' . esc_attr( $startDates ) . '" placeholder="09-2026, 01-2027" aria-describedby="oxcd_start_dates_help" />';
		echo '<span id="oxcd_start_dates_help" class="description">' . esc_html__( 'Comma separated, month-year format. Invalid entries are ignored.', 'oxford-course-discovery' ) . '</span></p>';

		$this->renderRelationship( $post, FieldKeys::PROVIDERS, ProviderPostType::NAME, __( 'Providers', 'oxford-course-discovery' ) );
		$this->renderRelationship( $post, FieldKeys::INSTRUCTORS, InstructorPostType::NAME, __( 'Instructors', 'oxford-course-discovery' ) );
	}

	public function renderProvider( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$url = (string) get_post_meta( $post->ID, FieldKeys::PROVIDER_URL, true );

		echo '<p><label for="oxcd_provider_url"><strong>' . esc_html__( 'Website', 'oxford-course-discovery' ) . '</strong></label><br />';
		echo '<input type="url" class="widefat" id="oxcd_provider_url" name="' . esc_attr( FieldKeys::PROVIDER_URL ) . '" value="' . esc_attr( $url ) . '" /></p>';
	}

	public function renderInstructor( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$role = (string) get_post_meta( $post->ID, FieldKeys::INSTRUCTOR_ROLE, true );

		echo '<p><label for="oxcd_instructor_role"><strong>' . esc_html__( 'Role or job title', 'oxford-course-discovery' ) . '</strong></label><br />';
		echo '<input type="text" class="widefat" id="oxcd_instructor_role" name="' . esc_attr( FieldKeys::INSTRUCTOR_ROLE ) . '" value="' . esc_attr( $role ) . '" /></p>';
	}

	public function save( int $postId, WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}

		if ( CoursePostType::NAME === $post->post_type ) {
			$this->saveCourse( $postId );
		}

		if ( ProviderPostType::NAME === $post->post_type ) {
			$this->saveText( $postId, FieldKeys::PROVIDER_URL, 'esc_url_raw' );
		}

		if ( InstructorPostType::NAME === $post->post_type ) {
			$this->saveText( $postId, FieldKeys::INSTRUCTOR_ROLE, 'sanitize_text_field' );
		}
	}

	private function saveCourse( int $postId ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in save().
		$this->saveText( $postId, FieldKeys::SHORT_DESCRIPTION, 'sanitize_textarea_field' );

		$price = isset( $_POST[ FieldKeys::PRICE ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ FieldKeys::PRICE ] ) ) : '';
		update_post_meta( $postId, FieldKeys::PRICE, is_numeric( $price ) ? (string) (float) $price : '' );

		$currency = isset( $_POST[ FieldKeys::CURRENCY ] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST[ FieldKeys::CURRENCY ] ) ) ) : 'GBP';
		update_post_meta( $postId, FieldKeys::CURRENCY, in_array( $currency, array( 'GBP', 'EUR', 'USD' ), true ) ? $currency : 'GBP' );

		$startDates = isset( $_POST[ FieldKeys::START_DATES ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ FieldKeys::START_DATES ] ) ) : '';
		update_post_meta( $postId, FieldKeys::START_DATES, StartDateValidator::canonicalise( $startDates ) );

		foreach ( array( FieldKeys::PROVIDERS, FieldKeys::INSTRUCTORS ) as $key ) {
			$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) )
				: array();

			// Casting to int is the real validation here: anything that is not
			// a positive post ID is discarded.
			$ids = array_values( array_unique( array_filter( array_map( 'intval', $raw ) ) ) );

			update_post_meta( $postId, $key, $ids );
		}
		// phpcs:enable
	}

	/**
	 * @param callable(string): string $sanitiser Sanitising callback.
	 */
	private function saveText( int $postId, string $key, callable $sanitiser ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in save(); $sanitiser is the sanitising callback.
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( (string) $_POST[ $key ] ) : '';

		update_post_meta( $postId, $key, $sanitiser( $raw ) );
	}

	private function renderRelationship( WP_Post $post, string $key, string $postType, string $label ): void {
		$selected = (array) get_post_meta( $post->ID, $key, true );
		$selected = array_map( 'intval', array_filter( $selected, 'is_numeric' ) );

		$options = get_posts(
			array(
				'post_type'      => $postType,
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- a bounded admin select, not a front end query.
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br /><select multiple size="6" class="widefat" id="%1$s" name="%1$s[]">',
			esc_attr( $key ),
			esc_html( $label )
		);

		foreach ( $options as $option ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $option->ID,
				selected( in_array( (int) $option->ID, $selected, true ), true, false ),
				esc_html( $option->post_title )
			);
		}

		echo '</select></p>';
	}
}
