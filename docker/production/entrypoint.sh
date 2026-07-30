#!/usr/bin/env bash
#
# Production entrypoint.
#
# Provisions the site on first boot and does nothing on subsequent boots, so a
# redeploy is safe and a restart is fast. Every step is idempotent.
#
# Reads platform conventions so the same image runs anywhere:
#   - $PORT                    Railway, Render, Fly, Cloud Run
#   - $MYSQL*                  Railway MySQL service
#   - $RAILWAY_PUBLIC_DOMAIN   Railway's generated hostname
#   - $WORDPRESS_DB_*          plain Docker / docker compose
#
# Exits non-zero with a readable message rather than starting a half-configured
# site — a broken deploy that fails loudly is easier to fix than one that serves
# a "database connection error" page.

set -euo pipefail

WP_PATH="${WP_PATH:-/var/www/html}"
PLUGIN_SLUG="${OXCD_PLUGIN_SLUG:-oxford-course-discovery}"
PLUGIN_SOURCE="/usr/src/oxcd-plugin"
PLUGIN_TARGET="${WP_PATH}/wp-content/plugins/${PLUGIN_SLUG}"

log() { printf '\033[0;36m[oxcd]\033[0m %s\n' "$*"; }
warn() { printf '\033[0;33m[oxcd]\033[0m %s\n' "$*" >&2; }
die() { printf '\033[0;31m[oxcd]\033[0m %s\n' "$*" >&2; exit 1; }

wp_cli() { wp --path="${WP_PATH}" --allow-root "$@"; }

# --- Database credentials ----------------------------------------------------
#
# Railway's MySQL service exposes MYSQL* variables; plain Docker uses
# WORDPRESS_DB_*. Accept either, and let an explicit WORDPRESS_DB_* win.

# Railway substitutes `${{Service.VAR}}` references only when the service name
# matches exactly. When it does not, the *literal* text is passed through as the
# value — so a typo produces a database host of `${{MySQL.MYSQL_URL}}`, two
# minutes of failed connection attempts, and an error about the database being
# unreachable. Catching it here turns that into an immediate, accurate message.
assert_resolved() {
	local name value

	for name in "$@"; do
		value="${!name:-}"

		case "${value}" in
			*'${{'* | *'}}'*)
				die "${name} contains an unresolved Railway reference: ${value}
    Railway only substitutes \${{Service.VAR}} when the service name matches exactly.
    Check the name in the Railway sidebar (it is case sensitive), or type \${{ in the
    variable editor and pick from the suggestions rather than typing it by hand."
				;;
		esac
	done
}

resolve_database() {
	assert_resolved MYSQL_URL MYSQLHOST MYSQLPORT MYSQLUSER MYSQLPASSWORD \
		MYSQLDATABASE WORDPRESS_DB_HOST WORDPRESS_DB_NAME WORDPRESS_DB_USER \
		WORDPRESS_DB_PASSWORD SITE_URL ADMIN_PASSWORD

	if [ -n "${MYSQL_URL:-}" ] && [ -z "${WORDPRESS_DB_HOST:-}" ]; then
		# mysql://user:password@host:port/database
		local url="${MYSQL_URL#mysql://}"
		local credentials="${url%%@*}"
		local location="${url#*@}"

		export WORDPRESS_DB_USER="${WORDPRESS_DB_USER:-${credentials%%:*}}"
		export WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-${credentials#*:}}"
		export WORDPRESS_DB_HOST="${location%%/*}"
		export WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME:-${location#*/}}"
	fi

	if [ -n "${MYSQLHOST:-}" ]; then
		export WORDPRESS_DB_HOST="${WORDPRESS_DB_HOST:-${MYSQLHOST}:${MYSQLPORT:-3306}}"
		export WORDPRESS_DB_USER="${WORDPRESS_DB_USER:-${MYSQLUSER:-root}}"
		export WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-${MYSQLPASSWORD:-}}"
		export WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME:-${MYSQLDATABASE:-railway}}"
	fi

	: "${WORDPRESS_DB_HOST:?No database configured. Set WORDPRESS_DB_HOST, or attach a MySQL service.}"
	: "${WORDPRESS_DB_NAME:?WORDPRESS_DB_NAME is required.}"
	: "${WORDPRESS_DB_USER:?WORDPRESS_DB_USER is required.}"

	export WORDPRESS_TABLE_PREFIX="${WORDPRESS_TABLE_PREFIX:-wp_}"
}

# Probe with the same driver the application uses.
#
# The obvious `mysqladmin ping` is the wrong tool: the MariaDB client shipped by
# Debian refuses MySQL 8+/9's self-signed TLS certificate outright
# ("TLS/SSL error: self-signed certificate in certificate chain"), while PHP's
# mysqli connects to the very same server without complaint. A readiness probe
# that can fail where the application succeeds is worse than no probe at all —
# it blocks a deployment that would have worked.
database_reachable() {
	php -r '
		mysqli_report( MYSQLI_REPORT_OFF );

		$connection = @mysqli_connect(
			getenv( "OXCD_PROBE_HOST" ),
			getenv( "OXCD_PROBE_USER" ),
			getenv( "OXCD_PROBE_PASSWORD" ),
			"",
			(int) getenv( "OXCD_PROBE_PORT" )
		);

		if ( ! $connection ) {
			fwrite( STDERR, mysqli_connect_error() );
			exit( 1 );
		}

		mysqli_close( $connection );
		exit( 0 );
	'
}

wait_for_database() {
	local host="${WORDPRESS_DB_HOST%%:*}"
	local port="${WORDPRESS_DB_HOST##*:}"
	[ "${port}" = "${host}" ] && port=3306

	export OXCD_PROBE_HOST="${host}"
	export OXCD_PROBE_PORT="${port}"
	export OXCD_PROBE_USER="${WORDPRESS_DB_USER}"
	export OXCD_PROBE_PASSWORD="${WORDPRESS_DB_PASSWORD:-}"

	log "Waiting for MySQL at ${host}:${port}"

	local attempt=1
	until database_reachable >/dev/null 2>&1; do
		if [ "${attempt}" -ge 60 ]; then
			# Surface the driver's own message; "not reachable" alone sends
			# people looking at networking when it is usually credentials.
			die "Database did not become reachable after 60 attempts: $( database_reachable 2>&1 >/dev/null || true )"
		fi

		sleep 2
		attempt=$((attempt + 1))
	done

	unset OXCD_PROBE_HOST OXCD_PROBE_PORT OXCD_PROBE_USER OXCD_PROBE_PASSWORD

	log "Database is up"
}

# --- Site URL ----------------------------------------------------------------

resolve_site_url() {
	if [ -z "${SITE_URL:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
		SITE_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
	fi

	if [ -z "${SITE_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
		SITE_URL="${RENDER_EXTERNAL_URL}"
	fi

	SITE_URL="${SITE_URL:-http://localhost:8080}"
	export SITE_URL WP_SITE_URL="${SITE_URL}"

	log "Site URL: ${SITE_URL}"
}

# --- Apache ------------------------------------------------------------------

configure_apache() {
	local port="${PORT:-80}"

	echo "Listen ${port}" > /etc/apache2/ports.conf
	sed -ri "s!<VirtualHost \*:[0-9]+>!<VirtualHost *:${port}>!" /etc/apache2/sites-available/000-default.conf

	log "Apache listening on ${port}"
}

# --- WordPress core ----------------------------------------------------------

ensure_core_files() {
	if [ ! -f "${WP_PATH}/wp-includes/version.php" ]; then
		log "Copying WordPress core into ${WP_PATH}"
		cp -a /usr/src/wordpress/. "${WP_PATH}/"
	fi

	mkdir -p "${WP_PATH}/wp-content/uploads"
	chown -R www-data:www-data "${WP_PATH}/wp-content"
}

SALT_KEYS=(
	AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY
	AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT
)

# Names of the salts that are not set.
missing_salts() {
	local key
	for key in "${SALT_KEYS[@]}"; do
		[ -z "${!key:-}" ] && printf '%s ' "${key}"
	done
}

# All eight salts, or none.
#
# A partial set is no better than none: the missing ones would be regenerated on
# every deploy, which invalidates sessions anyway. So the check is all-or-nothing
# and {@see ensure_config} reports exactly which names are absent — a half-set
# that is silently ignored is the kind of thing nobody discovers for weeks.
salts_provided() {
	local key
	for key in "${SALT_KEYS[@]}"; do
		[ -z "${!key:-}" ] && return 1
	done

	return 0
}

extra_php() {
	cat <<'PHP'
// TLS is terminated at the platform's proxy, so PHP sees a plain HTTP request.
// Without this, WordPress builds http:// URLs and redirects in a loop.
if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

// The hostname is assigned by the platform and can change, so it is read from
// the environment rather than stored in the database.
$oxcd_site_url = getenv( 'WP_SITE_URL' );

if ( is_string( $oxcd_site_url ) && '' !== $oxcd_site_url ) {
	define( 'WP_HOME', $oxcd_site_url );
	define( 'WP_SITEURL', $oxcd_site_url );
}

define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', false );

// The filesystem is ephemeral on most platforms: editing code through the admin
// would be lost on the next deploy, and updates belong in the image.
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );

define( 'FS_METHOD', 'direct' );
define( 'WP_POST_REVISIONS', 5 );
define( 'EMPTY_TRASH_DAYS', 7 );
PHP

	if salts_provided; then
		local key
		for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY \
			AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT
		do
			printf "define( '%s', '%s' );\n" "${key}" "${!key//\'/\\\'}"
		done
	fi
}

ensure_config() {
	if [ -f "${WP_PATH}/wp-config.php" ]; then
		return
	fi

	log "Generating wp-config.php"

	local salt_flag=()
	if salts_provided; then
		salt_flag+=( --skip-salts )
		log "Using salts from the environment"
	else
		local absent
		absent="$(missing_salts)"

		warn "Generating salts: this deploy will invalidate any existing logins."
		warn "All eight are needed before any are used. Still to set: ${absent% }"
	fi

	extra_php | wp_cli config create \
		--dbname="${WORDPRESS_DB_NAME}" \
		--dbuser="${WORDPRESS_DB_USER}" \
		--dbpass="${WORDPRESS_DB_PASSWORD:-}" \
		--dbhost="${WORDPRESS_DB_HOST}" \
		--dbprefix="${WORDPRESS_TABLE_PREFIX}" \
		--dbcharset=utf8mb4 \
		--dbcollate=utf8mb4_unicode_ci \
		--force \
		--extra-php \
		"${salt_flag[@]}"
}

ensure_installed() {
	if wp_cli core is-installed >/dev/null 2>&1; then
		log "WordPress already installed"
		wp_cli core update-db --quiet || true

		return
	fi

	local admin_user="${ADMIN_USER:-admin}"
	local admin_email="${ADMIN_EMAIL:-admin@example.com}"
	local admin_password="${ADMIN_PASSWORD:-}"

	if [ -z "${admin_password}" ]; then
		admin_password="$(head -c 18 /dev/urandom | base64 | tr -d '/+=' | head -c 20)"
		warn "ADMIN_PASSWORD was not set. Generated one for this install:"
		warn "    ${admin_user} / ${admin_password}"
		warn "Change it, or set ADMIN_PASSWORD and redeploy onto a fresh database."
	fi

	log "Installing WordPress"
	wp_cli core install \
		--url="${SITE_URL}" \
		--title="${SITE_TITLE:-Course Discovery}" \
		--admin_user="${admin_user}" \
		--admin_password="${admin_password}" \
		--admin_email="${admin_email}" \
		--skip-email
}

sync_plugin() {
	log "Installing the ${PLUGIN_SLUG} plugin"

	mkdir -p "${PLUGIN_TARGET}"
	# Mirror rather than copy, so a file removed in a later release is removed
	# from the running site too.
	rm -rf "${PLUGIN_TARGET:?}/"*
	cp -a "${PLUGIN_SOURCE}/." "${PLUGIN_TARGET}/"
	chown -R www-data:www-data "${PLUGIN_TARGET}"
}

ensure_plugins() {
	if [ "${INSTALL_ACF:-1}" = "1" ]; then
		if ! wp_cli plugin is-installed advanced-custom-fields >/dev/null 2>&1; then
			log "Installing Advanced Custom Fields"
			wp_cli plugin install advanced-custom-fields --activate \
				|| warn "Could not install ACF; the built-in metabox fallback will be used."
		else
			wp_cli plugin activate advanced-custom-fields >/dev/null 2>&1 || true
		fi
	fi

	wp_cli plugin activate "${PLUGIN_SLUG}"
	wp_cli plugin deactivate akismet hello >/dev/null 2>&1 || true
}

provision_content() {
	log "Running migrations"
	wp_cli oxcd migrate

	local courses
	courses="$(wp_cli post list --post_type=course --format=count 2>/dev/null || echo 0)"

	if [ "${courses}" -eq 0 ] && [ "${SKIP_SEED:-0}" != "1" ]; then
		log "Seeding ${SEED_COURSES:-48} demo courses"
		wp_cli oxcd seed --courses="${SEED_COURSES:-48}"
	else
		log "Content already present (${courses} courses); skipping seed"
	fi

	wp_cli rewrite structure '/%postname%/' >/dev/null
	wp_cli rewrite flush >/dev/null

	local finder_page
	finder_page="$(wp_cli option get oxcd_finder_page_id 2>/dev/null || true)"

	if [ -n "${finder_page}" ] && [ "${finder_page}" != "0" ]; then
		wp_cli option update show_on_front page >/dev/null
		wp_cli option update page_on_front "${finder_page}" >/dev/null
		log "Course finder set as the front page"
	fi

	# Search engines should not index a demo deployment.
	if [ "${DISCOURAGE_SEARCH_ENGINES:-1}" = "1" ]; then
		wp_cli option update blog_public 0 >/dev/null
	fi
}

# --- Main --------------------------------------------------------------------

main() {
	resolve_database
	resolve_site_url
	configure_apache

	# Only provision when we are actually about to serve traffic; `docker run
	# … wp cli info` style one-off commands should skip all of this.
	if [ "${1:-}" = "apache2-foreground" ]; then
		wait_for_database
		ensure_core_files
		ensure_config
		ensure_installed
		sync_plugin
		ensure_plugins
		provision_content

		log "Ready — serving ${SITE_URL}"
	fi

	exec "$@"
}

main "$@"
