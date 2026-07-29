# Performance and scalability

The brief asks for analysis rather than optimisation. This documents where this
design would hurt, why the lookup tables exist, and what the next moves are at
each order of magnitude.

Sizes below assume the shape of a real course catalogue: ~10 filters, a handful
of providers per course, 2–4 intakes per course, and read traffic dominated by
anonymous visitors.

---

## Expected bottlenecks

In the order they would actually bite.

### 1. Facet option queries

Every page view renders four dropdowns, each needing a `GROUP BY` over a lookup
table. That is four queries before a single course is fetched, and they are
identical for every visitor.

Mitigated by `CachingOptions`, a transient decorator with a version key the
indexer bumps on every write. Invalidation is one option update, not a key hunt.
At scale, `GROUP BY` over a multi-million row table is a scan — see
[Facet counts](#facet-counts-are-the-first-thing-to-break).

### 2. `SQL_CALC_FOUND_ROWS`

`WP_Query` needs a total to paginate, so it sets `SQL_CALC_FOUND_ROWS`, which
makes MySQL evaluate the whole result set before returning 12 rows. It is
removed in MySQL 8.0.17+ and is the single most expensive part of a filtered
query on a large table.

Not addressed here (`no_found_rows` is `false` because the UI shows a count),
but the fix is scoped: `SearchResults` already separates the count from the
page, so switching to an estimated or cached count touches one class.

### 3. Deep pagination

`LIMIT 12 OFFSET 12000` makes MySQL walk 12,012 rows. Page 1000 is roughly 1000×
the cost of page 1. Bounded here by clamping page size to 60 and by nobody
paginating that far in practice; see [Pagination strategy](#pagination-strategy).

### 4. Ordering by a correlated subquery

The default "soonest start date" ordering is a correlated subquery per row:

```sql
COALESCE( ( SELECT MIN( sd.sort_key ) FROM wp_oxcd_course_start_dates sd
            WHERE sd.course_id = wp_posts.ID AND sd.sort_key >= 202607 ), 999999 ) ASC
```

The `(sort_key, course_id)` index makes each lookup cheap, but it still runs per
candidate row and cannot be resolved from an index alone. At scale, the
denormalised alternative is one `next_start_key` column on the search table,
updated by the indexer — an indexed sort instead of a subquery. That is a
migration plus one line in the compiler.

### 5. Course hydration

Mapping a page of results loads posts, meta and terms. `CourseMapper::mapMany()`
primes all three caches for the whole page up front, so it is a fixed number of
queries rather than N+1 (or N×providers, since locations are two hops away).
With a persistent object cache this is mostly served from memory.

---

## Limitations of WordPress meta queries

The reason for the lookup tables, concretely.

**`wp_postmeta` has no useful index on `meta_value`.** The schema indexes
`meta_key` and `post_id`. `meta_value` is `LONGTEXT`, which cannot be fully
indexed. Every value comparison is a scan of the rows matching `meta_key`.

**Each meta clause is another `JOIN` onto the same table.** Three meta filters
means three self-joins of a table with 20+ rows per post. The optimiser's row
estimates degrade quickly, and it starts choosing bad plans.

**Serialised values cannot be queried.** ACF relationship fields store
`a:2:{i:0;s:2:"12";i:1;s:2:"47";}`. Matching provider 12 means
`meta_value LIKE '%"12"%'` — unindexable, and it also matches `120`, `"12"` in
another position, and any post whose serialised blob happens to contain that
substring.

**Text does not sort as data.** Intakes stored as `09-2026` sort
lexicographically: `01-2027` before `09-2026`. There is no `ORDER BY` that fixes
this without `CAST`ing per row, which discards any index.

**Derived relationships are inexpressible.** A course's locations belong to its
*providers*. There is no `meta_query` or `tax_query` for "terms of the posts
referenced by this post's meta". Without a lookup table it is a PHP loop over
providers — N+1 at best, and unfilterable at the database level.

**Where meta queries are still fine:** single scalar comparisons on a
low-cardinality key over a modest catalogue. `MetaConstraint` exists for exactly
that, and third party filters can use it without a migration.

---

## Indexing considerations

Every plugin table is narrow, integer-keyed, and indexed for the one access
pattern it serves.

| Table | Key | Serves |
|---|---|---|
| `oxcd_course_start_dates` | `UNIQUE (course_id, sort_key)` | Deduplication; "the intakes of this course" |
| | `KEY (sort_key, course_id)` | Filter by intake, and the facet `GROUP BY` — covering, so no table access |
| `oxcd_course_providers` | `UNIQUE (course_id, provider_id)` | Deduplication |
| | `KEY (provider_slug, course_id)` | Filter by provider — covering |
| | `KEY (provider_id)` | Reindex-on-provider-save |
| `oxcd_course_locations` | `UNIQUE (course_id, location_id, provider_id)` | Deduplication, and which provider contributed a location |
| | `KEY (location_slug, course_id)` | Filter by location — covering |
| `oxcd_course_search` | `PRIMARY (course_id)` | Replace-on-index |
| | `FULLTEXT (name, short_description, long_description)` | Keyword matching and relevance |

Three deliberate choices:

**The filter value is the leading column, the course ID second.** Both filter
queries and facet counts are then satisfied from the index alone — MySQL never
touches the table.

**Slugs are denormalised alongside IDs.** Filters match the value that appears
in the URL without a term or post lookup per request. The cost is that renaming
a provider requires a reindex, which the indexer already does on save.

**Semi-joins, not joins.** Constraints compile to
`posts.ID IN (SELECT course_id FROM …)` rather than `INNER JOIN`. Joining a
one-to-many table multiplies result rows and forces `DISTINCT`, which defeats
index-ordered scans and makes `LIMIT` read far more rows than it returns.

The tables WordPress owns matter too: `wp_postmeta(post_id, meta_key)` and
`wp_term_relationships(object_id, term_taxonomy_id)` are indexed by core, and
this design keeps both on their happy paths.

---

## Query performance

A fully filtered search (keyword + provider + location + intake + category) is
one `WP_Query` that compiles to roughly:

```sql
SELECT SQL_CALC_FOUND_ROWS wp_posts.ID FROM wp_posts
  INNER JOIN wp_term_relationships ON …          -- category
WHERE post_type = 'course' AND post_status = 'publish'
  AND ( wp_posts.ID IN ( SELECT course_id FROM …search  WHERE MATCH (…) AGAINST (…) ) )
  AND ( wp_posts.ID IN ( SELECT course_id FROM …providers WHERE provider_slug IN (…) ) )
  AND ( wp_posts.ID IN ( SELECT course_id FROM …locations WHERE location_slug IN (…) ) )
  AND ( wp_posts.ID IN ( SELECT course_id FROM …start_dates WHERE sort_key IN (…) ) )
ORDER BY COALESCE( ( SELECT MIN( sd.sort_key ) … ), 999999 ) ASC, wp_posts.ID ASC
LIMIT 0, 12
```

Every subquery is an index-only lookup. The remaining costs are the
`SQL_CALC_FOUND_ROWS` full evaluation and the correlated `ORDER BY`, both
identified above.

The query count per page view is fixed and small: one search, four facet queries
(cached), and the priming queries for posts, meta and terms. It does not grow
with the number of results or the number of filters applied.

---

## Caching opportunities

Ordered by value for effort.

1. **Facet options** — implemented. Version-keyed transients, invalidated by the
   indexer, tunable or disableable through `oxcd/cache/options_ttl`.
2. **A persistent object cache** (Redis or Memcached). WordPress caches posts,
   meta and terms per request; making that persistent removes most of the
   hydration cost across requests. Nothing in the plugin needs to change.
3. **Result set IDs.** `SearchCriteria::fingerprint()` already produces a stable
   hash of a search, and `QueryPlan::identities()` a stable hash of its
   constraints. Caching ID lists against that key is a small addition to
   `WpCourseRepository`; the front end would still hydrate from the object
   cache, so content edits stay visible.
4. **Full page cache** for anonymous visitors. The finder is a `GET` with all
   state in the URL, so it is cacheable as-is. The REST endpoint returns
   rendered markup, which makes the enhanced path equally cacheable at the edge.
5. **HTTP caching on the REST route.** `ETag`/`Last-Modified` derived from the
   index version would make repeat filter changes conditional requests.

Not cached deliberately: individual course hydration (the object cache already
covers it) and result counts (they must reflect the filters, and a wrong count
is worse than a slow one).

---

## Pagination strategy

**Now.** Offset pagination, page size clamped to 60, a five-page window either
side of the current page, and a stable secondary sort on `wp_posts.ID` so a row
can never be repeated or skipped between pages — a real bug when the primary
sort has ties, which "soonest intake" frequently does.

**At scale.** Offset pagination degrades linearly with depth. Options, in the
order they become worthwhile:

- **Cap the reachable depth.** Search engines and users almost never go beyond
  page ~50; capping it removes the worst case outright.
- **Keyset (seek) pagination.** `WHERE (sort_key, ID) > (:lastKey, :lastId)`
  is constant time at any depth. It costs random access to page N, which suits
  infinite scroll and "load more" better than numbered pages.
- **Cached ID lists.** Fetch and cache the first N pages of IDs for popular
  filter combinations; deep pages fall back to the live query.

`Pagination` is a value object and the repository returns `SearchResults`, so
changing strategy does not touch templates or the REST contract.

---

## Search optimisation

**Now.** A denormalised FULLTEXT table. Keyword terms become a BOOLEAN MODE
expression where every word is required and prefix-matched (`+design*
+foundation*`): more words means fewer, better results, and partial words still
match. Relevance ranking reuses the same expression, so ordering by relevance
costs nothing extra. Where FULLTEXT is unavailable the same table is queried
with `LIKE`, so search still works — slower, unranked, but correct.

**Known limits.** MySQL's default stopword list and minimum word length
(`innodb_ft_min_token_size`, 3 by default) mean short terms can be silently
dropped. There is no stemming, no synonyms, no typo tolerance, and no
multilingual analysis. Relevance is TF-IDF over three fields with no field
weighting — a title match does not outrank a body match.

**Next steps, in order.** Add field weighting by scoring `name` separately and
combining; add a curated synonym expansion at query build time (a filter on
`oxcd/query/plan` can already do this); tune `innodb_ft_min_token_size` for
course codes. Beyond that, the answer is a real search engine.

---

## Scaling to hundreds of thousands, or millions

The design already separates "what the user asked for" from "how it is
answered", so each step below replaces one component rather than the system.

### ~10,000 courses — today's design, unchanged

Filters are index-only lookups; facets are cached. Nothing here needs work.
Add a persistent object cache if traffic warrants it.

### ~100,000 courses — tune what exists

- **Denormalise `next_start_key`** onto `oxcd_course_search` to replace the
  correlated `ORDER BY` with an indexed sort.
- **Drop `SQL_CALC_FOUND_ROWS`.** Cache counts per filter combination, or show
  "1,000+" past a threshold, as large catalogues generally do.
- **Move the indexer off the request.** `wp oxcd reindex` already batches; a
  provider with 5,000 courses should enqueue an Action Scheduler job on save
  rather than rebuild inline.
- **Cap pagination depth** and cache the ID lists for the common combinations.

Still one database, still WordPress. This is where most real course catalogues
stop.

#### Facet counts are the first thing to break

Before result queries become a problem, `GROUP BY` over a multi-million row
lookup table will. Counts are also the least valuable part of the UI. In order:
lengthen the cache TTL; precompute counts into a summary table maintained by the
indexer; then drop exact counts in favour of ranges, or remove them.

### ~1,000,000+ courses — an external search index

At this point the cost is not any single query, it is that a relational database
is the wrong shape for faceted search: every filter combination is a different
access path, and there is no index that serves all of them.

Move to Elasticsearch or OpenSearch, with one denormalised document per course
containing every filterable field. Facets become a single aggregation request
instead of one query per filter; relevance, stemming, synonyms and typo
tolerance come with the engine.

**What that migration touches, concretely:**

- A new `ElasticCourseRepository implements CourseRepository`.
- A new compiler translating `QueryPlan` constraints into the engine's query
  DSL. The plan is already a declarative constraint list rather than SQL, which
  is what makes this a translation rather than a rewrite.
- The indexer gains a second destination alongside the lookup tables.

**What it does not touch:** the domain, the filters, `SearchCriteria`,
`SearchResults`, the templates, the shortcode, or the REST controller. They
depend on the `CourseRepository` interface, not on `WP_Query`. That is the
architectural payoff — the abstraction exists so this stays a component swap.

Keep MySQL as the source of truth and the fallback: search indices drift, and
being able to serve a degraded catalogue while reindexing beats an outage.

---

## When to introduce each technique

| Technique | Introduce when | Cost |
|---|---|---|
| Lookup tables | Filtering on serialised meta, derived fields, or anything needing ordering | Write-time indexing, reindex on schema change — **done** |
| Transient facet cache | Facet queries appear in slow query logs | Staleness bounded by TTL — **done** |
| Persistent object cache | Traffic, not catalogue size, is the pressure | Infrastructure |
| Denormalised sort columns | `ORDER BY` shows up in `EXPLAIN` as a filesort or correlated subquery | Another derived column to keep in step |
| Precomputed facet counts | `GROUP BY` over lookup tables becomes the slowest query | Counts lag content |
| Keyset pagination | Deep pages are actually requested | No random access to page N |
| External search engine | Facet aggregation across millions of documents; or you need stemming, synonyms, typo tolerance | New infrastructure, reindex pipeline, consistency lag |

The signal for each is a measurement, not a course count. `EXPLAIN` on the
generated query, the slow query log, and `SAVEQUERIES` in development will say
which of these is due long before a threshold does.
