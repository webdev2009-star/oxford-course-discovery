# Testing

How this system is tested, what deserves testing, and how to keep it that way as
filters are added.

---

## Running the suites

```bash
make test-unit          # ~0.5s, no database, no WordPress
make test-integration   # ~25s, real WordPress + MySQL
make test               # both
make test-e2e           # Playwright, against the running site
```

Directly, inside the container:

```bash
cd /var/www/html/wp-content/plugins/oxford-course-discovery
vendor/bin/phpunit --configuration phpunit-unit.xml.dist
vendor/bin/phpunit --configuration phpunit-integration.xml.dist --filter CourseSearch
```

The integration bootstrap needs the WordPress test library; `bin/test.sh`
installs it automatically on first run.

---

## The four suites

| Suite | Boundary | Speed | Answers |
|---|---|---|---|
| **Unit** (`tests/Unit`) | Pure PHP. WordPress functions polyfilled in `tests/bootstrap-unit.php` | <1s | Are the domain rules right? Does the filter pipeline generate the SQL we think it does? |
| **Integration** (`tests/Integration`) | Real WordPress, real MySQL | ~25s | Does the indexer derive the right rows? Do queries return the right courses? |
| **Feature** (`tests/Feature`) | Rendered output and HTTP contract | ~5s | Does a visitor get correct, accessible markup? Is the REST contract stable? |
| **End-to-end** (`e2e/tests`) | Real browser | ~10s | Can it be operated by keyboard? Does it work without JavaScript? Does it fit a phone? |

The split is deliberate. The unit suite is fast enough to run on every save,
which is what makes it useful for the filter logic — the part most likely to be
changed. The integration suite is where WordPress' actual behaviour is checked,
because mocking `WP_Query` proves nothing about `WP_Query`.

Current: **72 unit**, **68 integration + feature**, **26 end-to-end**.

---

## What should be tested

### Always

- **Filter composition.** Values within one filter OR; separate filters AND.
  This is the requirement most likely to regress silently and the one users
  notice last.
- **Value object invariants.** Anything that parses editor input or user input:
  `StartDate`, `Money`, `FilterValue`, `Pagination`.
- **Derived data.** Every rule the indexer implements, especially locations,
  which are two hops from the course.
- **Query generation.** The SQL a constraint produces, asserted as a string.
- **The extension surface.** Each hook in `Hooks.php` should have a test that
  proves it can change behaviour — a hook nobody tests is a hook that quietly
  stops firing.
- **Accessibility contracts.** Labels, grouping, live regions, keyboard
  operation, no-JavaScript operation.

### Deliberately not tested

- WordPress core behaviour (that `wp_insert_post` inserts a post).
- CSS appearance. Layout *behaviour* is covered (single-column stacking, no
  horizontal overflow); visual design is not.
- Exact result counts against seeded data. Tests assert invariants — "the union
  is never smaller than either side" — rather than "there are 16 courses", so
  changing the seed does not break the suite.

---

## High-risk areas

Ranked by (likelihood of breaking × time to notice).

### 1. The indexer's propagation paths

Lookup rows are derived at write time. A missed event does not error — it leaves
a course quietly missing from a filter, which nobody notices until a prospective
student does.

The riskiest path is provider → course: editing a provider's locations must
reindex every course that references it. Covered by
`CourseIndexerTest::test_changing_provider_locations_reindexes_its_courses`,
plus idempotence, unpublishing, and deletion.

*Mitigation beyond tests:* `wp oxcd reindex` rebuilds everything from
`wp_posts`, and Courses → Discovery tools exposes the same action, so a missed
event is always recoverable.

### 2. Query var collisions

Filter keys share a namespace with WordPress' public query vars. This has
already produced two real bugs, both found by the tests rather than in review:

- `?provider[]=dmu` → fatal `TypeError` in `sanitize_title_for_query()`, because
  the provider post type registers a `provider` query var and WordPress passed
  our array to a string function.
- `?paged=2` → a 404, because a static front page cannot be paginated.

`QueryVarGuard` handles both, and reads the filter registry so filters added
later are protected. Any new *reserved* parameter (not a filter key) must be
added to its `RESERVED` list — that is the one place this class can be got
wrong, so it is covered end-to-end in the `no-javascript` Playwright project
where a real browser submits a real form.

### 3. Start date parsing and ordering

Free text typed by editors, sorted in a dropdown, converted to integers for
SQL. Three chances to be wrong. The regression that matters most is
lexicographic ordering putting `01-2027` before `09-2026`; it is asserted
directly in both `StartDateTest` and `FilterOptionsTest`.

### 4. Keyword search

The one feature with a runtime-dependent implementation: FULLTEXT where
available, `LIKE` otherwise. Both paths are asserted — the unit suite checks the
generated SQL for each, and `KeywordSearchTest` runs real queries.

`KeywordSearchTest` deliberately opts out of the test suite's
transaction-per-test isolation by overriding `start_transaction()` with a no-op.
**This is not a shortcut:** InnoDB does not expose uncommitted rows to
`MATCH … AGAINST`, so under the standard harness every keyword assertion would
fail despite the feature working in production. That class commits for real and
cleans up explicitly. Everything else stays in the fast, isolated suite.

### 5. Escaping in generated SQL

Constraints build SQL. `LookupConstraint` validates table and column names
against an identifier pattern and passes every value through `prepare()`;
`KeywordConstraint` strips BOOLEAN MODE operators before they reach MySQL. Both
have negative tests, including injection-shaped input.

### 6. The REST ↔ template contract

The endpoint returns server-rendered markup as well as JSON. If a template
starts depending on data the REST path does not provide, the enhanced UI breaks
while the server-rendered page keeps working — a difference nobody sees locally
with JavaScript enabled. `CoursesRestApiTest` renders through the same partial
the shortcode uses.

---

## Regression prevention strategy

**Every bug gets a test at the lowest level that reproduces it.** The two query
var collisions became a browser-level test (they are only reproducible with a
real request cycle) *and* a fix that generalises past the two known cases.

**Invariants over fixtures.** Tests assert relationships that hold for any data
(`union >= max(a, b)`, `union <= a + b`) rather than counts, so the seed can
change without a cascade of edits.

**One rule, one implementation, one test.** The AND/OR grouping rule is
implemented in `QueryPlanner`/`QueryCompiler` and proved in
`FilterCompositionTest`. Because filters cannot implement their own combining
logic, no filter can violate it, and no filter needs its own copy of that test.

**Test the seams, not the internals.** Tests target `CourseRepository`,
`FilterRegistry`, the rendered markup and the REST response. The internals of
`QueryCompiler` can be rewritten without touching a test — which is the point,
since it is the class most likely to be optimised.

**Fast feedback where the churn is.** Filter logic is the part that changes most
often, and it is covered by the sub-second suite. If the fast suite is slow,
people stop running it.

**The extension surface is a contract.** Hooks are declared in `Hooks.php` and
exercised in tests. Renaming one breaks a test rather than a third party's site.

---

## Testing a new filter consistently

A filter has at most four capabilities, and each has one thing worth asserting.
This is the checklist; `FilterCompositionTest` and `FilterOptionsTest` show
every step against the built-in filters.

### 1. Identity and registration (unit)

```php
public function test_it_registers_and_orders(): void {
    $registry = new FilterRegistry( [ new DeliveryModeFilter() ] );

    self::assertTrue( $registry->has( FilterKey::fromString( 'delivery_mode' ) ) );
    self::assertContains( 'delivery_mode', $registry->keys() );
}
```

### 2. Normalisation (unit) — if it implements `NormalisesValue`

Assert that junk is dropped and valid input is canonicalised. Include one
injection-shaped value.

```php
public function test_it_rejects_unknown_modes(): void {
    $value = ( new DeliveryModeFilter() )->normalise(
        FilterValue::fromIterable( [ 'online', 'teleportation', "'; DROP TABLE" ] )
    );

    self::assertSame( [ 'online' ], $value->toArray() );
}
```

### 3. Query contribution (unit) — if it implements `ContributesQuery`

Assert **one constraint** for many values (never one per value), then compile it
and assert the SQL against `FakeDatabaseGateway`:

```php
public function test_it_contributes_one_ored_constraint(): void {
    $plan = $this->planFor( [ 'delivery_mode' => [ 'online', 'campus' ] ] );

    self::assertCount( 1, $plan->metaConstraints );
    self::assertSame( [ 'online', 'campus' ], $plan->metaConstraints[0]->toClause()['value'] );
}

public function test_it_ands_with_other_filters(): void {
    $compiled = ( new QueryCompiler( $this->db ) )->compile(
        $this->planFor( [ 'delivery_mode' => [ 'online' ], 'provider' => [ 'dmu' ] ] )
    );

    self::assertStringContainsString( ' ) AND ( ', $compiled->whereClause() );
}
```

### 4. Options (unit or integration) — if it implements `ProvidesOptions`

Unit test with `StaticOptions`; integration test if the options come from the
database, asserting order and counts.

### 5. Real results (integration)

The assertion that matters. Create fixtures with `CourseTestCase::makeCourse()`,
then:

```php
public function test_it_filters_courses(): void {
    self::assertSame(
        [ 'Online MSc' ],
        $this->searchTitles( [ 'delivery_mode' => [ 'online' ] ] )
    );
}
```

`makeCourse()` writes meta and triggers the indexer exactly as a real save does,
so the fixture exercises the same path as production.

### 6. Rendering (feature) — if it appears in the UI

Add its key to the control list in `CourseFinderShortcodeTest`. The existing
"every input is labelled" test then covers the new control automatically, which
is why that test iterates over the markup rather than naming fields.

### Checklist

- [ ] Registers, and appears in `keys()` in the expected order
- [ ] Invalid input is dropped at the boundary
- [ ] Many values produce one constraint, not many
- [ ] It ANDs with other filters
- [ ] Options are ordered, deduplicated and counted as intended
- [ ] Real courses come back for a real request
- [ ] Its control is labelled and keyboard operable
- [ ] It survives a page-2 request and a shared URL

---

## Writing integration tests

Extend `CourseTestCase` for the fixture builders:

```php
$provider = $this->makeProvider( 'DMU', [ 'Leicester', 'India' ] );

$this->makeCourse( 'International Business', [
    'providers'         => [ $provider ],
    'categories'        => [ 'Business' ],
    'start_dates'       => '09-2029, 01-2030',
    'price'             => 12500,
    'short_description' => 'Trade, economics and management.',
] );

self::assertSame( [ 'International Business' ], $this->searchTitles( [ 'location' => [ 'india' ] ] ) );
```

Use future years in fixtures. The default ordering and the start-date facet both
hide past intakes, so `09-2020` will produce confusing results.

---

## Static analysis and style

```bash
make analyse   # PHPStan level 6 over src/
make lint      # PHP_CodeSniffer
```

PHPStan runs at level 6 with WordPress' own functions ignored — the goal is this
plugin's type correctness, not a stub set for core.

---

## Continuous integration

Not configured, deliberately: the exercise ships a reproducible environment
rather than a pipeline. A CI job would be:

```yaml
- run: docker compose up -d --build
- run: docker compose exec -T wordpress bash /usr/local/bin/oxcd/setup.sh
- run: docker compose exec -T wordpress bash /usr/local/bin/oxcd/test.sh all
- run: cd e2e && npm ci && npx playwright install --with-deps chromium && npm test
```

`bin/test.sh` and `bin/setup.sh` are both idempotent and take every value from
the environment, so they run unchanged locally and in CI.
