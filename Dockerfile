# syntax=docker/dockerfile:1

# PayKaro in one container: FrankenPHP (Caddy + PHP) serving public/.
#
# One process, one port, no Nginx sidecar — which is what Railway, Render,
# Fly.io, DigitalOcean App Platform, Google Cloud Run, Azure App Service and
# AWS App Runner all expect. The database is external (see DEPLOY.md §13);
# the container itself holds no state.
#
#   docker build -t paykaro .
#   docker run --rm -p 8080:8080 --env-file .env -e RUN_MIGRATIONS=true paykaro

FROM dunglas/frankenphp:1-php8.3-bookworm AS base

# Production php.ini, plus the extensions this app needs beyond the official
# image: intl/zip for Composer and the framework, bcmath for the interest
# maths, and all three PDO drivers so the database stays a configuration choice.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions \
        bcmath \
        intl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip

WORKDIR /app

# ---------------------------------------------------------------------------
# Dependencies, in their own layer so a code change does not re-install them.
# `--no-scripts` here: package discovery needs the application, which is not
# copied yet. `dump-autoload` below runs Laravel's post-autoload-dump hooks.
# ---------------------------------------------------------------------------
FROM base AS dependencies

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

COPY . .

RUN composer dump-autoload --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------
# Runtime
# ---------------------------------------------------------------------------
FROM base AS runtime

COPY --from=dependencies --chown=www-data:www-data /app /app

COPY --chown=www-data:www-data Caddyfile /etc/frankenphp/Caddyfile
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/paykaro-entrypoint

RUN chmod +x /usr/local/bin/paykaro-entrypoint \
    # FrankenPHP runs as www-data and still needs to bind the port.
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R www-data:www-data /config/caddy /data/caddy \
    # Every directory the framework writes to on a read-only-ish host, created
    # once at build time rather than raced into existence on first request.
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# These are defaults a platform can override from its dashboard. Nothing secret
# lives here — APP_KEY, the database URL and the PAYKARO_* numbers are injected
# at runtime, which is also why no config cache is built into the image.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    CACHE_STORE=database \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=sync \
    PORT=8080

USER www-data

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/paykaro-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
