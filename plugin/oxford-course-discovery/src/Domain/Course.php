<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain;

use Oxford\CourseDiscovery\Domain\Collection\ReferenceCollection;
use Oxford\CourseDiscovery\Domain\Collection\StartDateCollection;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseName;
use Oxford\CourseDiscovery\Domain\ValueObject\Description;
use Oxford\CourseDiscovery\Domain\ValueObject\Price;
use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;

/**
 * A course as the discovery domain understands it.
 *
 * Deliberately a read model: it is hydrated from WordPress by
 * {@see \Oxford\CourseDiscovery\WordPress\CourseMapper} and never writes back.
 * Templates, the REST controller and tests all consume this type instead of
 * `WP_Post` plus a pile of `get_post_meta()` calls, so a storage change stays
 * behind the mapper.
 */
final readonly class Course {

	/**
	 * @param CourseId            $id               Identity.
	 * @param CourseName          $name             Course name.
	 * @param Description         $shortDescription Teaser copy.
	 * @param Description         $longDescription  Full copy.
	 * @param Price|null          $price            Price, null when not published yet.
	 * @param ReferenceCollection $instructors      Instructor references.
	 * @param ReferenceCollection $providers        Provider references.
	 * @param ReferenceCollection $locations        Locations derived from providers.
	 * @param ReferenceCollection $categories       Category references.
	 * @param StartDateCollection $startDates       Intakes, chronological.
	 * @param string              $url              Permalink.
	 * @param string              $imageUrl         Featured image URL, may be empty.
	 */
	public function __construct(
		public CourseId $id,
		public CourseName $name,
		public Description $shortDescription,
		public Description $longDescription,
		public ?Price $price,
		public ReferenceCollection $instructors,
		public ReferenceCollection $providers,
		public ReferenceCollection $locations,
		public ReferenceCollection $categories,
		public StartDateCollection $startDates,
		public string $url = '',
		public string $imageUrl = ''
	) {
	}

	/**
	 * The intake a prospective student would join next.
	 */
	public function nextStartDate( ?StartDate $from = null ): ?StartDate {
		return $this->startDates->next( $from ?? self::today() );
	}

	public function hasPrice(): bool {
		return $this->price instanceof Price;
	}

	/**
	 * Display price, or a neutral fallback so templates stay free of branching.
	 */
	public function formattedPrice(): string {
		return $this->price instanceof Price
			? $this->price->format()
			: __( 'Price on application', 'oxford-course-discovery' );
	}

	/**
	 * Flat, JSON friendly representation used by the REST controller.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'               => $this->id->toInt(),
			'name'             => $this->name->value,
			'url'              => $this->url,
			'imageUrl'         => $this->imageUrl,
			'shortDescription' => $this->shortDescription->plain(),
			'price'            => $this->hasPrice()
				? array(
					'from'      => $this->price->from()->toDecimal(),
					'to'        => $this->price->to()->toDecimal(),
					'currency'  => $this->price->from()->currency,
					'formatted' => $this->price->format(),
				)
				: null,
			'instructors'      => $this->referencesToArray( $this->instructors ),
			'providers'        => $this->referencesToArray( $this->providers ),
			'locations'        => $this->referencesToArray( $this->locations ),
			'categories'       => $this->referencesToArray( $this->categories ),
			'startDates'       => $this->startDates->map(
				static fn( StartDate $date ): array => array(
					'value' => $date->toString(),
					'label' => $date->label(),
				)
			),
		);
	}

	/**
	 * @param ReferenceCollection $references References to flatten.
	 *
	 * @return list<array{id: int, slug: string, name: string, url: string}>
	 */
	private function referencesToArray( ReferenceCollection $references ): array {
		return $references->map(
			static fn( $reference ): array => array(
				'id'   => $reference->id,
				'slug' => $reference->slug,
				'name' => $reference->name,
				'url'  => $reference->url,
			)
		);
	}

	private static function today(): StartDate {
		return StartDate::fromMonthAndYear( (int) gmdate( 'n' ), (int) gmdate( 'Y' ) );
	}
}
