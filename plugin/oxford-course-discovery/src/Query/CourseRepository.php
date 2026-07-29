<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Query;

use Oxford\CourseDiscovery\Domain\Course;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Search\SearchCriteria;
use Oxford\CourseDiscovery\Search\SearchResults;

/**
 * Reading courses.
 *
 * The whole application talks to this interface, never to `WP_Query`. That is
 * what makes the "move to Elasticsearch" story in docs/PERFORMANCE.md a new
 * implementation rather than a rewrite: templates, the REST controller and the
 * shortcode would not change.
 */
interface CourseRepository {

	public function search( SearchCriteria $criteria ): SearchResults;

	public function find( CourseId $id ): ?Course;

	/**
	 * Number of matches, without materialising the page of results.
	 */
	public function count( SearchCriteria $criteria ): int;
}
