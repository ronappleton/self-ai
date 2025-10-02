#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Ensure environment file exists
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Ensure sqlite database file exists when using sqlite connection
if grep -q '^DB_CONNECTION=sqlite' .env || [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p database
    if [ ! -f database/database.sqlite ]; then
        touch database/database.sqlite
    fi
fi

# Serialise dependency installation across services
if [ "${INSTALL_DEPENDENCIES:-true}" = "true" ]; then
    # Use a lock file on the shared application volume so that parallel
    # containers coordinate dependency installation properly.
    lock_file="/var/www/html/.docker-deps.lock"
    exec 200>"${lock_file}"
    flock 200

    if [ ! -d vendor ]; then
        composer install --no-interaction --prefer-dist
    else
        composer install --no-interaction --prefer-dist >/dev/null
    fi

    if [ -f package.json ]; then
        if [ ! -d node_modules ] || [ -z "$(find node_modules -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]; then
            pnpm install --frozen-lockfile
        fi
    fi

    flock -u 200
fi

# Generate the application key when missing
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Run migrations unless explicitly disabled
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# Ensure storage links are available
php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
