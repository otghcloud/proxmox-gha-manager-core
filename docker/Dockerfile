# syntax=docker/dockerfile:1
#
# Build context is the proxmox-gha-manager-core repository root.
#
#   docker build -t proxmox-gha-manager-core -f docker/Dockerfile .

FROM node:22-bookworm-slim AS assets

WORKDIR /build
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build


FROM composer:2 AS vendor

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . ./
RUN rm -rf bootstrap/cache/*.php .env public/build public/hot database/database.sqlite \
    && composer dump-autoload --optimize --no-dev --no-scripts


FROM ubuntu:26.04

ARG PHP=8.5
ARG PACKER_VERSION=1.16.0
ARG DEBIAN_FRONTEND=noninteractive
# Branch, tag or commit of proxmox-gha-manager-templates to bundle into the image.
ARG TEMPLATES_REF=main
# Released version to stamp into the image; local builds leave it unset and report dev-main.
ARG APP_VERSION=

ENV PHP_VERSION=${PHP}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl unzip tzdata git tini \
        nginx supervisor redis-server sqlite3 \
        "php${PHP}-fpm" "php${PHP}-cli" "php${PHP}-sqlite3" "php${PHP}-mbstring" \
        "php${PHP}-xml" "php${PHP}-curl" "php${PHP}-zip" "php${PHP}-intl" \
        "php${PHP}-bcmath" "php${PHP}-redis" "php${PHP}-gd" \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

RUN curl -fsSL -o /tmp/packer.zip \
        "https://releases.hashicorp.com/packer/${PACKER_VERSION}/packer_${PACKER_VERSION}_linux_amd64.zip" \
    && unzip -q /tmp/packer.zip -d /usr/local/bin \
    && rm /tmp/packer.zip \
    && chmod +x /usr/local/bin/packer \
    && packer --version

WORKDIR /app

COPY --from=vendor /build /app
COPY --from=assets /build/public/build /app/public/build

# app_version() reads this file, falling back to config when it is absent.
RUN if [ -n "${APP_VERSION}" ]; then printf '%s\n' "${APP_VERSION}" > /app/RELEASE; fi

# Fetch by ref rather than clone so branches, tags and commit SHAs all work.
RUN git init -q /opt/image-builder \
    && git -C /opt/image-builder fetch -q --depth 1 \
        https://github.com/otghcloud/proxmox-gha-manager-templates.git "${TEMPLATES_REF}" \
    && git -C /opt/image-builder checkout -q FETCH_HEAD \
    && rm -rf /opt/image-builder/.git

# Pre-installed so the first build does not fetch the plugin from GitHub unauthenticated.
ENV PACKER_PLUGIN_PATH=/opt/packer-plugins
RUN mkdir -p "${PACKER_PLUGIN_PATH}" \
    && packer plugins install github.com/hashicorp/proxmox

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php/php.ini /etc/php/${PHP}/fpm/conf.d/99-proxmox-manager.ini
COPY docker/php/www.conf /etc/php/${PHP}/fpm/pool.d/www.conf
COPY docker/redis/redis.conf /etc/redis/redis.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && useradd --system --uid 10001 --home /app --shell /usr/sbin/nologin manager \
    && mkdir -p /run/php /data \
    && chown -R manager:manager /app/storage /app/bootstrap/cache /data /opt/image-builder /opt/packer-plugins

ENV IMAGE_BUILDER_PATH=/opt/image-builder \
    BUILD_LOG_DIRECTORY=/data/builds \
    BUILD_WORKING_DIRECTORY=/data \
    RUNNER_IMAGES_PATH=/data/runner-images \
    TEMPLATES_INSTALL_PATH=/data/templates

# Holds the SQLite database, the generated APP_KEY, build logs and any fetched runner-images trees.
VOLUME ["/data"]

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/healthz || exit 1

# tini owns PID 1 so orphaned scheduler processes are reaped there, not by supervisord.
ENTRYPOINT ["/usr/bin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["supervisord"]
