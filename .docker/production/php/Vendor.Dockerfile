ARG USE_IRAN_MIRRORS=false

# =============================================
# Stage 1: Install dependencies
# =============================================
FROM composer:latest AS builder

ARG USE_IRAN_MIRRORS

WORKDIR /app

COPY composer.json composer.lock ./

RUN if [ "$USE_IRAN_MIRRORS" = "true" ]; then \
        composer config -g repos.packagist composer https://mirror-composer.runflare.com ; \
    fi

ENV COMPOSER_PROCESS_TIMEOUT=600 \
    COMPOSER_HTTP_TIMEOUT=300 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_NO_INTERACTION=1

RUN --mount=type=cache,target=/root/.composer/cache \
    set -ex \
    && INSTALL_OPTS="--no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader --classmap-authoritative --ignore-platform-reqs" \
    && if [ "$USE_IRAN_MIRRORS" = "true" ]; then \
        (composer install $INSTALL_OPTS \
            || ( \
                echo "Runflare failed, switching to ITO..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://archive.ito.gov.ir/composer && \
                composer install $INSTALL_OPTS \
            ) \
            || ( \
                echo "ITO failed, switching to Megan..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://hub.megan.ir/composer && \
                composer install $INSTALL_OPTS \
            ) \
            || ( \
                echo "Megan failed, switching to IranServer..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://composer.iranserver.com/repository/composer/ && \
                composer install $INSTALL_OPTS \
            ) \
            || ( \
                echo "IranServer failed, switching to Liara..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://package-mirror.liara.ir/repository/composer/ && \
                composer install $INSTALL_OPTS \
            ) \
            || ( \
                echo "Liara failed, switching to Packagist..." && \
                composer clear-cache && \
                composer config -g repos.packagist composer https://repo.packagist.org && \
                composer install $INSTALL_OPTS \
            ) \
        ); \
    else \
        composer install $INSTALL_OPTS; \
    fi \
    && composer clear-cache \
    && find vendor -type f \( \
        -name "*.md" -o -name "*.MD" \
        -o -name "CHANGELOG*" \
        -o -name "*.txt" -o -name "phpunit.xml*" \
        -o -name ".travis.yml" -o -name "*.dist" \
    \) -delete \
    && find vendor -type d \( \
        -name "test" -o -name "tests" \
        -o -name "Test" -o -name "Tests" \
        -o -name "docs" -o -name "doc" \
    \) -exec rm -rf {} + 2>/dev/null || true

# =============================================
# Stage 2: Only vendor folder (no PHP, no composer)
# =============================================
FROM scratch

WORKDIR /app
COPY --from=builder /app/vendor ./vendor
