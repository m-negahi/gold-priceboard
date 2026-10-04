ARG DOCKER_REGISTRY=""
ARG IMAGE_NAME="hamrahgold"

# =============================================
# Stage 1: Composer dependencies (pre-built)
# Build vendor locally with VPN using Vendor.Dockerfile,
# then push as :vendor to the registry.
# =============================================
FROM ${DOCKER_REGISTRY:-}${IMAGE_NAME}:vendor AS vendor

# =============================================
# Stage 2: Build frontend assets (Vite / Tailwind)
# =============================================
FROM node:22-alpine AS assets

ARG USE_IRAN_MIRROR=false

# Reverb/Echo — این مقادیر در build-time داخل باندل JS حک می‌شوند (import.meta.env).
# بدون آن‌ها key=undefined → «You must pass your app key…» + Mixed Content در prod.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

WORKDIR /app
COPY package.json ./
RUN if [ "$USE_IRAN_MIRROR" = "true" ]; then \
        echo "→ using Iran npm registry"; npm config set registry https://mirror-npm.runflare.com/ ; \
    fi \
    && for i in 1 2 3; do \
        npm install --no-audit --no-fund && break \
        || { echo "⚠️ npm install failed (attempt $i/3), retrying in 5s..."; sleep 5; } ; \
    done
COPY vite.config.js ./
COPY resources ./resources
# app.js imports Alpine from Livewire's ESM bundle (jelogiri-e-double-Alpine).
# Az vendor stage-e-ghabli faghat masire-lazem ro miavarim ke Vite betoone
# `../../vendor/livewire/livewire/dist/livewire.esm` ra resolve konad.
COPY --from=vendor /app/vendor/livewire/livewire/dist ./vendor/livewire/livewire/dist
RUN npm run build

# =============================================
# Stage 3: Production application
# =============================================
FROM ${DOCKER_REGISTRY:-}${IMAGE_NAME}:base

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app
WORKDIR /var/www/html

# PHP Config
COPY .docker/production/php/config/*.ini /usr/local/etc/php/conf.d/

COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY --from=assets --chown=app:app /app/public/build ./public/build

RUN rm -rf .docker .dockerignore docker-compose*.yml \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV XDG_CONFIG_HOME=/tmp
ENV PSYSH_HOME=/tmp/.psysh

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts \
    && rm -f /usr/local/bin/composer

COPY --chown=app:app .docker/production/php/octane.entry.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && sed -i 's/\r$//' /entrypoint.sh
# مقصدِ sync استاتیک‌ها (سرویس static). ساختش اینجا با مالکیت app لازم است تا
# volumeی تازه (docker copy-on-first-use) با همین مالکیت init شود؛ وگرنه root
# می‌شود و entrypoint (که با app اجرا می‌شود) نمی‌تواند بنویسد.
RUN mkdir -p /public-assets && chown app:app /public-assets

USER app
EXPOSE 8000
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["/entrypoint.sh"]