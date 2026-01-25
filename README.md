# 🤖 Homelab Assistant Tool (HAT)

## Introduction

Homelab Assistant Tool is a command-line application designed to simplify the management of a home lab environment. Its primary goal is to automate power management for physical and virtual machines, helping to save energy and ensure system
stability during power outages.

While initially created for a Proxmox-based homelab, the tool is designed to be generic and extensible, allowing other users to adapt it to their own setups.

## ✨ Key Features

- **🔌 Remote Device Management**:
    - Start devices remotely using Wake-on-LAN (WOL).
    - Gracefully shut down Linux-based servers via SSH.
    - Check the online status of devices using ping.
    - Direct SSH access to devices through a simple command.

- **🔋 Power Outage Protection**:
    - Monitor the status of a UPS (Uninterruptible Power Supply).
    - Automatically shut down designated devices when the UPS battery is low, ensuring data integrity.

- **⏰ Scheduled Automation (Cron)**:
    - Execute tasks based on a predefined schedule.
    - A typical use case is starting a backup server at night and shutting it down in the morning to conserve power.
    - Periodically check the status of critical servers and ensure they are running.

- **⚙️ Flexible Configuration**:
    - All devices, UPS settings, and schedules are configured in a central `parameters.yaml` file, making it easy for technical users to manage the setup.

For more details about the technologies used in this project, please see the [**Tech Stack documentation**](./docs/tech-stack.md).

## 🚀 Future Development Plan

- [x] **Implement Unit & Integration Tests**: Establish a solid testing foundation to ensure code quality and prevent regressions.
- [ ] **Proxmox VE Integration Enhancement**: Add support for managing **LXC containers** (start, stop, status).
- [ ] **User Interface and Configuration**:
    - [ ] Develop a simple **web-based User Interface (UI)**.
    - [ ] Migrate configuration from `parameters.yaml` to a **SQLite database**.
- [ ] **Improved Power Management**: Enhance the UPS integration to automatically restart systems when power is safely restored.
- [ ] **Extensibility**: Add support for other virtualization platforms and notification systems.

## 📁 Project Structure

The project follows a structured layout to separate concerns and make navigation easier.

```
bin/          # Executable console script
config/       # Application configuration files (services, parameters)
docs/         # Project documentation
src/          # All PHP source code
├── Api/      # Interfaces for core components (Device, UPS, etc.)
├── Command/  # All Symfony Console commands
├── Exception/  # Custom exceptions
├── Helper/     # Helper classes and utilities
├── Model/      # Core logic and data models (Device, UPS, Schedule)
├── Provider/   # Service providers that supply data to commands
└── Service/    # Core services like Cron and Logger
var/          # Temporary files, logs, and cache
vendor/       # Composer dependencies
```

## 🛠️ Installation

1. Create the project via Composer:
   ```bash
   composer create-project evilstudio/homelab-assistant-tool
   ```
2. Copy the configuration template:
   ```bash
   cp config/parameters.yaml.template config/parameters.yaml
   ```
3. Edit `config/parameters.yaml` to configure your devices, UPS, and schedules.
4. Ensure your SSH key allows passwordless access to the managed devices.

## 💻 Commands Overview

Here is a list of commands available in HAT.

| Command                   | Description                                                                         |
|---------------------------|-------------------------------------------------------------------------------------|
| `hat:device:show-all`     | Show a list of all configured devices. Use `--with-status` to include their status. |
| `hat:device:check-status` | Check the status of a specified device.                                             |
| `hat:device:ssh`          | SSH into a specified device.                                                        |
| `hat:device:start`        | Start a specified device via Wake-on-LAN.                                           |
| `hat:device:stop`         | Stop a specified device.                                                            |
| `hat:schedule:show`       | Show all configured schedules.                                                      |
| `hat:ups:show`            | Show the current UPS status and parameters.                                         |
| `hat:cron:run`            | Execute scheduled tasks. Intended to be run by a system cron job.                   |

**_NOTE:_** Device-related commands (`check-status`, `ssh`, `start`, `stop`) can accept an optional `name` argument. If omitted, you will be prompted to select a device from a list.

**_NOTE:_** Logs for cron jobs can be found in `var/log/cron.log`.

---

## 🧪 Testing & Quality

✅ **165 automated tests** | ✅ **83% code coverage** | ✅ **100% success rate**

This project has comprehensive test coverage with automated quality checks:
- **98 Unit Tests** - Testing individual components in isolation
- **67 Integration Tests** - Testing component interactions
- **All tests passing** - Production ready

### Quick Start:
```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage report
vendor/bin/phpunit --coverage-html tests/results/coverage
```

### 📚 Documentation:
- 📖 [Test Implementation Summary](./docs/test-implementation-summary.md) - Complete overview
- 📊 [Coverage Analysis](./docs/test-coverage-analysis.md) - Detailed coverage data
- 🚀 [CI/CD Guide](./docs/test-ci-cd-guide.md) - GitLab pipeline setup
- 📋 [Test Plan](./docs/test-plan.md) - Testing strategy
- 🔮 [Future Improvements](./docs/future-refactoring-plan.md) - Optional enhancements (87-88% coverage)
