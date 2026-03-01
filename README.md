# 🤖 Homelab Assistant Tool (HAT)

## Introduction

Homelab Assistant Tool is an application for homelab operations and automation.
It combines a CLI interface with a Web UI for managing devices, UPS units, schedules, and operational logs.

For technical details, see [Tech Stack](./docs/tech-stack.md).

## ✨ Key Features

- `🔌 Device operations`
- Start, stop, check status, and SSH operations for managed devices (via CLI)

- `🔋 UPS-aware behavior`
- UPS monitoring support and battery-aware automation logic

- `⏰ Scheduling`
- Cron-based automation with schedule-to-device assignment

- `🧾 Action logs`
- Unified action history for `CLI`, `CRON`, and `WEB` operations
- Log filtering and cleanup tools

- `🌐 Web UI`
- Management views for devices, UPS, schedules, and logs
- Manual operational actions from the browser

- `🔐 Authentication`
- `simple` mode (local username/password)
- `oidc` mode (OIDC provider login)

## 📁 Project Structure

```text
.
├── bin/             # CLI entrypoints and helper scripts
├── config/          # Symfony and app configuration
├── docs/            # Main technical documentation
├── migrations/      # Doctrine migrations
├── public/          # Web entrypoint and built assets
├── src/             # Application source code
│   ├── Command/         # CLI commands (setup, CRUD, runtime, users)
│   ├── Contract/        # Runtime contracts and enums
│   ├── Controller/      # Web controllers
│   ├── Entity/          # Doctrine entities
│   ├── EventSubscriber/ # Request/auth/log subscribers
│   ├── Factory/         # Runtime factories/adapters
│   ├── Helper/          # Configuration and helpers
│   ├── Repository/      # Doctrine repositories
│   ├── Runtime/         # Runtime models
│   └── Service/         # Application, runtime, auth, infrastructure services
├── templates/       # Twig templates (dashboard, devices, ups, schedules, logs, auth)
├── tests/           # Unit, integration, and functional test suites
└── var/             # Runtime data, cache, logs, SQLite files
```

## 🚀 Quick Start

### 1. Clone and install

```bash
git clone https://github.com/evilstudio/homelab-assistant-tool.git
cd homelab-assistant-tool
composer install
cp .env.example .env
```

### 2. Update `.env`

Set values for your environment (application/auth settings, and OIDC values when using `HAT_AUTH_MODE=oidc`).

### 3. Configure application settings

```bash
php bin/console hat:setup:configure
```

### 4. Initialize database

```bash
php bin/console hat:setup:db --init
```

### 5. Create first user (simple auth mode)

```bash
php bin/console hat:user:create
```

### 6. Run Web UI (Docker Compose)

```bash
docker compose up -d --build
```

Open: `http://localhost:8080`

### 7. Run CLI commands

```bash
php bin/console list
```

## 💻 Commands Overview

Here is a list of commands available in HAT.

| Command                                  | Description                              |
|------------------------------------------|------------------------------------------|
| `hat:setup:configure`                    | Configure app technical settings.        |
| `hat:setup:db`                           | Initialize/migrate SQLite database.      |
| `hat:setup:init`                         | Run full setup flow.                     |
| `hat:device:create/update/remove/list`   | Manage devices.                          |
| `hat:ups:create/update/remove/list`      | Manage UPS entries.                      |
| `hat:schedule:create/update/remove/list` | Manage schedules.                        |
| `hat:device:check-status`                | Check runtime status for a device.       |
| `hat:device:ssh`                         | Open SSH session to a device.            |
| `hat:device:start`                       | Start a device.                          |
| `hat:device:stop`                        | Stop a device.                           |
| `hat:cron:execute`                       | Execute scheduled tasks and maintenance. |
| `hat:logs:list`                          | List action logs stored in DB.           |
| `hat:logs:cleanup`                       | Remove old/all action logs from DB.      |
| `hat:user:create`                        | Create local user for simple auth mode.  |
| `hat:user:remove`                        | Remove local user in simple auth mode.   |
| `hat:user:reset-password`                | Reset local user password (simple mode). |

## 🧭 Notes

- Business data is stored in SQLite.
- Integration/functional tests use separate SQLite DB (`var/data/hat_test.sqlite`).
- Runtime settings are configured in `config/parameters.yaml`.
- Web logs are written to `var/log/web.log`.

## 🧪 Testing & Quality

### Quick Start:

```bash
# Run all tests (Xdebug off)
bin/phpunit

# Run with coverage report
bin/phpunit-coverage
```

### 📚 Documentation:

- 📖 **[Test Implementation Summary](./docs/test-implementation-summary.md)** - Overview and current scope
- 🚀 **[CI/CD Guide](./docs/test-ci-cd-guide.md)** - GitLab pipeline setup
