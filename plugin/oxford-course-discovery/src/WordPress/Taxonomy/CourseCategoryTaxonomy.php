<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Taxonomy;

use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;

/**
 * Hierarchical subject categories, e.g. Design > Graphic Design.
 */
final class CourseCategoryTaxonomy implements TaxonomyDefinition {

	public const NAME = 'course_category';

	public function name(): string {
		return self::NAME;
	}

	public function objectTypes(): array {
		return array( CoursePostType::NAME );
	}

	public function args(): array {
		return array(
			'labels'            => array(
				'name'          => __( 'Course Categories', 'oxford-course-discovery' ),
				'singular_name' => __( 'Course Category', 'oxford-course-discovery' ),
				'search_items'  => __( 'Search Categories', 'oxford-course-discovery' ),
				'all_items'     => __( 'All Categories', 'oxford-course-discovery' ),
				'parent_item'   => __( 'Parent Category', 'oxford-course-discovery' ),
				'edit_item'     => __( 'Edit Category', 'oxford-course-discovery' ),
				'menu_name'     => __( 'Categories', 'oxford-course-discovery' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'course-category' ),
		);
	}
}
