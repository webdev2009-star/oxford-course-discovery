# WordPress + the tooling needed to develop and test the plugin.
#
# Based on the official image so PHP extensions, Apache config and the
# WordPress upgrade path are all stock; only Composer, WP-CLI and the MySQL
# client are added.
FROM wordpress:6.8-php8.3-apache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        less \
        default-mysql-client \
        subversion \
        unzip \
        ca-certificates \
        curl; \
    rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# WP-CLI
RUN set -eux; \
    curl -fsSL -o /usr/local/bin/wp \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; \
    chmod +x /usr/local/bin/wp

# Xdebug is opt-in: it costs roughly 2x on every request, so it is installed
# but disabled unless XDEBUG_MODE says otherwise.
RUN set -eux; \
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
    { \
        echo 'xdebug.mode=${XDEBUG_MODE}'; \
        echo 'xdebug.client_host=host.docker.internal'; \
        echo 'xdebug.start_with_request=trigger'; \
    } > /usr/local/etc/php/conf.d/zz-xdebug.ini

ENV XDEBUG_MODE=off

# Slightly more generous than the defaults; importing courses and running the
# test suite both benefit.
RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'max_execution_time=180'; \
    } > /usr/local/etc/php/conf.d/zz-oxcd.ini
