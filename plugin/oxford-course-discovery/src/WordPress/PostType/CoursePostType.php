<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\PostType;

/**
 * The catalogue item students search for.
 */
final class CoursePostType implements PostTypeDefinition {

	public const NAME = 'course';

	public function name(): string {
		return self::NAME;
	}

	public function args(): array {
		return array(
			'labels'             => array(
				'name'          => __( 'Courses', 'oxford-course-discovery' ),
				'singular_name' => __( 'Course', 'oxford-course-discovery' ),
				'add_new_item'  => __( 'Add New Course', 'oxford-course-discovery' ),
				'edit_item'     => __( 'Edit Course', 'oxford-course-discovery' ),
				'search_items'  => __( 'Search Courses', 'oxford-course-discovery' ),
				'not_found'     => __( 'No courses found.', 'oxford-course-discovery' ),
				'all_items'     => __( 'All Courses', 'oxford-course-discovery' ),
				'menu_name'     => __( 'Courses', 'oxford-course-discovery' ),
			),
			'public'             => true,
			'has_archive'        => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'menu_position'      => 20,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ),
			'rewrite'            => array( 'slug' => 'courses' ),
			'capability_type'    => 'post',
			'publicly_queryable' => true,
		);
	}
}
