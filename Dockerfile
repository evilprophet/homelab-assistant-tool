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
COPY composer.json composer.lock ./

RUN set -eux; \
    apk add --no-cache bash git openssh-client nut $PHPIZE_DEPS linux-headers; \
    docker-php-ext-install -j"$(nproc)" sockets; \
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

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
