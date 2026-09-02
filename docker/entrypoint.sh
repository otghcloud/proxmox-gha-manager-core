#!/usr/bin/env bash
set -euo pipefail

DATA_DIR="${DATA_DIR:-/data}"
ENV_FILE="${DATA_DIR}/.env"
DB_FILE="${DATA_DIR}/database.sqlite"

# Set or replace a key in the env file, so repeated boots do not append duplicates.
set_env() {
    local key="$1" value="$2"
    if grep -q "^${key}=" "${ENV_FILE}"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "${ENV_FILE}"
    else
        echo "${key}=${value}" >> "${ENV_FILE}"
    fi
}

mkdir -p "${DATA_DIR}" "${DATA_DIR}/builds" "${DATA_DIR}/runner-images" "${DATA_DIR}/templates"

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "Creating ${ENV_FILE} on first boot."
    cp /app/.env.example "${ENV_FILE}"
fi

ln -sf "${ENV_FILE}" /app/.env

# key:generate rewrites an existing line rather than appending, so the key must be present.
if ! grep -q '^APP_KEY=' "${ENV_FILE}"; then
    echo 'APP_KEY=' >> "${ENV_FILE}"
fi

set_env APP_ENV "${APP_ENV:-production}"
set_env APP_DEBUG "${APP_DEBUG:-false}"
set_env APP_URL "${APP_URL:-http://localhost:8080}"
set_env APP_TIMEZONE "${APP_TIMEZONE:-UTC}"
set_env TRUSTED_PROXIES "${TRUSTED_PROXIES:-*}"
set_env DB_CONNECTION sqlite
set_env DB_DATABASE "${DB_FILE}"
set_env QUEUE_CONNECTION redis
set_env CACHE_STORE redis
set_env SESSION_DRIVER redis
set_env REDIS_HOST 127.0.0.1
set_env LOG_CHANNEL stderr

# The APP_KEY encrypts every stored Proxmox and GitHub credential. It is generated once and
# kept on the mounted volume: lose it and those secrets are permanently unrecoverable.
if ! grep -q '^APP_KEY=base64:' "${ENV_FILE}"; then
    echo "Generating a new APP_KEY. Back up ${ENV_FILE} - stored secrets cannot be read without it."
    php /app/artisan key:generate --force --no-interaction
fi

touch "${DB_FILE}"
chown -R manager:manager "${DATA_DIR}" /app/storage /app/bootstrap/cache

php /app/artisan package:discover --ansi
php /app/artisan migrate --force --no-interaction
php /app/artisan config:cache
php /app/artisan route:cache
php /app/artisan view:cache

if [[ "${1:-}" == "supervisord" ]]; then
    exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
fi

exec "$@"
