ARG PHP_VERSION=8.5

FROM phpswoole/swoole:php${PHP_VERSION}-alpine

ARG USE_IRAN_MIRRORS=false
ARG APCU_VERSION=5.1.24

# Set Alpine mirror with intelligent fallback
RUN set -ex \
    && if [ "$USE_IRAN_MIRRORS" = "true" ]; then \
        echo "=== Setting up Alpine mirrors with fallback ===" ; \
        ALPINE_VERSION=$(grep VERSION_ID /etc/os-release | cut -d= -f2 | cut -d. -f1,2) ; \
        MIRROR_WORKS=false ; \
        \
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
                echo "https://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/main" >> /etc/apk/repositories ; \
                echo "https://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/community" >> /etc/apk/repositories ; \
                echo "📋 Official Alpine repo added as fallback" ; \
                MIRROR_WORKS=true ; \
                break ; \
            else \
                echo "❌ ${base} not reachable" ; \
            fi ; \
        done ; \
        \
        if [ "$MIRROR_WORKS" = "false" ]; then \
            echo "⚠️ All Iran Alpine mirrors failed, falling back to official" ; \
            echo "https://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/main" > /etc/apk/repositories ; \
            echo "https://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VERSION}/community" >> /etc/apk/repositories ; \
            apk update ; \
        fi ; \
    else \
        echo "USE_IRAN_MIRRORS is false → using default Alpine repositories" ; \
    fi

# Runtime dependencies (with retry for transient DNS errors)
RUN for i in 1 2 3; do \
        apk add --no-cache \
            tini curl ca-certificates \
            libpq libzip libpng libjpeg-turbo libwebp freetype \
            icu-libs libstdc++ ffmpeg \
        && break || { echo "⚠️ apk add failed (attempt $i/3), retrying in 5s..." ; sleep 5 ; } ; \
    done

# Build extensions + cleanup in single layer to avoid build-deps bloating the image
COPY .docker/packages/apcu-*.tgz /tmp/
RUN set -ex \
    && for i in 1 2 3; do apk add --no-cache --virtual .build-deps \
        autoconf g++ gcc make pkgconf linux-headers musl-dev \
        libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
        freetype-dev openssl-dev zlib-dev libpq-dev icu-dev \
    && break || { echo "⚠️ apk add build-deps failed (attempt $i/3), retrying..." ; sleep 5 ; } ; done \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql pcntl bcmath zip intl \
    && (pecl install apcu-${APCU_VERSION} 2>/dev/null \
        || pecl install /tmp/apcu-*.tgz) \
    && docker-php-ext-enable apcu \
    && apk del .build-deps \
    && rm -rf /tmp/apcu-* /tmp/pear /var/cache/apk/*

RUN php -m | grep -E "swoole|redis|gd|pdo_pgsql|pcntl|bcmath|zip|intl|apcu|opcache"

LABEL maintainer="Trend <dev@trend.ir>" \
      description="PHP 8.4 + Swoole base for Laravel Octane"