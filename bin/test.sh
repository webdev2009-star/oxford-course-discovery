#!/usr/bin/env bash
#
# Run the PHP test suites inside the container.
#
# Usage: test.sh [unit|integration|all]

set -euo pipefail

SUITE="${1:-all}"
PLUGIN_DIR="/var/www/html/wp-content/plugins/oxford-course-discovery"

cd "${PLUGIN_DIR}"

if [ ! -d vendor ]; then
	echo "==> Installing Composer dependencies"
	composer install --no-interaction --prefer-dist
fi

run_unit() {
	echo "==> Unit suite"
	vendor/bin/phpunit --configuration phpunit-unit.xml.dist "${@:2}"
}

run_integration() {
	echo "==> Installing the WordPress test library"
	/usr/local/bin/oxcd/install-wp-tests.sh \
		"${WP_TESTS_DB_NAME:-wordpress_test}" \
		"${WORDPRESS_DB_USER:-wordpress}" \
		"${WORDPRESS_DB_PASSWORD:-wordpress}" \
		"${WORDPRESS_DB_HOST%%:*}"

	echo "==> Integration and feature suites"
	vendor/bin/phpunit --configuration phpunit-integration.xml.dist "${@:2}"
}

case "${SUITE}" in
	unit)
		run_unit "$@"
		;;
	integration|feature)
		run_integration "$@"
		;;
	all)
		run_unit "$@"
		run_integration "$@"
		;;
	*)
		echo "Unknown suite '${SUITE}'. Use: unit | integration | all" >&2
		exit 1
		;;
esac
