<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress;

use Oxford\CourseDiscovery\Domain\Collection\CourseCollection;
use Oxford\CourseDiscovery\Domain\Collection\ReferenceCollection;
use Oxford\CourseDiscovery\Domain\Collection\StartDateCollection;
use Oxford\CourseDiscovery\Domain\Course;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseName;
use Oxford\CourseDiscovery\Domain\ValueObject\Description;
use Oxford\CourseDiscovery\Domain\ValueObject\FixedPrice;
use Oxford\CourseDiscovery\Domain\ValueObject\Money;
use Oxford\CourseDiscovery\Domain\ValueObject\Price;
use Oxford\CourseDiscovery\Domain\ValueObject\Reference;
use Oxford\CourseDiscovery\Support\Hooks;
use Oxford\CourseDiscovery\WordPress\Fields\FieldKeys;
use Oxford\CourseDiscovery\WordPress\PostType\ProviderPostType;
use Oxford\CourseDiscovery\WordPress\Taxonomy\CourseCategoryTaxonomy;
use Oxford\CourseDiscovery\WordPress\Taxonomy\LocationTaxonomy;
use WP_Post;
use WP_Term;

/**
 * Hydrates {@see Course} entities from WordPress storage.
 *
 * The only class that knows the shape of a course in the database.
 * {@see self::mapMany()} primes post, meta and term caches for the whole page
 * up front: mapping results one at a time is a textbook N+1, and the derived
 * locations make it N×providers.
 */
final class CourseMapper {

	public function map( WP_Post $post ): Course {
		// mapMany() cannot return an empty collection for a non-empty input;
		// the fallback exists only to satisfy the nullable return of first().
		return $this->mapMany( array( $post ) )->first()
			?? $this->build( $post, $this->providerPosts( $post ) );
	}

	/**
	 * @param list<WP_Post> $posts Course posts.
	 */
	public function mapMany( array $posts ): CourseCollection {
		if ( array() === $posts ) {
			return CourseCollection::empty();
		}

		$related = $this->collectRelatedIds( $posts );

		if ( array() !== $related ) {
			_prime_post_caches( $related, false, true );
			update_object_term_cache( $this->providerIds( $posts ), ProviderPostType::NAME );
		}

		return CourseCollection::of(
			array_map(
				fn( WP_Post $post ): Course => $this->build( $post, $this->providerPosts( $post ) ),
				$posts
			)
		);
	}

	/**
	 * @param list<WP_Post> $providers Already loaded provider posts.
	 */
	private function build( WP_Post $post, array $providers ): Course {
		$course = new Course(
			CourseId::fromInt( $post->ID ),
			CourseName::fromString( '' === $post->post_title ? sprintf( '#%d', $post->ID ) : $post->post_title ),
			Description::fromString( (string) get_post_meta( $post->ID, FieldKeys::SHORT_DESCRIPTION, true ) ),
			Description::fromString( $post->post_content ),
			$this->price( $post->ID ),
			$this->references( $this->idsFromMeta( $post->ID, FieldKeys::INSTRUCTORS ) ),
			$this->references( $this->idsFromMeta( $post->ID, FieldKeys::PROVIDERS ) ),
			$this->locations( $providers ),
			$this->terms( $post->ID, CourseCategoryTaxonomy::NAME ),
			StartDateCollection::fromDelimitedString(
				(string) get_post_meta( $post->ID, FieldKeys::START_DATES, true )
			),
			(string) get_permalink( $post ),
			$this->thumbnailUrl( $post )
		);

		/**
		 * @see Hooks::COURSE
		 */
		$filtered = apply_filters( Hooks::COURSE, $course, $post );

		return $filtered instanceof Course ? $filtered : $course;
	}

	private function thumbnailUrl( WP_Post $post ): string {
		$url = get_the_post_thumbnail_url( $post, 'medium_large' );

		return is_string( $url ) ? $url : '';
	}

	private function price( int $courseId ): ?Price {
		$raw   = get_post_meta( $courseId, FieldKeys::PRICE, true );
		$price = null;

		if ( is_numeric( $raw ) ) {
			$currency = (string) get_post_meta( $courseId, FieldKeys::CURRENCY, true );
			$price    = FixedPrice::fromDecimal(
				(float) $raw,
				'' === $currency ? Money::DEFAULT_CURRENCY : $currency
			);
		}

		/**
		 * @see Hooks::COURSE_PRICE
		 */
		$filtered = apply_filters( Hooks::COURSE_PRICE, $price, $courseId );

		return $filtered instanceof Price ? $filtered : $price;
	}

	/**
	 * Locations are derived, never stored on the course: the union of the
	 * location terms of its providers.
	 *
	 * @param list<WP_Post> $providers Provider posts.
	 */
	private function locations( array $providers ): ReferenceCollection {
		$references = ReferenceCollection::empty();

		foreach ( $providers as $provider ) {
			$references = $references->merge( $this->terms( $provider->ID, LocationTaxonomy::NAME ) );
		}

		return $references->normalised();
	}

	private function terms( int $postId, string $taxonomy ): ReferenceCollection {
		$terms = get_the_terms( $postId, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return ReferenceCollection::empty();
		}

		$references = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$link = get_term_link( $term );

			$references[] = Reference::create(
				$term->term_id,
				$term->slug,
				$term->name,
				is_string( $link ) ? $link : ''
			);
		}

		return ReferenceCollection::of( $references )->normalised();
	}

	/**
	 * @param list<int> $ids Post IDs.
	 */
	private function references( array $ids ): ReferenceCollection {
		$references = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}

			$references[] = Reference::create(
				$post->ID,
				$post->post_name,
				$post->post_title,
				(string) get_permalink( $post )
			);
		}

		return ReferenceCollection::of( $references )->normalised();
	}

	/**
	 * ACF relationship fields store a serialised array of IDs; imports and
	 * WP-CLI may write a comma separated string. Accept both.
	 *
	 * @return list<int>
	 */
	private function idsFromMeta( int $postId, string $key ): array {
		$raw = get_post_meta( $postId, $key, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = explode( ',', $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();

		foreach ( $raw as $value ) {
			if ( is_object( $value ) && isset( $value->ID ) ) {
				$value = $value->ID;
			}

			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$ids[] = (int) $value;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param list<WP_Post> $posts Course posts.
	 *
	 * @return list<int>
	 */
	private function collectRelatedIds( array $posts ): array {
		$ids = array();

		foreach ( $posts as $post ) {
			$ids = array(
				...$ids,
				...$this->idsFromMeta( $post->ID, FieldKeys::PROVIDERS ),
				...$this->idsFromMeta( $post->ID, FieldKeys::INSTRUCTORS ),
			);
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param list<WP_Post> $posts Course posts.
	 *
	 * @return list<int>
	 */
	private function providerIds( array $posts ): array {
		$ids = array();

		foreach ( $posts as $post ) {
			$ids = array( ...$ids, ...$this->idsFromMeta( $post->ID, FieldKeys::PROVIDERS ) );
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return list<WP_Post>
	 */
	private function providerPosts( WP_Post $post ): array {
		$providers = array();

		foreach ( $this->idsFromMeta( $post->ID, FieldKeys::PROVIDERS ) as $id ) {
			$provider = get_post( $id );

			if ( $provider instanceof WP_Post && 'publish' === $provider->post_status ) {
				$providers[] = $provider;
			}
		}

		return $providers;
	}
}
