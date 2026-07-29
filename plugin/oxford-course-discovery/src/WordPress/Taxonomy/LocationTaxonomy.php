<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Taxonomy;

use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;

/**
 * Where teaching happens.
 *
 * Registered against providers only — the brief states a course's locations
 * are *derived* from its providers, so making it directly editable on a course
 * would create two sources of truth. Courses get their locations through the
 * lookup table the indexer maintains.
 */
final class LocationTaxonomy implements TaxonomyDefinition {

	public const NAME = 'course_location';

	public function name(): string {
		return self::NAME;
	}

	public function objectTypes(): array {
		return array( ProviderPostType::NAME );
	}

	public function args(): array {
		return array(
			'labels'            => array(
				'name'          => __( 'Locations', 'oxford-course-discovery' ),
				'singular_name' => __( 'Location', 'oxford-course-discovery' ),
				'search_items'  => __( 'Search Locations', 'oxford-course-discovery' ),
				'all_items'     => __( 'All Locations', 'oxford-course-discovery' ),
				'edit_item'     => __( 'Edit Location', 'oxford-course-discovery' ),
				'menu_name'     => __( 'Locations', 'oxford-course-discovery' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'location' ),
		);
	}
}
