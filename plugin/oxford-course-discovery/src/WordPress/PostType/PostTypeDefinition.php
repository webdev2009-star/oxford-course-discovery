<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\PostType;

/**
 * Describes a post type without registering it.
 *
 * Splitting "what the post type is" from "when it gets registered" keeps the
 * definitions unit-testable (assert on the array, no WordPress needed) and
 * lets {@see PostTypeRegistrar} own the single `init` hook.
 */
interface PostTypeDefinition {

	public function name(): string;

	/**
	 * @return array<string, mixed> Arguments for `register_post_type()`.
	 */
	public function args(): array;
}
