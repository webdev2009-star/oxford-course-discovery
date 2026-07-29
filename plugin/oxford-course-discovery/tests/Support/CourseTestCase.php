<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Support;

use Oxford\CourseDiscovery\Container;
use Oxford\CourseDiscovery\WordPress\Fields\FieldKeys;
use Oxford\CourseDiscovery\WordPress\PostType\CoursePostType;
use Oxford\CourseDiscovery\WordPress\PostType\InstructorPostType;
use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;
use Oxford\CourseDiscovery\WordPress\Taxonomy\CourseCategoryTaxonomy;
use Oxford\CourseDiscovery\WordPress\Taxonomy\LocationTaxonomy;
use WP_UnitTestCase;

/**
 * Shared fixture builders for the integration and feature suites.
 *
 * Creating content through the real WordPress APIs (rather than inserting rows)
 * means the indexer's hooks fire exactly as they do in production — which is
 * the thing most likely to break and least likely to be noticed.
 */
abstract class CourseTestCase extends WP_UnitTestCase {

	protected function container(): Container {
		return \Oxford\CourseDiscovery\plugin()->container();
	}

	protected function setUp(): void {
		parent::setUp();

		$this->container()->migrator()->migrate();
	}

	/**
	 * @param list<string> $locations Location term names.
	 */
	protected function makeProvider( string $name, array $locations = array() ): int {
		$providerId = (int) self::factory()->post->create(
			array(
				'post_type'   => ProviderPostType::NAME,
				'post_title'  => $name,
				'post_name'   => sanitize_title( $name ),
				'post_status' => 'publish',
			)
		);

		if ( array() !== $locations ) {
			wp_set_object_terms( $providerId, $locations, LocationTaxonomy::NAME );
		}

		return $providerId;
	}

	protected function makeInstructor( string $name ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => InstructorPostType::NAME,
				'post_title'  => $name,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * @param string               $title      Course title.
	 * @param array<string, mixed> $attributes Any of: short_description, content,
	 *                            price, providers (list<int>), instructors
	 *                            (list<int>), start_dates, categories
	 *                            (list<string>), status.
	 */
	protected function makeCourse( string $title, array $attributes = array() ): int {
		$courseId = (int) self::factory()->post->create(
			array(
				'post_type'    => CoursePostType::NAME,
				'post_title'   => $title,
				'post_status'  => $attributes['status'] ?? 'publish',
				'post_content' => $attributes['content'] ?? '',
			)
		);

		update_post_meta( $courseId, FieldKeys::SHORT_DESCRIPTION, $attributes['short_description'] ?? '' );
		update_post_meta( $courseId, FieldKeys::PRICE, (string) ( $attributes['price'] ?? '' ) );
		update_post_meta( $courseId, FieldKeys::START_DATES, $attributes['start_dates'] ?? '' );
		update_post_meta( $courseId, FieldKeys::PROVIDERS, $attributes['providers'] ?? array() );
		update_post_meta( $courseId, FieldKeys::INSTRUCTORS, $attributes['instructors'] ?? array() );

		if ( isset( $attributes['categories'] ) ) {
			wp_set_object_terms( $courseId, $attributes['categories'], CourseCategoryTaxonomy::NAME );
		}

		// Meta is written after wp_insert_post(), so trigger the indexer the
		// same way a real save would once everything is in place.
		$this->container()->indexer()->index( $courseId );

		return $courseId;
	}

	/**
	 * @param array<string, mixed> $request Request parameters.
	 *
	 * @return list<string> Matching course titles, in result order.
	 */
	protected function searchTitles( array $request ): array {
		$criteria = $this->container()->criteriaFactory()->fromRequest( $request );
		$results  = $this->container()->repository()->search( $criteria );

		return $results->courses->map(
			static fn( $course ): string => $course->name->value
		);
	}
}
