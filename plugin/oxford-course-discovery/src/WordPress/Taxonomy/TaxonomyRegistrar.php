<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress\Taxonomy;

/**
 * Registers every taxonomy definition on `init`, before post types.
 */
final class TaxonomyRegistrar {

	/**
	 * @param list<TaxonomyDefinition> $definitions Definitions to register.
	 */
	public function __construct( private readonly array $definitions ) {
	}

	public static function withDefaults(): self {
		return new self(
			array(
				new CourseCategoryTaxonomy(),
				new LocationTaxonomy(),
			)
		);
	}

	public function boot(): void {
		add_action( 'init', $this->register( ... ), 4 );
	}

	public function register(): void {
		foreach ( $this->definitions as $definition ) {
			/**
			 * Filter the arguments for one of the plugin's taxonomies.
			 *
			 * @param array<string, mixed> $args Registration arguments.
			 * @param string               $name Taxonomy name.
			 */
			$args = (array) apply_filters(
				'oxcd/taxonomy/args',
				$definition->args(),
				$definition->name()
			);

			register_taxonomy( $definition->name(), $definition->objectTypes(), $args );
		}
	}
}
