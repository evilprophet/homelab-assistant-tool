# 🤖 Homelab Assistant Tool (HAT)

## Introduction

Homelab Assistant Tool is an application for homelab operations and automation. It combines a CLI interface with a web UI for managing devices, UPS units, schedules, and operational logs.

For technical details, see [Tech Stack](./docs/tech-stack.md).

## ✨ Key Features

- **🔌 Device operations**: Start, stop, check status, and open SSH sessions for managed devices
- **🔋 UPS-aware behavior**: UPS monitoring with battery-aware automation, including shutdown on low runtime
- **⏰ Scheduling**: Cron-based automation with schedule-to-device assignment
- **🧾 Action logs**: Unified history for `CLI`, `CRON`, and `WEB` operations, with filtering and cleanup
- **🌐 Web UI**: Management views for devices, UPS, schedules, and logs, plus manual operational actions
- **🔐 Authentication**: `simple` mode with local accounts, or `oidc` mode delegating to an identity provider

## 📁 Project Structure

```text
.
├── assets/              # Tailwind sources for the web UI
├── bin/                 # CLI entrypoints and helper scripts
├── config/              # Symfony and app configuration
├── docker/              # Entrypoint and scheduler crontab shipped in the image
├── docs/                # Technical documentation
├── migrations/          # Doctrine migrations
├── public/              # Web entrypoint and built assets
├── src/                 # Application source code
│   ├── Command/             # CLI commands (setup, CRUD, runtime, users)
│   ├── Contract/            # Runtime contracts and enums
│   ├── Controller/          # Web controllers
│   ├── Entity/              # Doctrine entities
│   ├── EventSubscriber/     # Request, auth, log, and security-header subscribers
│   ├── Exception/           # Domain exceptions
│   ├── Factory/             # Runtime factories and adapters
│   ├── Helper/              # Configuration and helpers
│   ├── Repository/          # Doctrine repositories
│   ├── Runtime/             # Runtime models
│   ├── Security/            # Authenticators
│   └── Service/             # Application, runtime, auth, and infrastructure services
├── templates/           # Twig templates
├── tests/               # Unit, integration, and functional test suites
└── var/                 # Runtime data, cache, logs, SQLite files
```

## 🛠️ Requirements

Always required:

- PHP `8.3+` with `ctype`, `iconv`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_sqlite`, `sockets`, and `xml`
- Composer
- An SSH private key reachable by the application, for shutting down Linux devices
- A scheduler for `hat:cron:execute` - the Docker setup ships one, otherwise host cron

Required per feature:

- NUT client (`upsc`) on the host running HAT, for UPS status and battery-mode automation
- Docker and Docker Compose, only to serve the web UI
- Node.js, only to rebuild the stylesheet during development

## 🚀 Quick Start

HAT runs in two setups, described in [Run Modes](#-run-modes). Pick one and follow the matching steps.

### Option 1: Docker only

Runs HAT without a working copy of the repository.

1. Download `docker-compose.yml`, `.env`, and `config/parameters.yaml`:
    ```bash
    mkdir -p config var/data
    curl -L -o docker-compose.yml https://raw.githubusercontent.com/evilprophet/homelab-assistant-tool/2.x/docker-compose.yml
    curl -L -o .env https://raw.githubusercontent.com/evilprophet/homelab-assistant-tool/2.x/.env.example
    curl -L -o config/parameters.yaml https://raw.githubusercontent.com/evilprophet/homelab-assistant-tool/2.x/config/parameters.yaml.template
    ```
    `config/parameters.yaml` is not tracked in the repository, so the template stands in for it. It ships working defaults, which matters because the application refuses to boot without that file.
2. Update `.env` and `config/parameters.yaml` for your environment: `.env` for app and auth settings, `config/parameters.yaml` for runtime settings. `APP_SECRET` must be replaced - see [Configuration](#-configuration).

    Edit both files on the host. Do not run `hat:setup:configure` in this setup: compose bind-mounts `config/parameters.yaml` as a single file, and the command rewrites it through an atomic rename, which a container cannot perform over a bind-mounted file.
3. Pull the GHCR image:
    ```bash
    docker compose pull
    ```
4. Run the setup commands:
    ```bash
    docker compose run --rm hat-app php bin/console hat:setup:db --init
    docker compose run --rm hat-app php bin/console hat:user:create   # only for simple auth mode
    ```
5. Start the container and open `http://localhost:8080`:
    ```bash
    docker compose up -d
    ```
6. Confirm the scheduler is running:
    ```bash
    docker compose logs -f hat-cron
    ```
    `docker compose up -d` also starts the `hat-cron` service, which runs `hat:cron:execute` every five minutes. Nothing else has to be scheduled on the host. Set schedule minutes on five-minute boundaries - see [Scheduling](#-scheduling).

### Option 2: Cloned project

Used for development, or to run the CLI without a container.

1. Clone the repository:
    ```bash
    git clone https://github.com/evilprophet/homelab-assistant-tool.git
    cd homelab-assistant-tool
    composer install
    cp .env.example .env
    ```
2. Update `.env` with application and auth values for your environment. `APP_SECRET` must be replaced - see [Configuration](#-configuration).
3. Run the setup commands:
    ```bash
    php bin/console hat:setup:configure
    php bin/console hat:setup:db --init
    php bin/console hat:user:create   # only for simple auth mode
    ```
4. Serve the web UI, from the published image or from a local build with Xdebug:
    ```bash
    # Published image
    docker compose pull && docker compose up -d

    # Local build with Xdebug
    docker compose -f docker-compose.dev.yml up -d --build
    ```
    Wake-on-LAN and UPS status do not work in the development compose, because it cannot use host networking. The file itself documents why.
5. Schedule `hat:cron:execute` on the host - a cloned project schedules nothing on its own. See [Scheduling](#-scheduling).

## 📋 Commands

| Command                                  | When                  | Description                                |
| ---------------------------------------- | --------------------- | ------------------------------------------ |
| `hat:setup:configure`                    | install               | Configure app technical settings.          |
| `hat:setup:db`                           | install, update       | Initialize or migrate the SQLite database. |
| `hat:setup:init`                         | install               | Run the full setup flow.                   |
| `hat:device:create/update/remove/list`   | day-to-day            | Manage devices.                            |
| `hat:ups:create/update/remove/list`      | day-to-day            | Manage UPS entries.                        |
| `hat:schedule:create/update/remove/list` | day-to-day            | Manage schedules.                          |
| `hat:device:check-status`                | day-to-day            | Check runtime status for a device.         |
| `hat:device:ssh`                         | day-to-day            | Open an SSH session to a device.           |
| `hat:device:start`                       | day-to-day            | Send a wake-on-LAN packet to a device.     |
| `hat:device:stop`                        | day-to-day            | Shut down a device over SSH.               |
| `hat:cron:execute`                       | scheduler, every tick | Execute scheduled tasks and maintenance.   |
| `hat:logs:list`                          | day-to-day            | List action logs stored in the database.   |
| `hat:logs:cleanup`                       | day-to-day            | Remove old or all action logs.             |
| `hat:user:create`                        | web, simple auth mode | Create a local user.                       |
| `hat:user:remove`                        | web, simple auth mode | Remove a local user.                       |
| `hat:user:reset-password`                | web, simple auth mode | Reset a local user password.               |

`hat:cron:execute` is the only command meant to run unattended; everything else is operator-driven. The `web` rows matter only when the web UI runs with `HAT_AUTH_MODE=simple`.

### Scripted use

- Removal commands require `--force` under `--no-interaction`, and fail instead of prompting when it is missing
- `hat:device:ssh` returns the exit code of the underlying `ssh` process
- `hat:device:start` reports that the wake-on-LAN packet was sent; it cannot confirm that the device booted
- Passwords must not contain whitespace, and are rejected at creation and reset rather than silently trimmed

Pass passwords through `--password-stdin` rather than `--password`, which stays visible in shell history and in the process list:

```bash
printf '%s' "$NEW_PASSWORD" | bin/console hat:user:create admin --password-stdin --no-interaction
```

## 🧩 Run Modes

HAT is a CLI application first. The web UI is an optional layer on top of it, and every operational action is available from the command line.

| Requirement                        | CLI only  | CLI + web          |
| ---------------------------------- | --------- | ------------------ |
| `config/parameters.yaml`           | yes       | yes                |
| SQLite database                    | yes       | yes                |
| SSH key for Linux shutdown         | yes       | yes                |
| Scheduler for `hat:cron:execute`   | host cron | `hat-cron` service |
| Migrations after an update         | manual    | automatic          |
| Container and `.env` auth settings | no        | yes                |

Both modes share one SQLite database. Initial setup is always a CLI step; the container takes over scheduling and migrations only once it is running.

Mixing the two - serving the web UI from the image while running commands from a cloned project - works, but both installations must then stay on the same version, because they run different code against one schema.

## ⏰ Scheduling

`hat:cron:execute` evaluates due schedules, applies UPS battery-mode rules, and prunes old action logs. It is the only command meant to run unattended.

In the Docker setup the `hat-cron` service runs it every five minutes. To change the interval:

1. Write your own crontab file:
    ```cron
    # ./hat-crontab
    */10 * * * * cd /app && php bin/console hat:cron:execute >> /proc/1/fd/1 2>&1
    ```
2. Mount it over the one baked into the image:
    ```yaml
      hat-cron:
        volumes:
          - ./hat-crontab:/etc/crontabs/hat:ro
    ```

Without the container, schedule it on the host:

```cron
*/5 * * * * cd /path/to/homelab-assistant-tool && php bin/console hat:cron:execute
```

Two rules apply to any interval:

- A schedule fires when its due minute falls within one minute of a tick, so schedule minutes must sit on the interval's boundary. With a five-minute interval use `0`, `5`, `10`, and so on
- Overlapping runs are prevented by a lock file in `var/data/`, so a host crontab and the `hat-cron` service cannot execute the same tick twice as long as both use the same data directory

## ⚙️ Configuration

Runtime behaviour lives in `config/parameters.yaml`, written by `hat:setup:configure`. Authentication and framework settings live in `.env`.

`APP_SECRET` must be replaced with a real value, because `.env.example` ships the literal placeholder `{app_secret}`. The container refuses to start while it is unset or still the placeholder:

```bash
openssl rand -hex 64
```

If the SSH key is passphrase-protected, put the passphrase in `ssh_key_passphrase` and leave it empty for an unencrypted key. `hat:setup:configure` does not ask for it, so add it by hand.

The container reads these environment variables:

| Variable                 | Default | Effect                                                                                                                                                                     |
|--------------------------|---------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `HAT_AUTO_MIGRATE`       | `1`     | Back up the database and apply pending migrations before the web server starts. `0` disables it.                                                                           |
| `PHP_CLI_SERVER_WORKERS` | `4`     | Requests the web server handles in parallel. HAT blocks on SSH, `upsc`, and ICMP inside requests, so a value of `1` lets one unreachable host stall the whole application. |
| `TRUSTED_PROXIES`        | empty   | Comma-separated reverse proxy addresses allowed to set `X-Forwarded-*`. Use `127.0.0.1` for a proxy on the same host.                                                      |
| `HAT_AUTH_MODE`          | -       | `simple` or `oidc`, lowercase. Any other value stops the application at startup with a single clear error rather than failing per request.                                 |

Set `HAT_AUTO_MIGRATE=0` when a cloned project shares the same database, so migrations stay under your control rather than being applied by whichever installation starts first. The automatic step never runs for `docker compose run` commands, only when the container starts the web server.

## 🔐 Reverse Proxy

The container serves plain HTTP on port `8080` and terminates no TLS of its own. Running it behind a reverse proxy is the supported way to reach HAT over HTTPS, and `.env.example` already assumes it through `DEFAULT_URI` and `OIDC_REDIRECT_URI`.

The proxy is responsible for:

- Terminating TLS and redirecting `http://` to `https://`
- Sending `Strict-Transport-Security: max-age=31536000` - HAT deliberately does not set this header itself, because a direct HTTP deployment on the LAN is equally valid and would be broken by it
- Forwarding `X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Host`, and `X-Forwarded-Port`

Set `TRUSTED_PROXIES` to the proxy address. Until it is set, Symfony ignores every `X-Forwarded-*` header, so generated URLs use `http` and the client IP in the action log is the proxy's. Never list whole private ranges there.

Serving HAT under a path prefix (for example `/hat/`) additionally requires the proxy to send `X-Forwarded-Prefix` and that header to be added to Symfony's trusted headers, which is not part of its default set.

## ⬆️ Updating

In the Docker setup the container migrates itself: on start it backs up the database, applies pending migrations, and refuses to serve traffic if a migration fails. A cloned project migrates nothing on its own.

```bash
# Docker
docker compose pull
docker compose up -d

# Cloned project
git pull
composer install --no-dev
php bin/console hat:setup:db --migrate --backup
```

The backup is written next to the database as `hat.sqlite.<timestamp>.bak`, and only when migrations are actually pending, so restarts do not accumulate copies. Old backups are never pruned - remove them yourself.

With `HAT_AUTO_MIGRATE=0`, or when both installations share one database, run the migration explicitly before starting the new version:

```bash
docker compose run --rm hat-app php bin/console hat:setup:db --migrate --backup
```

### Upgrading to a non-root container

The container used to run as root and now runs as UID/GID `1000`. Two things must be adjusted once, before the first start of the new image:

1. Hand the mounted directories to the new user:
    ```bash
    sudo chown -R 1000:1000 var/data var/log
    ```
2. Move the SSH key into the mounted data directory, because a key under `/root/.ssh/` is no longer readable:
    ```bash
    cp ~/.ssh/id_ed25519 var/data/id_ed25519
    chmod 600 var/data/id_ed25519
    sudo chown 1000:1000 var/data/id_ed25519
    ```
    Then point `ssh_key_path` in `config/parameters.yaml` at the new location.

Skipping either step leaves the application unable to write its database or to shut down Linux devices.

## 🧪 Development

### Quick Start:

```bash
# Run all tests (Xdebug off)
bin/phpunit

# Run with coverage report
bin/phpunit-coverage

# Check the coding standard, and fix what can be fixed automatically
composer cs
composer cs-fix

# Rebuild the stylesheet
npm run build:css
```

### 📚 Documentation:

- 📖 **[Test Implementation Summary](./docs/test-implementation-summary.md)** - Overview and current scope
- 🚀 **[CI/CD Guide](./docs/test-ci-cd-guide.md)** - GitLab quality pipeline and GHCR image publication
- 🧱 **[Tech Stack](./docs/tech-stack.md)** - Frameworks, libraries, and tooling assumptions

## 🧭 Notes

- Business data is stored in SQLite, and integration/functional tests use a separate database (`var/data/hat_test.sqlite`)
- Runtime settings are configured in `config/parameters.yaml`
- Web request logs are written to `var/log/`, rotated daily and kept for 28 days (`web-<date>.log` in production, `web.log` in development); no host-side logrotate rule is needed. Application actions are recorded in the database and readable through `hat:logs:list` or the web UI
- Every account has the same privileges. There is no admin/user split: anyone who can log in can edit devices (which hold SSH credentials), schedules, and UPS entries, and can purge action logs. In `oidc` mode any user the identity provider lets through is provisioned automatically, so the provider's application-access policy is the only authorization gate
- Sessions and the Symfony cache live in `var/cache`, which is not a mounted volume. Updating the image or recreating the container logs everyone out - by design, nothing there needs backing up
