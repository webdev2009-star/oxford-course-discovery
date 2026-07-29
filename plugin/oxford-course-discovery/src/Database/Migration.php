<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Database;

/**
 * One versioned, forward-only schema change.
 */
interface Migration {

	/**
	 * Monotonically increasing version. Migrations run in this order and each
	 * runs at most once per site.
	 */
	public function version(): int;

	public function name(): string;

	public function up( DatabaseGateway $db ): void;
}
