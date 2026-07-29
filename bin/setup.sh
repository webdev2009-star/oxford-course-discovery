#!/usr/bin/env bash
#
# One-shot provisioning for the development site: install WordPress, activate
# the plugin and ACF, and generate demo content.
#
# Idempotent — safe to run again after a `docker compose down` that kept the
# volumes, and safe to run again after code changes.

set -euo pipefail

PLUGIN_SLUG="oxford-course-discovery"
WP_PATH="/var/www/html"
SITE_URL="${SITE_URL:-http://localhost:8080}"
SITE_TITLE="${SITE_TITLE:-Oxford Course Discovery}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-password}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
SEED_COURSES="${SEED_COURSES:-48}"

wp() {
	command wp --path="${WP_PATH}" --allow-root "$@"
}

echo "==> Waiting for the database"
until wp db check >/dev/null 2>&1 || wp core is-installed >/dev/null 2>&1 || mysqladmin ping -h"${WORDPRESS_DB_HOST%%:*}" --silent >/dev/null 2>&1; do
	sleep 2
done

if ! wp core is-installed >/dev/null 2>&1; then
	echo "==> Installing WordPress"
	wp core install \
		--url="${SITE_URL}" \
		--title="${SITE_TITLE}" \
		--admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--skip-email
else
	echo "==> WordPress already installed"
fi

echo "==> Installing Composer dependencies"
composer install \
	--working-dir="${WP_PATH}/wp-content/plugins/${PLUGIN_SLUG}" \
	--no-interaction \
	--prefer-dist

echo "==> Activating Advanced Custom Fields"
if ! wp plugin is-installed advanced-custom-fields >/dev/null 2>&1; then
	# ACF is the one third party plugin the brief permits. The plugin works
	# without it (native metaboxes take over), so a download failure is not
	# fatal.
	wp plugin install advanced-custom-fields --activate || \
		echo "    ! Could not install ACF; the built-in metabox fallback will be used."
else
	wp plugin activate advanced-custom-fields || true
fi

echo "==> Activating ${PLUGIN_SLUG}"
wp plugin activate "${PLUGIN_SLUG}"

echo "==> Running migrations"
wp oxcd migrate

echo "==> Setting permalinks"
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

if [ "$(wp post list --post_type=course --format=count)" -eq 0 ]; then
	echo "==> Seeding ${SEED_COURSES} demo courses"
	wp oxcd seed --courses="${SEED_COURSES}"
else
	echo "==> Courses already present; skipping seed (use bin/seed.sh --fresh to replace)"
fi

FINDER_ID="$(wp option get oxcd_finder_page_id 2>/dev/null || echo '')"

if [ -n "${FINDER_ID}" ]; then
	wp option update page_on_front "${FINDER_ID}"
	wp option update show_on_front page
	echo "==> Course finder set as the front page"
fi

echo
echo "Done."
echo "  Site:  ${SITE_URL}"
echo "  Admin: ${SITE_URL}/wp-admin  (${ADMIN_USER} / ${ADMIN_PASSWORD})"
