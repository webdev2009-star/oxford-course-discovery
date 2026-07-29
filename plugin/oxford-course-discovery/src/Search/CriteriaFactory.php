<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Search;

use Oxford\CourseDiscovery\Filter\FilterRegistry;
use Oxford\CourseDiscovery\Filter\FilterValue;
use Oxford\CourseDiscovery\Filter\NormalisesValue;
use Oxford\CourseDiscovery\Filter\TransformsCriteria;
use Oxford\CourseDiscovery\Support\Hooks;

/**
 * The request boundary: untrusted input in, validated {@see SearchCriteria} out.
 *
 * Only registered filters are read, so an unknown parameter cannot smuggle a
 * value into the query, and each filter validates its own input through
 * {@see NormalisesValue}. Everything downstream can assume well-formed criteria
 * — no `isset()`, no re-sanitising, no defensive casts.
 */
final class CriteriaFactory {

	public function __construct( private readonly FilterRegistry $filters ) {
	}

	/**
	 * @param array<string, mixed> $request Typically `$_GET` or REST params.
	 */
	public function fromRequest( array $request ): SearchCriteria {
		/**
		 * @see Hooks::REQUEST
		 */
		$request = (array) apply_filters( Hooks::REQUEST, $request );

		$values = FilterValues::empty();

		foreach ( $this->filters->all() as $filter ) {
			$key = $filter->key();
			$raw = $request[ $key->value ] ?? null;

			if ( null === $raw ) {
				continue;
			}

			$value = FilterValue::fromRequest( $raw );

			if ( $filter instanceof NormalisesValue ) {
				$value = $filter->normalise( $value );
			}

			if ( ! $value->isEmpty() ) {
				$values = $values->with( $key, $value );
			}
		}

		$criteria = SearchCriteria::create(
			$values,
			$this->pagination( $request ),
			Ordering::fromRequest( $request['orderby'] ?? null, Orderings::available() )
		);

		foreach ( $this->filters->providing( TransformsCriteria::class ) as $filter ) {
			$criteria = $filter->transform( $criteria );
		}

		/**
		 * @see Hooks::CRITERIA
		 */
		$filtered = apply_filters( Hooks::CRITERIA, $criteria );

		return $filtered instanceof SearchCriteria ? $filtered : $criteria;
	}

	/**
	 * @param array<string, mixed> $request Request data.
	 */
	private function pagination( array $request ): Pagination {
		$page    = $request['paged'] ?? $request['page'] ?? 1;
		$perPage = $request['per_page'] ?? Pagination::DEFAULT_PER_PAGE;

		return Pagination::of(
			is_numeric( $page ) ? (int) $page : 1,
			is_numeric( $perPage ) ? (int) $perPage : Pagination::DEFAULT_PER_PAGE
		);
	}
}
