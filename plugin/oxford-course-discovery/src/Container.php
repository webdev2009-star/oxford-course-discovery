<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Migrator;
use Oxford\CourseDiscovery\Database\WpdbGateway;
use Oxford\CourseDiscovery\Filter\Filters\CategoryFilter;
use Oxford\CourseDiscovery\Filter\Filters\KeywordFilter;
use Oxford\CourseDiscovery\Filter\Filters\LocationFilter;
use Oxford\CourseDiscovery\Filter\Filters\ProviderFilter;
use Oxford\CourseDiscovery\Filter\Filters\StartDateFilter;
use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Filter\Options\CachingOptions;
use Oxford\CourseDiscovery\Filter\Options\LocationOptions;
use Oxford\CourseDiscovery\Filter\Options\ProviderOptions;
use Oxford\CourseDiscovery\Filter\Options\StartDateOptions;
use Oxford\CourseDiscovery\Filter\Options\TermOptions;
use Oxford\CourseDiscovery\Frontend\Assets;
use Oxford\CourseDiscovery\Frontend\CourseFinder;
use Oxford\CourseDiscovery\Frontend\Shortcode;
use Oxford\CourseDiscovery\Frontend\TemplateRenderer;
use Oxford\CourseDiscovery\Http\CoursesController;
use Oxford\CourseDiscovery\Indexing\CourseIndexer;
use Oxford\CourseDiscovery\Query\CourseRepository;
use Oxford\CourseDiscovery\Query\QueryCompiler;
use Oxford\CourseDiscovery\Query\QueryPlanner;
use Oxford\CourseDiscovery\Query\WpCourseRepository;
use Oxford\CourseDiscovery\Search\CriteriaFactory;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\WordPress\Admin\CourseAdmin;
use Oxford\CourseDiscovery\WordPress\CourseMapper;
use Oxford\CourseDiscovery\WordPress\Fields\AcfFields;
use Oxford\CourseDiscovery\WordPress\Fields\MetaBoxFields;
use Oxford\CourseDiscovery\WordPress\PostType\PostTypeRegistrar;
use Oxford\CourseDiscovery\WordPress\QueryVarGuard;
use Oxford\CourseDiscovery\WordPress\Taxonomy\TaxonomyRegistrar;

/**
 * Composition root.
 *
 * Hand written and lazily memoised rather than a DI framework: the graph is
 * small enough to read at a glance, and nothing is constructed until something
 * asks for it — most WordPress requests touch none of this. Every collaborator
 * is constructor injected, so tests can swap the gateway or repository without
 * touching globals.
 */
final class Container {

	/**
	 * @var array<string, object>
	 */
	private array $services = array();

	public function __construct( private readonly string $pluginFile ) {
	}

	public function pluginFile(): string {
		return $this->pluginFile;
	}

	public function baseDir(): string {
		return plugin_dir_path( $this->pluginFile );
	}

	public function baseUrl(): string {
		return plugin_dir_url( $this->pluginFile );
	}

	public function version(): string {
		return defined( __NAMESPACE__ . '\VERSION' ) ? VERSION : '0.0.0';
	}

	public function database(): DatabaseGateway {
		return $this->service( DatabaseGateway::class, static fn(): DatabaseGateway => WpdbGateway::fromGlobals() );
	}

	public function migrator(): Migrator {
		return $this->service( Migrator::class, fn(): Migrator => Migrator::withDefaults( $this->database() ) );
	}

	public function indexer(): CourseIndexer {
		return $this->service( CourseIndexer::class, fn(): CourseIndexer => new CourseIndexer( $this->database() ) );
	}

	/**
	 * The filter registry, built once and then opened up to integrations.
	 *
	 * Built-in filters are registered first so extensions can inspect,
	 * reorder, replace or remove them.
	 */
	public function filters(): FilterRegistry {
		return $this->service(
			FilterRegistry::class,
			function (): FilterRegistry {
				$db = $this->database();

				$registry = new FilterRegistry(
					array(
						new KeywordFilter(),
						new ProviderFilter( new CachingOptions( new ProviderOptions( $db ) ) ),
						new LocationFilter( new CachingOptions( new LocationOptions( $db ) ) ),
						new StartDateFilter( new CachingOptions( new StartDateOptions( $db ) ) ),
						new CategoryFilter( new CachingOptions( new TermOptions( \Oxford\CourseDiscovery\WordPress\Taxonomy\CourseCategoryTaxonomy::NAME ) ) ),
					)
				);

				/**
				 * @see Hooks::REGISTER_FILTERS
				 */
				do_action( Hooks::REGISTER_FILTERS, $registry );

				return $registry;
			}
		);
	}

	public function criteriaFactory(): CriteriaFactory {
		return $this->service( CriteriaFactory::class, fn(): CriteriaFactory => new CriteriaFactory( $this->filters() ) );
	}

	public function planner(): QueryPlanner {
		return $this->service( QueryPlanner::class, fn(): QueryPlanner => new QueryPlanner( $this->filters() ) );
	}

	public function compiler(): QueryCompiler {
		return $this->service( QueryCompiler::class, fn(): QueryCompiler => new QueryCompiler( $this->database() ) );
	}

	public function mapper(): CourseMapper {
		return $this->service( CourseMapper::class, static fn(): CourseMapper => new CourseMapper() );
	}

	public function repository(): CourseRepository {
		return $this->service(
			CourseRepository::class,
			fn(): CourseRepository => new WpCourseRepository( $this->planner(), $this->compiler(), $this->mapper() )
		);
	}

	public function finder(): CourseFinder {
		return $this->service(
			CourseFinder::class,
			fn(): CourseFinder => new CourseFinder( $this->filters(), $this->criteriaFactory(), $this->repository() )
		);
	}

	public function renderer(): TemplateRenderer {
		return $this->service(
			TemplateRenderer::class,
			fn(): TemplateRenderer => new TemplateRenderer( $this->baseDir() . 'templates' )
		);
	}

	public function assets(): Assets {
		return $this->service(
			Assets::class,
			fn(): Assets => new Assets( $this->baseUrl(), $this->baseDir(), $this->version() )
		);
	}

	public function shortcode(): Shortcode {
		return $this->service(
			Shortcode::class,
			fn(): Shortcode => new Shortcode( $this->finder(), $this->renderer(), $this->assets() )
		);
	}

	public function restController(): CoursesController {
		return $this->service(
			CoursesController::class,
			fn(): CoursesController => new CoursesController( $this->finder(), $this->filters(), $this->renderer() )
		);
	}

	public function queryVarGuard(): QueryVarGuard {
		return $this->service( QueryVarGuard::class, fn(): QueryVarGuard => new QueryVarGuard( $this->filters() ) );
	}

	public function postTypes(): PostTypeRegistrar {
		return $this->service( PostTypeRegistrar::class, static fn(): PostTypeRegistrar => PostTypeRegistrar::withDefaults() );
	}

	public function taxonomies(): TaxonomyRegistrar {
		return $this->service( TaxonomyRegistrar::class, static fn(): TaxonomyRegistrar => TaxonomyRegistrar::withDefaults() );
	}

	public function acfFields(): AcfFields {
		return $this->service( AcfFields::class, static fn(): AcfFields => new AcfFields() );
	}

	public function metaBoxFields(): MetaBoxFields {
		return $this->service( MetaBoxFields::class, static fn(): MetaBoxFields => new MetaBoxFields() );
	}

	public function courseAdmin(): CourseAdmin {
		return $this->service(
			CourseAdmin::class,
			fn(): CourseAdmin => new CourseAdmin( $this->mapper(), $this->indexer(), $this->migrator() )
		);
	}

	/**
	 * @template TService of object
	 *
	 * @param class-string<TService> $id      Service identifier.
	 * @param callable(): TService   $factory Factory, invoked at most once.
	 *
	 * @return TService
	 */
	private function service( string $id, callable $factory ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			$this->services[ $id ] = $factory();
		}

		/** @var TService $service */
		$service = $this->services[ $id ];

		return $service;
	}
}
