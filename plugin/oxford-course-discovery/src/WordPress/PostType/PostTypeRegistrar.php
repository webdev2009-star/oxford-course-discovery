<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\PostType;

/**
 * Registers every post type definition on `init`.
 */
final class PostTypeRegistrar {

	/**
	 * @param list<PostTypeDefinition> $definitions Definitions to register.
	 */
	public function __construct( private readonly array $definitions ) {
	}

	public static function withDefaults(): self {
		return new self(
			array(
				new CoursePostType(),
				new InstructorPostType(),
				new ProviderPostType(),
			)
		);
	}

	public function boot(): void {
		add_action( 'init', $this->register( ... ), 5 );
	}

	public function register(): void {
		foreach ( $this->definitions as $definition ) {
			/**
			 * Filter the arguments for one of the plugin's post types.
			 *
			 * @param array<string, mixed> $args Registration arguments.
			 * @param string               $name Post type name.
			 */
			$args = (array) apply_filters(
				'oxcd/post_type/args',
				$definition->args(),
				$definition->name()
			);

			register_post_type( $definition->name(), $args );
		}
	}

	/**
	 * @return list<string>
	 */
	public function names(): array {
		return array_map(
			static fn( PostTypeDefinition $definition ): string => $definition->name(),
			$this->definitions
		);
	}
}
