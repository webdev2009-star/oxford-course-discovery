<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Options;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\WordPress\Taxonomy\LocationTaxonomy;

/**
 * Locations reachable through at least one published course.
 *
 * Locations live on the provider, so the naive route is: load providers, load
 * their terms, de-duplicate in PHP. The derived lookup table collapses that to
 * one grouped query and gives accurate per-location course counts.
 */
final class LocationOptions implements OptionsSource {

	public function __construct( private readonly DatabaseGateway $db ) {
	}

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		if ( ! $this->db->tableExists( Schema::LOCATIONS ) ) {
			return ( new TermOptions( LocationTaxonomy::NAME, false, false ) )->options( $criteria );
		}

		$sql = sprintf(
			'SELECT t.slug AS value, t.name AS label, COUNT( DISTINCT l.%s ) AS total
			 FROM %s l
			 INNER JOIN %s t ON t.term_id = l.location_id
			 GROUP BY t.term_id, t.slug, t.name
			 ORDER BY t.name ASC',
			Schema::COURSE_COLUMN,
			$this->db->table( Schema::LOCATIONS ),
			$this->db->termsTable()
		);

		$rows = $this->db->results( $sql );

		if ( array() === $rows ) {
			return ( new TermOptions( LocationTaxonomy::NAME, false, false ) )->options( $criteria );
		}

		return FilterOptionCollection::of(
			array_map(
				static fn( array $row ): FilterOption => FilterOption::create(
					(string) ( $row['value'] ?? '' ),
					(string) ( $row['label'] ?? '' ),
					(int) ( $row['total'] ?? 0 )
				),
				array_values( array_filter( $rows, static fn( array $row ): bool => '' !== (string) ( $row['value'] ?? '' ) ) )
			)
		);
	}

	public function cacheKey( SearchCriteria $criteria ): string {
		return 'locations';
	}
}
