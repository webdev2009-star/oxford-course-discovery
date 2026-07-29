<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Support;

use Oxford\CourseDiscovery\Database\DatabaseGateway;

/**
 * In-memory gateway for unit tests.
 *
 * Implements the same interface as the `$wpdb` gateway, including a faithful
 * enough `prepare()` (positional `%s`/`%d`, quoting, escaping) that the SQL a
 * constraint produces can be asserted on character for character — without a
 * database, and therefore fast enough to run on every keystroke.
 */
final class FakeDatabaseGateway implements DatabaseGateway {

	/**
	 * @var list<string> Every statement passed to execute().
	 */
	public array $executed = array();

	/**
	 * @var list<array<string, string|null>>
	 */
	public array $nextResults = array();

	/**
	 * @var list<string>
	 */
	public array $nextColumn = array();

	public function __construct(
		private readonly string $prefix = 'wp_',
		private readonly bool $fullText = true,
		private readonly bool $tablesExist = true
	) {
	}

	public function table( string $name ): string {
		return $this->prefix . $name;
	}

	public function postsTable(): string {
		return $this->prefix . 'posts';
	}

	public function termsTable(): string {
		return $this->prefix . 'terms';
	}

	public function prepare( string $sql, array $args ): string {
		if ( array() === $args ) {
			return $sql;
		}

		$index = 0;

		return (string) preg_replace_callback(
			'/%[sdf]/',
			static function ( array $matches ) use ( $args, &$index ): string {
				$value = $args[ $index ] ?? '';
				++$index;

				return match ( $matches[0] ) {
					'%d'    => (string) (int) $value,
					'%f'    => (string) (float) $value,
					default => "'" . str_replace( "'", "\\'", (string) $value ) . "'",
				};
			},
			$sql
		);
	}

	public function results( string $sql ): array {
		return $this->nextResults;
	}

	public function column( string $sql ): array {
		return $this->nextColumn;
	}

	public function scalar( string $sql ): ?string {
		return $this->nextColumn[0] ?? null;
	}

	public function execute( string $sql ): int {
		$this->executed[] = $sql;

		return 1;
	}

	public function supportsFullText(): bool {
		return $this->fullText;
	}

	public function tableExists( string $name ): bool {
		return $this->tablesExist;
	}

	public function charsetCollate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
}
