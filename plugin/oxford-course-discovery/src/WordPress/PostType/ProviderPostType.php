<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\PostType;

/**
 * Partner institutions. A provider carries the locations it teaches at, which
 * is where a course's locations are derived from.
 */
final class ProviderPostType implements PostTypeDefinition {

	public const NAME = 'provider';

	public function name(): string {
		return self::NAME;
	}

	public function args(): array {
		return array(
			'labels'          => array(
				'name'          => __( 'Providers', 'oxford-course-discovery' ),
				'singular_name' => __( 'Provider', 'oxford-course-discovery' ),
				'add_new_item'  => __( 'Add New Provider', 'oxford-course-discovery' ),
				'edit_item'     => __( 'Edit Provider', 'oxford-course-discovery' ),
				'menu_name'     => __( 'Providers', 'oxford-course-discovery' ),
			),
			'public'          => true,
			'has_archive'     => false,
			'show_in_rest'    => true,
			'menu_icon'       => 'dashicons-building',
			'menu_position'   => 22,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'rewrite'         => array( 'slug' => 'providers' ),
			'capability_type' => 'post',
			// Providers are reached through pretty permalinks and the finder,
			// never through `?provider=slug`. Suppressing the query var frees
			// the name for the provider *filter* — see QueryVarGuard.
			'query_var'       => false,
		);
	}
}
