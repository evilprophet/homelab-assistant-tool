FROM node:20-alpine AS assets_builder

WORKDIR /app

COPY package.json package-lock.json tailwind.config.js ./
RUN npm ci

COPY assets ./assets
COPY templates ./templates
COPY src ./src

RUN mkdir -p public/assets && npm run build:css

FROM php:8.4-cli-alpine

WORKDIR /app

ARG INSTALL_XDEBUG=0

ENV APP_RUNTIME_OPTIONS='{"disable_dotenv":true}'

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/conf.d/xdebug.ini /tmp/docker-xdebug.ini
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/10-app.ini
COPY composer.json composer.lock ./

RUN set -eux; \
    apk add --no-cache bash git openssh-client nut $PHPIZE_DEPS linux-headers; \
    docker-php-ext-install -j"$(nproc)" sockets opcache; \
    if [ "$INSTALL_XDEBUG" = "1" ]; then \
        pecl install xdebug; \
        docker-php-ext-enable xdebug; \
        cp /tmp/docker-xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini; \
    fi; \
    composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts; \
    apk del --no-network $PHPIZE_DEPS linux-headers; \
    rm -rf /tmp/pear /tmp/docker-xdebug.ini /root/.composer/cache

COPY . .
COPY --from=assets_builder /app/public/assets/app.css /app/public/assets/app.css
COPY docker/cron/hat /etc/crontabs/hat

# composer install ran before the application code was copied, so the optimized
# classmap contained vendor classes only. This rebuilds it with src/ included.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# Without this the built-in server handles one request at a time, so a single
# blocking SSH or upsc call stalls every other request.
ENV PHP_CLI_SERVER_WORKERS=4

RUN set -eux; \
    chmod +x /app/docker/entrypoint.sh; \
    addgroup -g 1000 hat; \
    adduser -u 1000 -G hat -s /bin/sh -D hat; \
    mkdir -p /app/var/data /app/var/log; \
    chown -R hat:hat /app/var; \
    chown root:root /etc/crontabs/hat; \
    chmod 0600 /etc/crontabs/hat

USER hat

EXPOSE 8080

# The built-in server can wedge while the process stays alive, and a live process
# never triggers `restart: unless-stopped`. Hitting a public route detects that.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget --quiet --spider --tries=1 http://127.0.0.1:8080/auth/login || exit 1

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
