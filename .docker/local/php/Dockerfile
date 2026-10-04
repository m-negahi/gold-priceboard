ARG PHP_VERSION=8.5

FROM composer:latest AS vendor
ARG USE_IRAN_MIRROR=false

WORKDIR /app
COPY composer.* ./

# Install composer dependencies
RUN --mount=type=cache,target=/root/.cache/composer \
    set -ex \
    && rm -rf /root/.cache/composer/* \
    && if [ "$USE_IRAN_MIRROR" = "true" ]; then \
        composer config -g repos.packagist composer https://mirror-composer.runflare.com \
        && echo "Using Runflare mirror..." \
        && (composer install --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs \
            || ( \
                echo "Runflare failed, switching to ITO..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://archive.ito.gov.ir/composer && \
                composer install --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs \
            ) \
            || ( \
                echo "ITO failed, switching to Megan..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://hub.megan.ir/composer && \
                composer install --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs \
            ) \
            || ( \
                echo "Megan failed, switching to IranServer..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://composer.iranserver.com/repository/composer/ && \
                composer install --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs \
            ) \
            || ( \
                echo "IranServer failed, switching to Liara..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://package-mirror.liara.ir/repository/composer/ && \
                composer install --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs \
            ) \
            || ( \
                echo "Liara failed, switching to Packagist..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://repo.packagist.org && \
                composer install --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs \
            ) \
        ); \
    else \
        composer install --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs; \
    fi \
    && composer clear-cache

# Base image (PHP + Swoole + Extensions + Xdebug)
FROM phpswoole/swoole:php${PHP_VERSION}-alpine AS base

ARG USE_IRAN_MIRROR=false
ARG APCU_VERSION=5.1.24
ARG XDEBUG_VERSION=3.5.3

COPY .docker/packages /opt/packages

# Set Alpine mirror
RUN set -ex \
    && ALPINE_VERSION=$(grep VERSION_ID /etc/os-release | cut -d= -f2 | cut -d. -f1,2) \
    && echo "Alpine version: ${ALPINE_VERSION}" \
    && if [ "$USE_IRAN_MIRROR" = "true" ]; then \
        MIRROR_WORKS=false ; \
        for base in \
            "http://repo.iut.ac.ir/repo/alpine/v${ALPINE_VERSION}" \
            "http://mirror-linux.runflare.com/alpine/v${ALPINE_VERSION}" \
            "https://mirror.arvancloud.ir/alpine/v${ALPINE_VERSION}" \
            "https://linux-mirror.liara.ir/repository/alpine/v${ALPINE_VERSION}" \
            "https://mirror.mobinhost.com/alpine/v${ALPINE_VERSION}" \
            "https://hub.megan.ir/alpine/v${ALPINE_VERSION}" \
            "https://mirror.0-1.cloud/alpine/v${ALPINE_VERSION}" ; do \
            \
            echo "→ Testing Alpine mirror: ${base}" ; \
            echo "${base}/main" > /etc/apk/repositories ; \
            echo "${base}/community" >> /etc/apk/repositories ; \
            \
            if apk update -q 2>/dev/null; then \
                echo "✅ Using Alpine mirror: ${base}" ; \
                MIRROR_WORKS=true ; \
                break ; \
            else \
                echo "❌ ${base} not reachable" ; \
            fi ; \
        done ; \
        if [ "$MIRROR_WORKS" = "false" ]; then \
            echo "⚠️ All Iran Alpine mirrors failed, falling back to official" ; \
            echo "https://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/main" > /etc/apk/repositories ; \
            echo "https://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/community" >> /etc/apk/repositories ; \
            apk update ; \
        fi ; \
    else \
        echo "http://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/main" > /etc/apk/repositories; \
        echo "http://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/community" >> /etc/apk/repositories; \
        apk update; \
    fi

# Build dependencies (with retry for network issues)
RUN (apk update || true) && \
    for i in 1 2; do \
        echo "Attempt $i/3: Installing build dependencies..." && \
        apk add --no-cache --virtual .build-deps \
            autoconf g++ gcc make pkgconf linux-headers musl-dev \
            libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
            freetype-dev openssl-dev zlib-dev libpq-dev icu-dev curl-dev git \
        && break || { echo "Failed, retrying..."; sleep 5; apk update || true; }; \
    done

# Runtime dependencies (with retry for network issues)
RUN for i in 1 2; do \
        echo "Attempt $i/3: Installing runtime dependencies..." && \
        apk add --no-cache \
            tini curl ca-certificates \
            libpq libzip libpng libjpeg-turbo libwebp freetype \
            icu-libs libstdc++ git unzip nodejs npm zip jq ffmpeg \
        && break || { echo "Failed, retrying..."; sleep 5; apk update || true; }; \
    done

# PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql pcntl bcmath zip intl exif

# APCu
RUN set -ex \
    && mkdir -p /tmp/apcu \
    && APCU_URL="https://github.com/krakjoe/apcu/archive/v${APCU_VERSION}.tar.gz" \
    && LOCAL_APCU="/opt/packages/apcu-${APCU_VERSION}.tgz" \
    && DOWNLOAD_OK=false \
    && echo "Trying to download APCu from GitHub..." \
    && if curl -fsSL --connect-timeout 15 --retry 3 --retry-delay 5 "${APCU_URL}" -o /tmp/apcu.tar.gz; then \
        tar -xzf /tmp/apcu.tar.gz -C /tmp/apcu --strip-components=1 && DOWNLOAD_OK=true; \
    fi \
    && if [ "$DOWNLOAD_OK" = "false" ] && [ -f "$LOCAL_APCU" ]; then \
        echo "Download failed. Using local APCu package..." \
        && tar -xzf "$LOCAL_APCU" -C /tmp/apcu --strip-components=1 && DOWNLOAD_OK=true; \
    fi \
    && [ "$DOWNLOAD_OK" = "true" ] || { echo "All APCu sources failed"; exit 1; } \
    && cd /tmp/apcu && phpize && ./configure \
    && make -j"$(nproc)" && make install \
    && docker-php-ext-enable apcu && rm -rf /tmp/apcu*

# Xdebug (dev-only; best-effort — a network/compile hiccup must NOT fail the build)
RUN set -x \
    && mkdir -p /tmp/xdebug \
    && XDEBUG_URL="https://github.com/xdebug/xdebug/archive/refs/tags/${XDEBUG_VERSION}.tar.gz" \
    && LOCAL_XDEBUG="/opt/packages/xdebug-${XDEBUG_VERSION}.tgz" \
    && DOWNLOAD_OK=false \
    && echo "Trying to download Xdebug ${XDEBUG_VERSION} from GitHub..." \
    && if curl -fsSL --connect-timeout 15 --retry 3 --retry-delay 5 "${XDEBUG_URL}" -o /tmp/xdebug.tar.gz; then \
        tar -xzf /tmp/xdebug.tar.gz -C /tmp/xdebug --strip-components=1 && DOWNLOAD_OK=true; \
    fi \
    && if [ "$DOWNLOAD_OK" = "false" ] && [ -f "$LOCAL_XDEBUG" ]; then \
        echo "Download failed. Using local Xdebug package..." \
        && tar -xzf "$LOCAL_XDEBUG" -C /tmp/xdebug --strip-components=1 && DOWNLOAD_OK=true; \
    fi \
    && if [ "$DOWNLOAD_OK" = "true" ] \
        && cd /tmp/xdebug && phpize && ./configure \
        && make -j"$(nproc)" && make install \
        && docker-php-ext-enable xdebug; then \
        echo "✅ Xdebug installed"; \
    else \
        echo "⚠️ Xdebug unavailable — skipping (dev debugger only, non-fatal)"; \
    fi \
    && rm -rf /tmp/xdebug*

# Cleanup build deps and package files
RUN apk del .build-deps && rm -rf /tmp/* /var/cache/apk/* /opt/packages

# npm mirror for Iran + Chokidar for hot reload
RUN set -ex \
    && NPM_OK=false \
    && if [ "$USE_IRAN_MIRROR" = "true" ]; then \
        for mirror in \
            "https://registry.npmjs.ir" \
            "https://npm-mirror.liara.ir" \
            "https://mirror-npm.runflare.com" ; do \
            echo "Trying npm mirror: ${mirror}" ; \
            npm config set registry "$mirror" ; \
            if npm install --global chokidar-cli 2>/dev/null; then \
                echo "Success with: ${mirror}" ; \
                NPM_OK=true ; \
                break ; \
            fi ; \
        done ; \
    fi \
    && if [ "$NPM_OK" = "false" ]; then \
        npm config delete registry 2>/dev/null || true ; \
        npm install --global chokidar-cli ; \
    fi

# Application
FROM base AS app

ARG USER_ID=1000
ARG GROUP_ID=1000

# Create user with matching host UID/GID
RUN addgroup -g ${GROUP_ID} appgroup \
    && adduser -u ${USER_ID} -G appgroup -s /bin/sh -D appuser

WORKDIR /var/www/html

# PHP Config
COPY .docker/local/php/config /usr/local/etc/php/conf.d

# Application code
COPY --chown=appuser:appgroup . .

# Vendor dependencies
COPY --from=vendor --chown=appuser:appgroup /app/vendor ./vendor
COPY --from=vendor /app/vendor /opt/vendor-backup
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY --from=vendor /app/composer.lock ./composer.lock

RUN if [ "$USE_IRAN_MIRROR" = "true" ]; then \
        composer config -g repos.packagist composer https://package-mirror.liara.ir/repository/composer/ ; \
    fi

# Storage directories
RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R appuser:appgroup storage bootstrap/cache composer.lock \
    && chmod -R 775 storage bootstrap/cache \
    && mkdir -p /home/appuser/.composer /home/appuser/.config/psysh \
    && chown -R appuser:appgroup /home/appuser

# Entrypoint
COPY --chown=appuser:appgroup .docker/local/php/octane.entry.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER appuser

EXPOSE 8000
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["/usr/local/bin/entrypoint.sh"]
