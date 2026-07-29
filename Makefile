# Developer entry points. Every target is a thin wrapper over docker compose,
# so nothing here is required — it is all reproducible by hand.

COMPOSE ?= docker compose
WP      ?= $(COMPOSE) exec -T wordpress
PLUGIN  ?= /var/www/html/wp-content/plugins/oxford-course-discovery

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

.PHONY: up
up: ## Build and start the stack
	$(COMPOSE) up -d --build

.PHONY: setup
setup: up ## Start the stack, install WordPress, seed demo content
	$(WP) bash /usr/local/bin/oxcd/setup.sh

.PHONY: down
down: ## Stop the stack (keeps data)
	$(COMPOSE) down

.PHONY: destroy
destroy: ## Stop the stack and delete all data
	$(COMPOSE) down -v

.PHONY: logs
logs: ## Tail the WordPress logs
	$(COMPOSE) logs -f wordpress

.PHONY: shell
shell: ## Open a shell in the WordPress container
	$(COMPOSE) exec wordpress bash

.PHONY: wp
wp: ## Run a WP-CLI command, e.g. make wp CMD="plugin list"
	$(WP) wp --allow-root --path=/var/www/html $(CMD)

.PHONY: install
install: ## Install PHP dependencies
	$(WP) composer install --working-dir=$(PLUGIN) --no-interaction

.PHONY: test
test: ## Run every PHP suite
	$(WP) bash /usr/local/bin/oxcd/test.sh all

.PHONY: test-unit
test-unit: ## Run the fast unit suite
	$(WP) bash /usr/local/bin/oxcd/test.sh unit

.PHONY: test-integration
test-integration: ## Run the integration and feature suites
	$(WP) bash /usr/local/bin/oxcd/test.sh integration

.PHONY: test-e2e
test-e2e: ## Run the Playwright end-to-end suite (host, needs Node)
	cd e2e && npm install && npx playwright install --with-deps chromium && npm test

.PHONY: lint
lint: ## PHP_CodeSniffer against the WordPress standard
	$(WP) $(PLUGIN)/vendor/bin/phpcs --standard=$(PLUGIN)/phpcs.xml.dist

.PHONY: analyse
analyse: ## Static analysis (PHPStan level 6, with WordPress stubs)
	$(WP) bash -c "cd $(PLUGIN) && vendor/bin/phpstan analyse --memory-limit=1G"

.PHONY: reindex
reindex: ## Rebuild the course lookup tables
	$(WP) wp --allow-root --path=/var/www/html oxcd reindex

.PHONY: seed
seed: ## Regenerate demo content
	$(WP) wp --allow-root --path=/var/www/html oxcd seed --courses=48 --fresh
