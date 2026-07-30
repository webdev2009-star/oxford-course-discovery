# Deployment

The production image provisions itself: on first boot it installs WordPress,
activates the plugin, runs the migrations and seeds demo content. On every boot
after that, each of those steps is a no-op. A redeploy is therefore safe, and
there is no manual post-deploy checklist.

Verified locally before writing this: first boot provisions in ~40s, a restart
re-runs the entrypoint without re-seeding, the filter API returns correct
results through the production Apache config, and `/healthcheck.php` flips to
503 when the database is stopped and back to 200 when it returns.

- [Railway](#railway) — recommended for a few weeks
- [Any Docker host / VPS](#any-docker-host)
- [Environment variables](#environment-variables)
- [What the entrypoint does](#what-the-entrypoint-does)
- [Health checks](#health-checks)
- [Operations](#operations)
- [Not Vercel](#not-vercel)

---

## Railway

Two services: this repository, and Railway's MySQL. Total setup is about five
minutes.

### 1. Create the project

```bash
npm i -g @railway/cli
railway login
railway init            # or: create the project in the dashboard
```

Or in the dashboard: **New Project → Deploy from GitHub repo →
`webdev2009-star/course-discovery`**.

`railway.json` is already in the repository, so Railway builds
`docker/production/Dockerfile` without any configuration.

### 2. Add MySQL

**New → Database → Add MySQL**, **in the same project as the app service**.

> MySQL, not Postgres. WordPress requires MySQL or MariaDB, and this plugin's
> search index uses a `FULLTEXT` key.

Then reference it from the **app** service's variables — Railway does not share
variables between services automatically:

| Variable | Value |
|---|---|
| `MYSQLHOST` | `${{MySQL.MYSQLHOST}}` |
| `MYSQLPORT` | `${{MySQL.MYSQLPORT}}` |
| `MYSQLUSER` | `${{MySQL.MYSQLUSER}}` |
| `MYSQLPASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `MYSQLDATABASE` | `${{MySQL.MYSQLDATABASE}}` |

Or the same thing in one variable: `MYSQL_URL` = `${{MySQL.MYSQL_URL}}`. The
entrypoint parses either. Prefer the five if the generated password might
contain an `@`, which would break URL parsing.

Or from the CLI:

```bash
railway variables --service <app> --set 'MYSQLHOST=${{MySQL.MYSQLHOST}}'
# …and the other four
```

#### Two traps worth knowing

**References only resolve inside one project.** If the database and the app are
in *different* Railway projects, `${{MySQL.MYSQLHOST}}` resolves to an **empty
string** — not an error, and not the literal text. The app then starts with no
database and restart-loops on:

```
No database configured. Set WORDPRESS_DB_HOST, or attach a MySQL service.
```

Since a failing container never serves a page, this presents as a *health check*
failure, which sends you looking in the wrong place entirely. Check the values
first:

```bash
railway variables --service <app> --json | grep MYSQL
```

Empty values mean the reference is not bound. Adding a database to the right
project is not enough on its own — a reference created while the target did not
exist stays unbound, so re-set the variables afterwards.

**A mistyped service name behaves differently.** Then Railway passes the literal
`${{MySQL.MYSQLHOST}}` through, and the entrypoint stops immediately naming the
variable rather than timing out against a nonsense host.

### 3. Set the variables

On the app service, add:

| Variable | Value |
|---|---|
| `ADMIN_PASSWORD` | something you choose |
| `ADMIN_EMAIL` | your email |
| `SITE_TITLE` | `Course Discovery` |
| `SEED_COURSES` | `48` |

Then generate a set of salts from
<https://api.wordpress.org/secret-key/1.1/salt/> and add the eight values
(`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`,
`SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`). Skipping this works, but
each redeploy generates new salts and logs you out.

`SITE_URL` is **not** needed: the entrypoint derives it from
`RAILWAY_PUBLIC_DOMAIN`. Set it only if you attach a custom domain and Railway's
generated hostname should not be canonical.

### 4. Generate the domain and deploy

**Settings → Networking → Generate Domain**, then deploy. Railway sets `$PORT`;
the entrypoint configures Apache to listen on it.

`railway.json` sets `healthcheckPath` to `/healthcheck.php` with a 600 second
timeout — first boot has to install WordPress, download ACF and seed the
catalogue before Apache starts answering, and a shorter timeout would mark a
perfectly good deploy as failed. See [Health checks](#health-checks).

Watch the deploy log — you should see:

```
[oxcd] Site URL: https://your-app.up.railway.app
[oxcd] Apache listening on 8080
[oxcd] Database is up
[oxcd] Installing WordPress
[oxcd] Running migrations
[oxcd] Seeding 48 demo courses
[oxcd] Ready — serving https://your-app.up.railway.app
```

### 5. Add a volume for uploads

**Settings → Volumes → Add Volume**, mount path
`/var/www/html/wp-content/uploads`.

Railway's filesystem is otherwise ephemeral. The database is a separate service
so content survives regardless, but without this volume any images uploaded
through the admin disappear on the next deploy. The seeded demo data has no
uploads, so this is optional for a demo and required for anything real.

### Cost

Roughly $5/month of usage credit covers the app and MySQL at demo traffic
levels. `sleepApplication` is set to `false` in `railway.json` so the app does
not cold-start on the reviewer's first request — if you would rather trade that
for lower cost, set it to `true`.

---

## Any Docker host

For a VPS (DigitalOcean, Hetzner, Linode) or anywhere else with Docker.

```bash
git clone https://github.com/webdev2009-star/course-discovery.git
cd course-discovery
cp .env.production.example .env
# fill in SITE_URL, ADMIN_PASSWORD, DB_PASSWORD, DB_ROOT_PASSWORD
docker compose -f docker-compose.prod.yml up -d --build
```

That serves on port 80. Follow the provisioning:

```bash
docker compose -f docker-compose.prod.yml logs -f app
```

### With HTTPS

Point your domain's A record at the host, then:

```bash
# in .env
DOMAIN=courses.example.com
ACME_EMAIL=you@example.com
SITE_URL=https://courses.example.com
HTTP_PORT=8080          # frees port 80 for Caddy

docker compose -f docker-compose.prod.yml --profile tls up -d --build
```

Caddy obtains and renews a Let's Encrypt certificate automatically and
forwards `X-Forwarded-Proto`, which is what the entrypoint's proxy handling
expects.

### Differences from the development stack

| | `docker-compose.yml` | `docker-compose.prod.yml` |
|---|---|---|
| Plugin source | Bind mounted from the host | Baked into the image |
| Composer dependencies | Full, including dev | `--no-dev` only |
| Xdebug, PHPUnit, PHPCS | Installed | Absent |
| Tests directory | Present | Removed at build time |
| `WP_DEBUG` | On | Off, errors to stderr |
| Database port | Published | Internal only |
| File editing in admin | Allowed | `DISALLOW_FILE_MODS` |
| Provisioning | `bin/setup.sh`, run by hand | Automatic on boot |

---

## Environment variables

All optional unless marked. The entrypoint fails fast with a readable message
when a required value is missing.

### Database

| Variable | Notes |
|---|---|
| `WORDPRESS_DB_HOST` | **Required** unless a Railway MySQL service is attached. `host` or `host:port` |
| `WORDPRESS_DB_NAME` | **Required** unless Railway provides it |
| `WORDPRESS_DB_USER` | **Required** unless Railway provides it |
| `WORDPRESS_DB_PASSWORD` | |
| `WORDPRESS_TABLE_PREFIX` | Default `wp_` |
| `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` | Read automatically; set by Railway |
| `MYSQL_URL` | `mysql://user:pass@host:port/db`, parsed if the above are absent |

### Site

| Variable | Notes |
|---|---|
| `SITE_URL` | Falls back to `https://$RAILWAY_PUBLIC_DOMAIN`, then `$RENDER_EXTERNAL_URL`, then `http://localhost:8080` |
| `SITE_TITLE` | Default `Course Discovery` |
| `PORT` | Apache's listen port. Set by the platform |
| `ADMIN_USER` | Default `admin` |
| `ADMIN_PASSWORD` | Generated and printed to the log if unset — set it |
| `ADMIN_EMAIL` | Default `admin@example.com` |

### Content

| Variable | Notes |
|---|---|
| `SEED_COURSES` | Default `48`. Only used when the catalogue is empty |
| `SKIP_SEED` | `1` to deploy with no demo content |
| `INSTALL_ACF` | `1` (default) installs ACF from wordpress.org; `0` uses the built-in metaboxes |
| `DISCOURAGE_SEARCH_ENGINES` | `1` (default) sets `blog_public=0` |

### Salts

`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`,
`SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`.

All eight or none — the entrypoint only uses them if the full set is present.

---

## What the entrypoint does

`docker/production/entrypoint.sh`, in order. Every step is idempotent, which is
what makes a redeploy safe.

1. **Resolve database credentials** from `WORDPRESS_DB_*`, Railway's `MYSQL*`,
   or `MYSQL_URL`.
2. **Resolve the site URL** from `SITE_URL`, `RAILWAY_PUBLIC_DOMAIN` or
   `RENDER_EXTERNAL_URL`.
3. **Configure Apache** to listen on `$PORT`.
4. **Wait for MySQL** — up to two minutes, then fail with a clear message
   rather than starting a site that cannot reach its database.
5. **Copy WordPress core** into the web root if it is not already there.
6. **Generate `wp-config.php`** if absent, including the reverse-proxy HTTPS
   fix, `WP_HOME`/`WP_SITEURL` read from the environment at runtime, and the
   hardening constants.
7. **Install WordPress** if it is not installed; otherwise run `core update-db`.
8. **Sync the plugin** from `/usr/src/oxcd-plugin` into `wp-content/plugins`,
   mirroring rather than merging so files deleted in a release disappear.
9. **Install and activate ACF**, tolerating failure; activate the plugin;
   deactivate Akismet and Hello Dolly.
10. **Run migrations**, seed only if the catalogue is empty, set permalinks, and
    make the finder the front page.
11. **Exec Apache.**

The provisioning block is skipped when the container is given a command other
than `apache2-foreground`, so one-off commands are fast:

```bash
docker compose -f docker-compose.prod.yml exec app wp --allow-root plugin list
```

### The reverse-proxy detail

Every platform here terminates TLS at a proxy and forwards plain HTTP. PHP
therefore sees `http`, builds `http://` URLs, and — because `WP_SITEURL` is
`https://` — redirects forever. The generated `wp-config.php` sets
`$_SERVER['HTTPS']` from `X-Forwarded-Proto`, which is the standard fix and the
single most common reason a containerised WordPress deploy loops.

---

## Health checks

`GET /healthcheck.php` — used by Railway, by the compose healthcheck, and
useful by hand.

```console
$ curl -i https://your-app.up.railway.app/healthcheck.php
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
Cache-Control: no-store, no-cache, must-revalidate
X-Robots-Tag: noindex, nofollow

{"status":"ok","database":"reachable","php":"8.3.28"}
```

Returns **503** with `"database":"connection failed"` when MySQL is unreachable.

It deliberately does **not** load WordPress:

| | `/` | `/healthcheck.php` |
|---|---|---|
| Boots WordPress | Yes | No |
| Runs the finder query | Yes | No |
| Response time | ~150ms | ~4ms |
| Detects a dead database | Only as a 500 | Explicitly, as a 503 |
| Survives a fatal error in a plugin | No | Yes |

That last row is the point. If a plugin update throws a fatal, `/` returns 500
and the platform restarts the container in a loop; the health endpoint keeps
answering, so the container stays up and you can read the logs and fix it.

Because the entrypoint starts Apache only after provisioning succeeds, *any*
response means install, migrations and seeding finished. The endpoint's ongoing
job is catching what breaks later — which in practice is the database.

Add `/healthcheck.php` to an external uptime monitor if you want to be told
before the reviewer notices.

---

## Operations

```bash
# Railway
railway logs
railway run wp --allow-root --path=/var/www/html oxcd reindex
railway run wp --allow-root --path=/var/www/html oxcd seed --courses=100 --fresh

# Docker host
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml exec app wp --allow-root --path=/var/www/html oxcd reindex
```

### Rebuild the course index

Needed only if lookup rows look stale — after a bulk import, or a direct
database edit:

```bash
wp oxcd reindex
```

Also available in the admin at **Courses → Discovery tools**.

### Backup

```bash
docker compose -f docker-compose.prod.yml exec db \
    mariadb-dump -u root -p"$DB_ROOT_PASSWORD" wordpress > backup.sql
```

The four plugin tables are derived data and do not strictly need backing up —
`wp oxcd reindex` rebuilds them from `wp_posts`.

### Updating

Push to `main`. Railway rebuilds and redeploys automatically; on a Docker host:

```bash
git pull && docker compose -f docker-compose.prod.yml up -d --build
```

Migrations run on boot, so a schema change needs no separate step.

---

## Not Vercel

Vercel cannot run this, and the reasons are structural rather than
configuration:

- **No PHP runtime.** Only a community-maintained serverless runtime, which
  runs PHP as functions rather than behind a web server.
- **No MySQL.** Vercel offers Postgres, Redis and Blob storage. WordPress
  requires MySQL or MariaDB, and the search index needs a `FULLTEXT` key.
- **Ephemeral, read-only filesystem.** WordPress writes to
  `wp-content/uploads`, and `dbDelta()` schema migrations on a cold serverless
  start are not something to rely on.

Vercel is a good fit for the static documentation in this repository, and a poor
fit for the application. Railway is the closest equivalent developer experience
that can actually run it.
