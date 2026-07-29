<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Filter\Options;

use Oxford\CourseDiscovery\Database\DatabaseGateway;
use Oxford\CourseDiscovery\Database\Schema;
use Oxford\CourseDiscovery\Domain\ValueObject\StartDate;
use Oxford\CourseDiscovery\Filter\FilterOption;
use Oxford\CourseDiscovery\Filter\FilterOptionCollection;
use Oxford\CourseDiscovery\Search\SearchCriteria;

/**
 * Distinct intakes, chronologically ordered.
 *
 * The brief requires the dropdown to be in chronological order. Editors type
 * `{month}-{year}` strings, which sort lexicographically (`01-2027` before
 * `09-2026`) and duplicate across courses. The indexer stores each intake as
 * an integer `sort_key` (`YYYYMM`), so ordering is an indexed integer sort and
 * de-duplication is a `DISTINCT` — no PHP sorting of the whole catalogue.
 */
final class StartDateOptions implements OptionsSource {

	/**
	 * @param bool $upcomingOnly Hide intakes that have already started.
	 */
	public function __construct(
		private readonly DatabaseGateway $db,
		private readonly bool $upcomingOnly = true
	) {
	}

	public function options( SearchCriteria $criteria ): FilterOptionCollection {
		if ( ! $this->db->tableExists( Schema::START_DATES ) ) {
			return FilterOptionCollection::empty();
		}

		$sql = sprintf(
			'SELECT sort_key, COUNT( DISTINCT %s ) AS total
			 FROM %s
			 WHERE sort_key >= %%d
			 GROUP BY sort_key
			 ORDER BY sort_key ASC',
			Schema::COURSE_COLUMN,
			$this->db->table( Schema::START_DATES )
		);

		$rows = $this->db->results(
			$this->db->prepare( $sql, array( $this->upcomingOnly ? $this->currentSortKey() : 0 ) )
		);

		$options = array();

		foreach ( $rows as $row ) {
			$sortKey = (int) ( $row['sort_key'] ?? 0 );

			if ( $sortKey < 197001 ) {
				continue;
			}

			$date = StartDate::fromSortKey( $sortKey );

			$options[] = FilterOption::create(
				$date->toString(),
				$date->label(),
				(int) ( $row['total'] ?? 0 )
			);
		}

		return FilterOptionCollection::of( $options );
	}

	public function cacheKey( SearchCriteria $criteria ): string {
		return sprintf( 'start_dates:%d:%d', (int) $this->upcomingOnly, $this->currentSortKey() );
	}

	private function currentSortKey(): int {
		return (int) gmdate( 'Y' ) * 100 + (int) gmdate( 'n' );
	}
}
