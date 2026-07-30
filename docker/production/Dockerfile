# syntax=docker/dockerfile:1
#
# Production image: WordPress with the Course Discovery plugin baked in.
#
# Differs from the development image in three ways that matter:
#   - the plugin is copied into the image with production dependencies only,
#     rather than bind mounted from the host;
#   - no Xdebug, no Composer, no test tooling;
#   - an entrypoint that provisions the site on first boot (install, migrate,
#     seed) and is a no-op on every boot after that.
#
# Works unchanged on Railway, Render, Fly.io or any Docker host. The only
# platform-specific behaviour is reading $PORT and Railway's MYSQL* variables,
# both handled in the entrypoint.

# --- Dependencies ------------------------------------------------------------

FROM composer:2 AS vendor

WORKDIR /plugin

COPY plugin/oxford-course-discovery/composer.json plugin/oxford-course-discovery/composer.lock ./

# Production dependencies only. The plugin has none at runtime, so this
# produces just the optimised autoloader — but it stays honest if that changes.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --no-autoloader

COPY plugin/oxford-course-discovery/ ./

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -rf tests phpunit-*.xml.dist phpcs.xml.dist phpstan.neon.dist

# --- Runtime -----------------------------------------------------------------

FROM wordpress:6.8-php8.3-apache

# `less` is for WP-CLI paging, the MySQL client for the readiness check.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        less \
        default-mysql-client \
        ca-certificates \
        curl; \
    rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    curl -fsSL -o /usr/local/bin/wp \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; \
    chmod +x /usr/local/bin/wp

RUN { \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=32M'; \
        echo 'max_execution_time=60'; \
        echo 'expose_php=Off'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/dev/stderr'; \
    } > /usr/local/etc/php/conf.d/zz-oxcd-production.ini

# Compression and long-lived caching for static assets. Cheap, and it makes the
# finder feel fast on a small instance.
RUN a2enmod deflate expires headers rewrite remoteip

# Pin the MPM explicitly.
#
# mod_php only works under prefork, and Apache refuses to start at all if a
# second MPM is loaded ("AH00534: More than one MPM loaded"), which is a crash
# loop rather than a degraded mode. The base image enables prefork alone today,
# but nothing stops an apt upgrade in the layer above from enabling event as
# well — so state the requirement instead of inheriting it.
RUN set -eux; \
    a2dismod mpm_event mpm_worker 2>/dev/null || true; \
    a2enmod mpm_prefork; \
    ls /etc/apache2/mods-enabled/ | grep -c 'mpm_.*\.load' | grep -qx 1

COPY docker/production/apache-oxcd.conf /etc/apache2/conf-available/oxcd.conf
RUN a2enconf oxcd

# Staged outside the web root: the entrypoint syncs it into wp-content/plugins
# after WordPress core is in place.
COPY --from=vendor /plugin /usr/src/oxcd-plugin

COPY docker/production/entrypoint.sh /usr/local/bin/oxcd-entrypoint.sh
RUN chmod +x /usr/local/bin/oxcd-entrypoint.sh

# Health check endpoint. Lives in the web root so platform probes can reach it
# without loading WordPress; the entrypoint's `cp -a` of core files never
# removes it, because it copies over the directory rather than replacing it.
COPY docker/production/healthcheck.php /var/www/html/healthcheck.php

ENV OXCD_PLUGIN_SLUG=oxford-course-discovery \
    WP_PATH=/var/www/html \
    PORT=80

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/oxcd-entrypoint.sh"]
CMD ["apache2-foreground"]
