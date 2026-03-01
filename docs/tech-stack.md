# 🧰 Tech Stack

This document is the source of truth for the Homelab Assistant Tool technology stack.

## 🧪 Language and Runtime

- PHP `8.3+` (project requirement in `composer.json`)
- Symfony Runtime for CLI and Web entrypoints
- Docker runtime image: `php:8.4-cli-alpine`

## 🧩 PHP Extensions

Required by the project:

- `ctype`
- `iconv`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_sqlite`
- `sockets`
- `xml`

## 🧱 Framework and Core Packages

Symfony stack:

- `symfony/framework-bundle`
- `symfony/runtime`
- `symfony/dotenv`
- `symfony/process`
- `symfony/yaml`
- `symfony/twig-bundle`
- `symfony/monolog-bundle`
- `symfony/flex`

Persistence stack:

- `doctrine/orm`
- `doctrine/doctrine-bundle`
- `doctrine/doctrine-migrations-bundle`

## 💾 Storage and Data Model

- SQLite file database (`var/data/hat.sqlite`)
- Doctrine entities and repositories for:
    - `Device`
    - `Ups`
    - `Schedule`
    - `ActionLog`
    - `User`

## 🔌 Domain and Infrastructure Dependencies

- `phpseclib/phpseclib` - SSH operations support
- `diegonz/php-wake-on-lan` - Wake-on-LAN packets
- `geerlingguy/ping` - ICMP reachability checks
- `dragonmantank/cron-expression` - cron expression parsing

## 🛠️ External System Commands and Tools

Important runtime binaries:

- `upsc` (Network UPS Tools client) - UPS status reads
- `ssh` / OpenSSH client - remote stop/session operations
- `ping` - device online status checks

## 🔐 Authentication

- Local mode (`simple`) with username/password users
- OIDC mode (`oidc`) with provider integration
- JWT cookie session handling via application services

## 🌐 Web UI and Frontend

- Symfony Controllers + Twig templates (SSR)
- Tailwind CSS (`3.4.x`)
- Node/npm scripts:
    - `npm run build:css`
    - `npm run watch:css`

## 📝 Logging

- DB action logs (`action_logs`) for `CLI`, `CRON`, `WEB`
- Monolog file logs:
    - `var/log/web.log`
    - environment logs (for example `var/log/prod.log`)

## 🐳 Containerization

- Docker Compose service: `hat-app`
- Multi-stage Docker build:
    - Node stage for Tailwind build
    - PHP stage for runtime
- Persistent mounts:
    - `./var/data -> /app/var/data`
    - `./var/log -> /app/var/log`
- Optional Xdebug toggles:
    - `HAT_WITH_XDEBUG`
    - `XDEBUG_MODE`
    - `XDEBUG_CONFIG`

## ✅ Development and Quality Tooling

- Composer
- PHPUnit `11.x`
- PHP_CodeSniffer `4.x` (PSR-12)
- GitLab CI pipeline (`.gitlab-ci.yml`) for lint and tests

## 🧪 Test Environment

- Dedicated Symfony `test` environment (`APP_ENV=test`)
- Test defaults loaded from `.env.test` (for auth and URL/runtime values)
- Dedicated SQLite test database: `var/data/hat_test.sqlite`
- Test-specific container config:
    - `config/packages/test/framework.yaml`
    - `config/services_test.yaml`
- Integration database tests recreate schema from Doctrine metadata for deterministic runs
