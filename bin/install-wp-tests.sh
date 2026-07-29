#!/usr/bin/env bash
#
# Install the WordPress test library (the official `wordpress-develop` PHPUnit
# harness) so the integration and feature suites can run.
#
# Usage: install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]

set -euo pipefail

DB_NAME="${1:-wordpress_test}"
DB_USER="${2:-wordpress}"
DB_PASS="${3:-wordpress}"
DB_HOST="${4:-db}"
WP_VERSION="${5:-latest}"

WP_TESTS_DIR="${WP_TESTS_DIR:-/var/www/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/var/www/wordpress-develop}"

download() {
	curl -fsSL "$1" -o "$2"
}

if [ "${WP_VERSION}" = "latest" ]; then
	WP_TESTS_TAG="trunk"
else
	WP_TESTS_TAG="tags/${WP_VERSION}"
fi

install_test_suite() {
	if [ -d "${WP_TESTS_DIR}/includes" ] && [ -f "${WP_TESTS_DIR}/wp-tests-config.php" ]; then
		echo "==> Test library already present at ${WP_TESTS_DIR}"
		return
	fi

	echo "==> Fetching the WordPress test library (${WP_TESTS_TAG})"
	mkdir -p "${WP_TESTS_DIR}"

	svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "${WP_TESTS_DIR}/includes"
	svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "${WP_TESTS_DIR}/data"

	download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "${WP_TESTS_DIR}/wp-tests-config.php"

	# The suite needs a WordPress checkout to run against; the running site's
	# files are reused so the tested version is the deployed version.
	sed -i "s:dirname( __FILE__ ) . '/src/':'/var/www/html/':" "${WP_TESTS_DIR}/wp-tests-config.php"
	sed -i "s/youremptytestdbnamehere/${DB_NAME}/" "${WP_TESTS_DIR}/wp-tests-config.php"
	sed -i "s/yourusernamehere/${DB_USER}/" "${WP_TESTS_DIR}/wp-tests-config.php"
	sed -i "s/yourpasswordhere/${DB_PASS}/" "${WP_TESTS_DIR}/wp-tests-config.php"
	sed -i "s|localhost|${DB_HOST}|" "${WP_TESTS_DIR}/wp-tests-config.php"
}

create_db() {
	echo "==> Ensuring the test database exists"
	mysqladmin create "${DB_NAME}" --user="${DB_USER}" --password="${DB_PASS}" --host="${DB_HOST}" --protocol=tcp 2>/dev/null \
		|| echo "    (already exists)"
}

install_test_suite
create_db

echo "==> Ready. WP_TESTS_DIR=${WP_TESTS_DIR}"
