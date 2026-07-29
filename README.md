# Oxford Course Discovery

An extensible Course Discovery system for WordPress: course, instructor and
provider post types, a composable filter pipeline, and an accessible course
finder that works with or without JavaScript.

Built for the Oxford International pre-interview task. No plugins beyond
Advanced Custom Fields.

---

## Contents

- [Quick start](#quick-start)
- [Environment requirements](#environment-requirements)
- [Database setup](#database-setup)
- [Development commands](#development-commands)
- [Testing](#testing)
- [Architecture](#architecture)
- [Extending the system](#extending-the-system)
- [Assumptions](#assumptions)
- [Further documentation](#further-documentation)

---

## Quick start

Docker is the only requirement.

**macOS / Linux** (needs `make`):

```bash
git clone https://github.com/webdev2009-star/course-discovery.git
cd course-discovery
cp .env.example .env      # optional; every value has a working default
make setup                # build, start, install WordPress, seed 48 courses
```

**Windows** (PowerShell — `make` is usually not installed):

```powershell
git clone https://github.com/webdev2009-star/course-discovery.git
cd course-discovery
.\bin\dev.ps1 setup
```

**Any platform, no wrapper** — this is all either script does:

```bash
docker compose up -d --build
docker compose exec wordpress bash /usr/local/bin/oxcd/setup.sh
```

Then open:

| | |
|---|---|
| Course finder | <http://localhost:8080/> |
| Admin | <http://localhost:8080/wp-admin> (`admin` / `password`) |
| REST API | <http://localhost:8080/wp-json/oxcd/v1/courses> |

Setup is idempotent — re-run it any time.

### Deploying it

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). The production image provisions
itself on first boot — install, migrations, seed — and every step is a no-op
afterwards, so a redeploy needs no manual follow-up.

```bash
# Railway: railway.json is already in the repo; add a MySQL service and deploy.
# Any Docker host:
cp .env.production.example .env
docker compose -f docker-compose.prod.yml up -d --build
```

### Installing into an existing WordPress site

The plugin is a self-contained directory with no build step:

```bash
cp -r plugin/oxford-course-discovery /path/to/wp-content/plugins/
cd /path/to/wp-content/plugins/oxford-course-discovery && composer install --no-dev
wp plugin activate oxford-course-discovery
wp oxcd migrate
```

Composer is optional — a PSR-4 fallback autoloader ships in the bootstrap file,
so the plugin also runs from a plain copy of the directory.

---

## Environment requirements

| Requirement | Version | Notes |
|---|---|---|
| PHP | 8.2+ | Enums, readonly classes, first-class callables |
| WordPress | 6.4+ | Tested on 6.8 |
| MySQL / MariaDB | 5.7+ / 10.3+ | FULLTEXT search needs MySQL 5.6+; older servers fall back to `LIKE` |
| Composer | 2.x | Development and testing only |
| Node.js | 18+ | End-to-end tests only |
| Docker | 24+ | Only for the containerised environment |

Advanced Custom Fields (free tier) is optional. When present it supplies the
editing UI; when absent the plugin registers equivalent native metaboxes. Both
write identical post meta, so content survives either being switched.

---

## Database setup

Nothing manual. The plugin owns four tables, created by versioned migrations
that run on activation, on load when the recorded schema version is behind, and
on demand via WP-CLI:

```bash
wp oxcd migrate     # run pending migrations
wp oxcd reindex     # rebuild the lookup tables from wp_posts
```

| Table | Purpose |
|---|---|
| `{prefix}oxcd_course_start_dates` | One row per (course, intake) with an integer `YYYYMM` sort key |
| `{prefix}oxcd_course_providers` | Course → provider, flattened from the ACF relationship field |
| `{prefix}oxcd_course_locations` | Course → location, derived transitively through providers |
| `{prefix}oxcd_course_search` | Denormalised, FULLTEXT indexed copy of the searchable fields |

These exist because the brief's filters cannot be served efficiently from
WordPress' native storage — providers are a serialised array in `postmeta`,
start dates are free text that will not sort chronologically, and locations are
not stored on the course at all. The reasoning, and when you would go further,
is in [docs/PERFORMANCE.md](docs/PERFORMANCE.md).

All four are derived data: dropping and rebuilding them loses nothing.

---

## Development commands

`make help` lists everything. On Windows use `.\bin\dev.ps1 <command>`, which
mirrors every target below. The common ones:

| Command | Description |
|---|---|
| `make setup` | Build, start, install WordPress, seed demo content |
| `make up` / `make down` | Start / stop the stack (data preserved) |
| `make destroy` | Stop and delete all volumes |
| `make shell` | Shell inside the WordPress container |
| `make logs` | Tail the WordPress logs |
| `make test` | Every PHP suite |
| `make test-unit` | Fast unit suite (no WordPress, <1s) |
| `make test-integration` | Integration + feature suites |
| `make test-e2e` | Playwright suite (runs on the host) |
| `make lint` / `make analyse` | PHP_CodeSniffer / PHPStan |
| `make seed` | Regenerate demo content |
| `make reindex` | Rebuild the lookup tables |
| `make wp CMD="plugin list"` | Any WP-CLI command |

### WP-CLI commands added by the plugin

```bash
wp oxcd migrate                          # run pending migrations
wp oxcd reindex [--batch=200]            # rebuild lookup and search tables
wp oxcd seed [--courses=48] [--fresh]    # generate demo content
```

---

## Testing

```bash
make test          # unit + integration + feature
make test-e2e      # end-to-end
```

Four suites, each with a different job:

| Suite | Location | Runtime | What it proves |
|---|---|---|---|
| Unit | `tests/Unit` | <1s | Domain rules and the SQL the filter pipeline generates, with no database |
| Integration | `tests/Integration` | ~25s | Indexer, migrations and real query results against MySQL |
| Feature | `tests/Feature` | ~5s | Rendered markup and the REST contract |
| End-to-end | `e2e/tests` | ~10s | Keyboard operation, no-JavaScript fallback, mobile layout |

Current state: **72 unit**, **68 integration + feature**, **26 end-to-end** —
all passing.

[docs/TESTING.md](docs/TESTING.md) covers what should be tested, the high-risk
areas, the regression prevention strategy, and how to test a new filter
consistently.

---

## Architecture

```
Request  ──▶ CriteriaFactory ──▶ SearchCriteria ──▶ QueryPlanner ──▶ QueryPlan
                    │                                      │
             (filters normalise             (filters contribute one constraint each)
              their own input)                             │
                                                           ▼
Templates ◀── FinderResult ◀── CourseRepository ◀── QueryCompiler ──▶ CompiledQuery
                                     │                                    │
                              CourseMapper ◀── WP_Query ◀── WP_Query args + SQL
```

### Layers

| Namespace | Responsibility | Knows about WordPress? |
|---|---|---|
| `Domain` | Courses, prices, intakes, typed collections | No |
| `Filter` | Filter contracts, registry, option sources | No |
| `Search` | Criteria, pagination, ordering, results | No |
| `Query` | Plan, constraints, compiler, repository contract | Only in `WpCourseRepository` |
| `Database` | Gateway, migrations, schema | Behind an interface |
| `Indexing` | Keeping lookup tables in step | Yes |
| `Wordpress` | Post types, taxonomies, fields, admin, mapper | Yes |
| `Frontend` / `Http` | Shortcode, templates, REST | Yes |

### Key decisions

**Composition over inheritance in the filter layer.** A filter implements
`Filter` (identity only). Everything else is an opt-in capability interface:
`ProvidesOptions`, `ContributesQuery`, `NormalisesValue`, `TransformsCriteria`.
The pipeline discovers capabilities with `instanceof` rather than calling no-op
methods on a fat base class, so a new filter implements exactly what it needs.
Even identity is composed — filters hold a `FilterDefinition` value object
instead of extending a base class.

**Options are a separate collaborator.** `OptionsSource` is where a filter's
choices come from. `CachingOptions` is a decorator over it, not a subclass of a
filter, which is why caching applies uniformly to filters that have not been
written yet.

**One place implements the AND/OR rule.** Each filter contributes at most one
constraint holding all of its selected values; the compiler joins constraints
with `AND`. Values inside a constraint become an `IN (…)`. A new filter inherits
both halves of the brief's grouping rule for free.

**A query plan, not a query.** `QueryPlan` is an immutable, storage-agnostic
description of the search. It is compiled to `WP_Query` arguments and SQL in one
place, which means the plan can be asserted on in unit tests with no database,
and `oxcd/query/plan` listeners manipulate constraints as data rather than by
string-munging SQL.

**No primitives in the domain.** `CourseId`, `CourseName`, `Money`, `StartDate`,
`FilterKey`, `FilterValue`, `Pagination`, `Ordering` are value objects that
validate on construction. `StartDate` is the clearest case: it owns parsing of
the editor's `{month}-{year}`, the integer sort key that makes chronological
ordering possible, and the display label.

**Price is an interface.** The brief specifies a single numeric price but
anticipates ranges. `Price` has `from()`, `to()` and `format()`; `FixedPrice`
and `PriceRange` implement it. Supporting price bands is a new implementation
plus a hook, not a change to any consumer.

**Lookup tables, not meta queries.** See [docs/PERFORMANCE.md](docs/PERFORMANCE.md).

**Progressive enhancement, one source of markup.** The shortcode renders
complete results server side; every search has a shareable URL and works with
JavaScript disabled. The REST endpoint returns both JSON *and* the server
rendered results markup, so the client never reimplements a course card.

**A guard for query var collisions.** Readable filter URLs (`?provider[]=dmu`)
share a namespace with WordPress' own public query vars. `QueryVarGuard` strips
the finder's parameters from the main query — but only when they arrived in the
query string, leaving pretty-permalink pagination untouched. Without it,
`?provider[]=…` is a fatal `TypeError` inside `WP_Query` and `?paged=2` is a
404. It reads the filter registry, so filters added later are protected
automatically.

---

## Extending the system

Everything below is possible from a separate plugin, with no change to this one.
Hook names are declared and documented in `src/Support/Hooks.php`.

### Register a new filter

```php
add_action( 'oxcd/filters/register', function ( FilterRegistry $registry ): void {
    $registry->register( new DeliveryModeFilter() );
} );
```

```php
final class DeliveryModeFilter implements Filter, ProvidesOptions, ContributesQuery {

    public function key(): FilterKey       { return FilterKey::fromString( 'delivery_mode' ); }
    public function label(): string        { return __( 'Delivery mode', 'my-plugin' ); }
    public function control(): FilterControl { return FilterControl::Checkboxes; }
    public function priority(): int        { return 60; }

    public function options( SearchCriteria $criteria ): FilterOptionCollection {
        return FilterOptionCollection::fromPairs( [
            'online' => __( 'Online', 'my-plugin' ),
            'campus' => __( 'On campus', 'my-plugin' ),
        ] );
    }

    public function contribute( FilterValue $value, QueryPlan $plan, SearchCriteria $criteria ): QueryPlan {
        return $plan->withMetaConstraint( MetaConstraint::anyOf( 'delivery_mode', $value->toArray() ) );
    }
}
```

That is the whole integration. The filter is rendered in the UI, accepted from
the query string, exposed as a REST argument, included in the AND/OR grouping
and covered by the shared filter test contract — with no core change.

### Alter the options of an existing filter

```php
add_filter( 'oxcd/filter/options', function ( $options, FilterKey $key ) {
    return 'location' === $key->value
        ? $options->filter( fn( $o ) => 'antarctica' !== $o->value )
        : $options;
}, 10, 3 );
```

### Modify the query

```php
// Add a constraint.
add_filter( 'oxcd/query/plan', fn( QueryPlan $plan ) =>
    $plan->withMetaConstraint( MetaConstraint::where( 'featured', '1' ) ) );

// Remove one a built-in filter added.
add_filter( 'oxcd/query/plan', fn( QueryPlan $plan ) =>
    $plan->withoutConstraintsMatching( 'oxcd_course_providers' ) );

// Reach the raw WP_Query arguments.
add_filter( 'oxcd/query/args', function ( array $args ) {
    $args['post_status'] = [ 'publish', 'private' ];
    return $args;
} );
```

### Transform the search criteria

```php
// Pin a category on a landing page.
add_filter( 'oxcd/criteria', fn( SearchCriteria $criteria ) =>
    is_page( 'design-courses' )
        ? $criteria->withFilter( FilterKey::fromString( 'category' ), FilterValue::fromScalar( 'design' ) )
        : $criteria );
```

### Customise result ordering

```php
add_filter( 'oxcd/orderings', function ( array $orderings ) {
    $orderings['duration'] = __( 'Shortest first', 'my-plugin' );
    return $orderings;
} );

add_filter( 'oxcd/query/orderby', function ( string $orderby, Ordering $ordering ) {
    return $ordering->is( 'duration' ) ? 'course_duration ASC' : $orderby;
}, 10, 2 );
```

### Replace a built-in filter, or drop one

```php
add_action( 'oxcd/filters/register', function ( FilterRegistry $registry ): void {
    $registry->unregister( FilterKey::fromString( 'start_date' ) );
    $registry->replace( new MyProviderFilter() );
} );
```

### Override the markup

Copy any template from `plugin/oxford-course-discovery/templates/` into
`your-theme/oxford-course-discovery/` — same path, same name. Or filter
`oxcd/template/candidates` to point elsewhere.

### The complete hook list

| Hook | Type | Purpose |
|---|---|---|
| `oxcd/filters/register` | action | Register, replace or remove filters |
| `oxcd/filter/options` | filter | Alter the options of a filter |
| `oxcd/criteria/request` | filter | Rewrite the raw request |
| `oxcd/criteria` | filter | Transform the parsed search |
| `oxcd/query/plan` | filter | Add, remove or rewrite constraints |
| `oxcd/query/args` | filter | Alter the compiled `WP_Query` arguments |
| `oxcd/query/where` | filter | Alter the generated SQL fragments |
| `oxcd/query/orderby` | filter | Alter the `ORDER BY` expression |
| `oxcd/orderings` | filter | Register orderings for the UI |
| `oxcd/course` | filter | Alter a hydrated course |
| `oxcd/course/price` | filter | Replace the price model (e.g. with a range) |
| `oxcd/results` | filter | Post-process the result set |
| `oxcd/course/indexed` | action | React to a course being reindexed |
| `oxcd/template/candidates` | filter | Change template resolution |
| `oxcd/cache/options_ttl` | filter | Tune or disable facet caching |
| `oxcd/post_type/args`, `oxcd/taxonomy/args` | filter | Adjust registration |

---

## Assumptions

Decisions taken where the brief left room, and why.

1. **Start dates are entered as a comma separated list.** ACF repeaters are a
   Pro feature and the brief allows only ACF. A validated text field
   (`09-2026, 01-2027`) keeps the plugin on the free tier. Input is validated at
   the editing boundary and canonicalised on save; the storage format is
   irrelevant to queries because intakes are indexed as integers.

2. **Locations are a taxonomy on providers, not on courses.** The brief calls
   locations "derived from provider", so making them directly editable on a
   course would create a second source of truth. Courses receive the union of
   their providers' locations through the lookup table.

3. **Categories use a real hierarchical taxonomy.** Term relationships are
   already well indexed and give descendant matching (selecting "Design" also
   returns "Graphic Design") for free, so this is the one built-in filter that
   does not need a lookup table.

4. **The long description is `post_content`.** Editors get the normal editor,
   and the field is covered by revisions. The short description is meta.

5. **A single price point in GBP by default.** Currency is stored per course and
   the `Price` interface already supports ranges; the seeded data uses one
   price, as specified.

6. **Published courses only.** Drafts and private courses are excluded from
   discovery and removed from the index. `oxcd/query/args` can change that.

7. **Facet counts reflect the whole catalogue, not the current selection.**
   Recomputing every facet per request costs one query per filter; contextual
   counts are a documented extension point (`ProvidesOptions` receives the
   criteria) rather than a default.

8. **Past intakes are hidden from the start date dropdown.** A prospective
   student cannot enrol on an intake that has begun. `StartDateOptions` takes a
   flag to include them.

9. **The finder is a shortcode.** `[course_finder]` works in the block editor,
   in widgets and in page builders, and a page carrying it is created on
   activation. A block would bind the UI to one editing context.

10. **No pagination-crawling protection or rate limiting.** Out of scope for the
    exercise; page size is clamped to 60 so the obvious abuse is bounded.

11. **The default seed produces 48 courses across 6 providers and 8 locations.**
    Enough to exercise every filter and paginate; not enough to demonstrate
    scale. Load-testing at 100k+ courses is discussed in the performance
    document rather than performed.

---

## Further documentation

| Document | Contents |
|---|---|
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Railway and Docker host deployment, environment variables, operations |
| [docs/TESTING.md](docs/TESTING.md) | Test strategy, high-risk areas, regression prevention, how to test a new filter |
| [docs/PERFORMANCE.md](docs/PERFORMANCE.md) | Bottlenecks, meta query limits, indexing, caching, pagination, and the path to millions of courses |

---

## Project layout

```
.
├── docker-compose.yml            Development stack (WordPress, MariaDB)
├── docker-compose.prod.yml       Production stack, with optional HTTPS via Caddy
├── railway.json                  Railway build and deploy configuration
├── docker/                       Development and production images
├── bin/                          setup, test and developer scripts (sh + PowerShell)
├── Makefile                      Developer entry points
├── docs/                         Testing and performance documentation
├── e2e/                          Playwright suite
└── plugin/oxford-course-discovery/
    ├── oxford-course-discovery.php
    ├── src/
    │   ├── Domain/               Entities, value objects, typed collections
    │   ├── Filter/               Contracts, registry, options, built-in filters
    │   ├── Search/               Criteria, pagination, ordering, results
    │   ├── Query/                Plan, constraints, compiler, repository
    │   ├── Database/             Gateway, migrations, schema
    │   ├── Indexing/             Lookup table maintenance
    │   ├── Wordpress/            Post types, taxonomies, fields, admin, mapper
    │   ├── Frontend/             Shortcode, templates, view models
    │   ├── Http/                 REST controller
    │   └── Cli/                  WP-CLI commands
    ├── templates/                Overridable markup
    ├── assets/                   CSS and the progressive-enhancement module
    └── tests/                    Unit, integration and feature suites
```
