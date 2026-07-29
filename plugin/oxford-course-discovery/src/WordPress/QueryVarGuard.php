<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\WordPress;

use Oxford\CourseDiscovery\Filter\FilterRegistry;

/**
 * Keeps the finder's query string out of WordPress' main query.
 *
 * The finder needs readable URLs (`?provider[]=dmu&paged=2`), which puts its
 * parameters in the same namespace as WordPress' own public query vars. Three
 * separate failures follow from that, all of them silent until a user hits them:
 *
 * - `provider` is a query var registered by the provider post type, so
 *   WordPress hands our *array* of slugs to `sanitize_title_for_query()`,
 *   which expects a string, and the request dies with a `TypeError`;
 * - `paged` on a page with a static front page makes the main query a
 *   non-existent page 2 and returns a 404 instead of the finder;
 * - `orderby` is applied to the main query, which then no longer matches the
 *   page being displayed.
 *
 * The finder parses the request itself, so the main query never needs any of
 * these. They are stripped here — but only when they arrived in the query
 * string, so pretty permalink pagination (`/blog/page/2/`, which populates
 * `paged` through rewrite rules rather than `$_GET`) is left alone.
 *
 * Filter keys are read from the registry, so a filter added by a third party is
 * protected on the day it is registered.
 */
final class QueryVarGuard {

	/**
	 * Parameters the finder owns in addition to the registered filter keys.
	 */
	private const RESERVED = array( 'paged', 'page', 'per_page', 'orderby', 'order' );

	public function __construct( private readonly FilterRegistry $filters ) {
	}

	public function boot(): void {
		add_filter( 'request', $this->stripFinderVars( ... ), 1 );
		add_filter( 'redirect_canonical', $this->allowFinderUrls( ... ), 10, 2 );
	}

	/**
	 * @param array<string, mixed> $vars Public query vars for the main query.
	 *
	 * @return array<string, mixed>
	 */
	public function stripFinderVars( array $vars ): array {
		foreach ( $this->ownedParameters() as $key ) {
			if ( $this->cameFromQueryString( $key ) ) {
				unset( $vars[ $key ] );
			}
		}

		return $vars;
	}

	/**
	 * @param string|false $redirectUrl  Proposed canonical URL.
	 * @param string       $requestedUrl Requested URL.
	 *
	 * @return string|false
	 */
	public function allowFinderUrls( string|false $redirectUrl, string $requestedUrl ): string|false {
		foreach ( $this->ownedParameters() as $key ) {
			if ( $this->cameFromQueryString( $key ) ) {
				return false;
			}
		}

		return $redirectUrl;
	}

	/**
	 * @return list<string>
	 */
	private function ownedParameters(): array {
		return array( ...$this->filters->keys(), ...self::RESERVED );
	}

	private function cameFromQueryString( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only inspection of a public search URL.
		return isset( $_GET[ $key ] );
	}
}
