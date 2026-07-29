<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Fields;

use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use Oxford\CourseDiscovery\WordPress\PostType\InstructorPostType;
use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;

/**
 * ACF field groups registered in code.
 *
 * Local field groups rather than database-stored ones: the schema is then
 * version controlled, deploys deterministically, and cannot drift between
 * environments. The field *names* match {@see FieldKeys} exactly, so ACF is an
 * editing convenience over ordinary post meta — remove ACF and the data, the
 * queries and the front end all still work (see {@see MetaBoxFields}).
 *
 * Only free-tier ACF fields are used; the plugin must run on ACF free.
 */
final class AcfFields {

	public function boot(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		add_action( 'acf/init', $this->register( ... ) );
		add_filter( 'acf/validate_value/name=' . FieldKeys::START_DATES, $this->validateStartDates( ... ), 10, 4 );
	}

	public static function isAvailable(): bool {
		return function_exists( 'acf_add_local_field_group' );
	}

	public function register(): void {
		acf_add_local_field_group(
			array(
				'key'        => 'group_oxcd_course',
				'title'      => __( 'Course details', 'oxford-course-discovery' ),
				'menu_order' => 0,
				'position'   => 'normal',
				'style'      => 'default',
				'active'     => true,
				'location'   => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => CoursePostType::NAME,
						),
					),
				),
				'fields'     => array(
					array(
						'key'          => 'field_oxcd_short_description',
						'label'        => __( 'Short description', 'oxford-course-discovery' ),
						'name'         => FieldKeys::SHORT_DESCRIPTION,
						'type'         => 'textarea',
						'rows'         => 3,
						'maxlength'    => 400,
						'instructions' => __( 'One or two sentences shown on course cards and search results.', 'oxford-course-discovery' ),
					),
					array(
						'key'          => 'field_oxcd_price',
						'label'        => __( 'Price', 'oxford-course-discovery' ),
						'name'         => FieldKeys::PRICE,
						'type'         => 'number',
						'min'          => 0,
						'step'         => '0.01',
						'instructions' => __( 'A single price point. Leave empty for "price on application".', 'oxford-course-discovery' ),
					),
					array(
						'key'           => 'field_oxcd_currency',
						'label'         => __( 'Currency', 'oxford-course-discovery' ),
						'name'          => FieldKeys::CURRENCY,
						'type'          => 'select',
						'choices'       => array(
							'GBP' => 'GBP (£)',
							'EUR' => 'EUR (€)',
							'USD' => 'USD ($)',
						),
						'default_value' => 'GBP',
					),
					array(
						'key'          => 'field_oxcd_start_dates',
						'label'        => __( 'Start dates', 'oxford-course-discovery' ),
						'name'         => FieldKeys::START_DATES,
						'type'         => 'text',
						'placeholder'  => '09-2026, 01-2027',
						'instructions' => __( 'Comma separated intakes in month-year format, e.g. <code>09-2026, 01-2027</code>.', 'oxford-course-discovery' ),
					),
					array(
						'key'           => 'field_oxcd_instructors',
						'label'         => __( 'Instructors', 'oxford-course-discovery' ),
						'name'          => FieldKeys::INSTRUCTORS,
						'type'          => 'relationship',
						'post_type'     => array( InstructorPostType::NAME ),
						'filters'       => array( 'search' ),
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_oxcd_providers',
						'label'         => __( 'Providers', 'oxford-course-discovery' ),
						'name'          => FieldKeys::PROVIDERS,
						'type'          => 'relationship',
						'post_type'     => array( ProviderPostType::NAME ),
						'filters'       => array( 'search' ),
						'return_format' => 'id',
						'instructions'  => __( 'The course inherits its locations from the providers selected here.', 'oxford-course-discovery' ),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_oxcd_provider',
				'title'    => __( 'Provider details', 'oxford-course-discovery' ),
				'active'   => true,
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => ProviderPostType::NAME,
						),
					),
				),
				'fields'   => array(
					array(
						'key'   => 'field_oxcd_provider_url',
						'label' => __( 'Website', 'oxford-course-discovery' ),
						'name'  => FieldKeys::PROVIDER_URL,
						'type'  => 'url',
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_oxcd_instructor',
				'title'    => __( 'Instructor details', 'oxford-course-discovery' ),
				'active'   => true,
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => InstructorPostType::NAME,
						),
					),
				),
				'fields'   => array(
					array(
						'key'   => 'field_oxcd_instructor_role',
						'label' => __( 'Role or job title', 'oxford-course-discovery' ),
						'name'  => FieldKeys::INSTRUCTOR_ROLE,
						'type'  => 'text',
					),
				),
			)
		);
	}

	/**
	 * Reject malformed intakes at the point of entry.
	 *
	 * @param bool|string          $valid Current validation state.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $input Input name.
	 *
	 * @return bool|string True, or an error message.
	 */
	public function validateStartDates( bool|string $valid, mixed $value, array $field, string $input ): bool|string {
		if ( true !== $valid || ! is_string( $value ) || '' === trim( $value ) ) {
			return $valid;
		}

		$invalid = StartDateValidator::invalidFragments( $value );

		if ( array() === $invalid ) {
			return true;
		}

		return sprintf(
			/* translators: %s: comma separated list of rejected values. */
			__( 'These start dates are not in {month}-{year} format: %s', 'oxford-course-discovery' ),
			implode( ', ', $invalid )
		);
	}
}
