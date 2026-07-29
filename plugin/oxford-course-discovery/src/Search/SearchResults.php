<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

use Oxford\CourseDiscovery\Domain\Collection\CourseCollection;

/**
 * A page of courses plus the metadata a UI needs to describe it.
 */
final readonly class SearchResults {

	private function __construct(
		public CourseCollection $courses,
		public int $total,
		public Pagination $pagination,
		public SearchCriteria $criteria
	) {
	}

	public static function create(
		CourseCollection $courses,
		int $total,
		Pagination $pagination,
		SearchCriteria $criteria
	): self {
		return new self( $courses, max( 0, $total ), $pagination, $criteria );
	}

	public static function emptyFor( SearchCriteria $criteria ): self {
		return new self( CourseCollection::empty(), 0, $criteria->pagination, $criteria );
	}

	public function withCourses( CourseCollection $courses ): self {
		return new self( $courses, $this->total, $this->pagination, $this->criteria );
	}

	public function isEmpty(): bool {
		return 0 === $this->total;
	}

	public function totalPages(): int {
		return $this->pagination->totalPages( $this->total );
	}

	public function currentPage(): int {
		return $this->pagination->page;
	}

	public function hasNextPage(): bool {
		return $this->currentPage() < $this->totalPages();
	}

	public function hasPreviousPage(): bool {
		return $this->currentPage() > 1;
	}

	/**
	 * 1 based index of the first result on this page, 0 when empty.
	 */
	public function firstResultIndex(): int {
		return $this->isEmpty() ? 0 : $this->pagination->offset() + 1;
	}

	public function lastResultIndex(): int {
		return min( $this->total, $this->pagination->offset() + $this->courses->count() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'courses'    => $this->courses->toArrays(),
			'pagination' => array(
				'total'       => $this->total,
				'page'        => $this->currentPage(),
				'perPage'     => $this->pagination->perPage,
				'totalPages'  => $this->totalPages(),
				'hasNext'     => $this->hasNextPage(),
				'hasPrevious' => $this->hasPreviousPage(),
				'firstResult' => $this->firstResultIndex(),
				'lastResult'  => $this->lastResultIndex(),
			),
		);
	}
}
