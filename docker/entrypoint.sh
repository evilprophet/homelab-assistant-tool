#!/bin/sh
set -e

# .env.example ships the literal placeholder, so an unedited copy would boot and
# work with a publicly known kernel secret and no warning at all.
if [ "${APP_SECRET:-}" = '{app_secret}' ] || [ -z "${APP_SECRET:-}" ]; then
    echo "APP_SECRET is unset or still the '{app_secret}' placeholder from .env.example." >&2
    echo "Generate one with: openssl rand -hex 64" >&2
    exit 1
fi

# Migrations run only when this container starts the HTTP server. Ad-hoc
# "docker compose run" invocations, including the setup commands used before a
# database exists, must not trigger them.
if [ "${HAT_AUTO_MIGRATE:-1}" != "0" ] && [ "$1" = "php" ] && [ "$2" = "-S" ]; then
    php bin/console hat:setup:db --migrate --backup --no-interaction
fi

exec "$@"
