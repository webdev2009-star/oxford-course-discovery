<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

use Oxford\CourseDiscovery\Filter\FilterKey;
use Oxford\CourseDiscovery\Filter\FilterValue;

/**
 * Everything the user asked for, in one immutable object.
 *
 * Request parsing produces it, filters read it, the query plan derives from it,
 * and "the same search on page 2" is regenerated from it. Every mutator returns
 * a new instance, so an `oxcd/criteria` listener that rewrites the search
 * cannot corrupt state another listener already read.
 */
final readonly class SearchCriteria {

	private function __construct(
		public FilterValues $filters,
		public Pagination $pagination,
		public Ordering $ordering
	) {
	}

	public static function create(
		?FilterValues $filters = null,
		?Pagination $pagination = null,
		?Ordering $ordering = null
	): self {
		return new self(
			$filters ?? FilterValues::empty(),
			$pagination ?? Pagination::firstPage(),
			$ordering ?? Ordering::default()
		);
	}

	public static function empty(): self {
		return self::create();
	}

	public function withFilters( FilterValues $filters ): self {
		return new self( $filters, $this->pagination, $this->ordering );
	}

	public function withFilter( FilterKey $key, FilterValue $value ): self {
		return new self( $this->filters->with( $key, $value ), $this->pagination, $this->ordering );
	}

	public function withoutFilter( FilterKey $key ): self {
		return new self( $this->filters->without( $key ), $this->pagination, $this->ordering );
	}

	public function withPagination( Pagination $pagination ): self {
		return new self( $this->filters, $pagination, $this->ordering );
	}

	public function withPage( int $page ): self {
		return $this->withPagination( $this->pagination->withPage( $page ) );
	}

	public function withOrdering( Ordering $ordering ): self {
		return new self( $this->filters, $this->pagination, $ordering );
	}

	public function valueFor( FilterKey $key ): FilterValue {
		return $this->filters->get( $key );
	}

	public function hasFilter( FilterKey $key ): bool {
		return $this->filters->has( $key );
	}

	public function isFiltered(): bool {
		return ! $this->filters->isEmpty();
	}

	/**
	 * Query string parameters that reproduce this search.
	 *
	 * @return array<string, mixed>
	 */
	public function toQueryVars(): array {
		$vars = $this->filters->toArray();

		if ( $this->pagination->page > 1 ) {
			$vars['paged'] = $this->pagination->page;
		}

		if ( Pagination::DEFAULT_PER_PAGE !== $this->pagination->perPage ) {
			$vars['per_page'] = $this->pagination->perPage;
		}

		$vars['orderby'] = $this->ordering->toRequestValue();

		return $vars;
	}

	/**
	 * Stable identity for caching: same search, same key, regardless of the
	 * order parameters arrived in.
	 */
	public function fingerprint(): string {
		$vars = $this->toQueryVars();
		ksort( $vars );

		return md5( (string) wp_json_encode( $vars ) );
	}
}
