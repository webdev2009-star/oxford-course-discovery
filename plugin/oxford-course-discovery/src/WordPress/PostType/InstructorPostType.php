<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\PostType;

/**
 * People who teach courses.
 */
final class InstructorPostType implements PostTypeDefinition {

	public const NAME = 'instructor';

	public function name(): string {
		return self::NAME;
	}

	public function args(): array {
		return array(
			'labels'          => array(
				'name'          => __( 'Instructors', 'oxford-course-discovery' ),
				'singular_name' => __( 'Instructor', 'oxford-course-discovery' ),
				'add_new_item'  => __( 'Add New Instructor', 'oxford-course-discovery' ),
				'edit_item'     => __( 'Edit Instructor', 'oxford-course-discovery' ),
				'menu_name'     => __( 'Instructors', 'oxford-course-discovery' ),
			),
			'public'          => true,
			'has_archive'     => false,
			'show_in_rest'    => true,
			'menu_icon'       => 'dashicons-businessperson',
			'menu_position'   => 21,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'rewrite'         => array( 'slug' => 'instructors' ),
			'capability_type' => 'post',
			'query_var'       => false,
		);
	}
}
