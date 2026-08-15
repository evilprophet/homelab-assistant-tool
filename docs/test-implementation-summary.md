# 🧪 Test Implementation Summary

**Project:** Homelab Assistant Tool
**Coverage Source:** Xdebug (Real Data)
**Coverage:** 81.24% (3733/4595 lines)
**Total Tests:** 376
**Total Assertions:** 1890
**Status:** Active and maintained
**Last Updated:** 2026-08-13

---

## 📌 Scope At A Glance

- 376 automated tests (310 Unit + 20 Integration + 46 Functional)
- 1890 assertions
- All suites currently pass
- Test setup is isolated and deterministic (`.env.test`, dedicated SQLite test DB, schema reset between tests)

---

## ⚙️ Test Runtime Notes

- Tests use `.env.test` defaults with `APP_ENV=test`
- DB-backed suites use an isolated SQLite test database (`var/data/hat_test.sqlite`)
- Schema is reset deterministically between DB-backed test runs
- Coverage is generated with Xdebug using `bin/phpunit-coverage`
- PHPUnit 12 reports notices for mock objects created without expectations; the suite still exits `0`, and converting the remaining ones to test stubs is tracked as test debt

---

## 📊 Coverage Overview

```text
Classes:  30.43% (28/92)
Methods:  69.74% (378/542)
Lines:    81.24% (3733/4595)
```

### 🌍 Global Coverage Statistics

```text
Tests:       376
Assertions:  1890

Unit:        310 tests / 1506 assertions
Integration:  20 tests /  125 assertions
Functional:   46 tests /  259 assertions

Covered:   3733 lines
Uncovered:  862 lines (18.76%)
```

### 📁 Coverage by Category

| Category             | Methods              | Lines                  | Coverage   |
|----------------------|----------------------|------------------------|------------|
| **Command/**         | 49.15% (58/118)      | 72.41% (1223/1689)     | 72.41%     |
| **Contract/**        | 100.00% (10/10)      | 100.00% (41/41)        | 100.00%    |
| **Controller/**      | 42.47% (31/73)       | 80.42% (891/1108)      | 80.42%     |
| **Entity/**          | 96.00% (72/75)       | 96.64% (144/149)       | 96.64%     |
| **EventSubscriber/** | 81.25% (13/16)       | 96.19% (101/105)       | 96.19%     |
| **Exception/**       | 100.00% (4/4)        | 100.00% (6/6)          | 100.00%    |
| **Factory/**         | 40.00% (2/5)         | 94.23% (49/52)         | 94.23%     |
| **Helper/**          | 92.31% (12/13)       | 96.77% (30/31)         | 96.77%     |
| **Kernel.php**       | 50.00% (2/4)         | 60.71% (17/28)         | 60.71%     |
| **Repository/**      | 84.00% (21/25)       | 95.36% (144/151)       | 95.36%     |
| **Runtime/**         | 80.77% (42/52)       | 91.74% (200/218)       | 91.74%     |
| **Security/**        | 45.45% (5/11)        | 75.00% (45/60)         | 75.00%     |
| **Service/**         | 77.94% (106/136)     | 87.98% (842/957)       | 87.98%     |
| **GLOBAL**           | **69.74% (378/542)** | **81.24% (3733/4595)** | **81.24%** |

### 📈 Visual Representation

```text
Contract:        ████████████████████████████████████████ 100%
Exception:       ████████████████████████████████████████ 100%
Helper:          ██████████████████████████████████████░░ 97%
Entity:          ██████████████████████████████████████░░ 97%
EventSubscriber: ██████████████████████████████████████░░ 96%
Repository:      ██████████████████████████████████████░░ 95%
Factory:         █████████████████████████████████████░░░ 94%
Runtime:         ████████████████████████████████████░░░░ 92%
Service:         ███████████████████████████████████░░░░░ 88%
Controller:      ████████████████████████████████░░░░░░░░ 80%
Security:        ██████████████████████████████░░░░░░░░░░ 75%
Command:         █████████████████████████████░░░░░░░░░░░ 72%
Kernel.php:      ████████████████████████░░░░░░░░░░░░░░░░ 61%

GLOBAL:          ████████████████████████████████░░░░░░░░ 81%
```

---

## ✅ Tested Scope

### Unit Tests

Unit tests cover logic in isolation, with infrastructure boundaries mocked or stubbed.

- Console commands: every `hat:*` command driven through `CommandTester` with mocked services, covering argument and option handling, exit codes, and action-log calls
- Application services: `DeviceService`, `UpsService`, `ScheduleService`, `ActionLogService`
- Auth: `AuthModeResolver`, `AuthUserService`, and `SimpleLoginFormAuthenticator` (next-path normalisation and the login-timing decoy)
- Runtime services: `Cron`, `RuntimeStatusResolver`, `DeviceOperationsService`, `UpsRuntimeService`
- Runtime models: `Runtime\Ups`, `Runtime\Device\Linux` including SSH key loading and argument building
- Domain and support: entity invariants, `Configuration`, factories, command input traits, asset version strategy

### Integration Tests

Every test in this suite boots the kernel and runs against the SQLite test database. Command tests that only exercise argument parsing with mocked services live in the unit suite instead, where they belong.

- `CommandWiringTest` - resolves every `hat:*` command from the real container, asserts the expected command list, and runs create/list against the database. This is what catches a DI wiring break: a renamed constructor argument, a dropped `services.yaml` bind, or a command that stopped being registered
- `DeviceServiceDatabaseIntegrationTest`, `UpsServiceDatabaseIntegrationTest`, `ScheduleServiceDatabaseIntegrationTest` - persistence, cascades, and unique-constraint mapping
- `AuthUserServiceDatabaseIntegrationTest` - user creation, password reset, and removal verified through the password hasher
- `CronDatabaseIntegrationTest` - cron branches against real schedules and devices
- `ActionLogRepositoryTest` - filtering, pagination, and retention deletes
- `MigrationSchemaDriftTest` - migrates from an empty database and asserts that every entity table and hand-written index exists

### Functional Tests

Functional tests send real HTTP requests through the kernel and assert on rendered output.

- Simple auth: login success and failure, protected route redirects, logout behaviour
- OIDC auth: callback success and error branches, plus transport failures
- Management pages: device, UPS, and schedule CRUD with validation, pagination, sorting, and unknown-entity branches
- Logs pages: filters, pagination, cleanup by retention and level, invalid input handling
- Dashboard and runtime: dashboard rendering and the runtime-statuses payload contract
- CSRF: every mutating route rejects a request without a valid token

---

## 🧩 Remaining Risk Areas

- Command branch coverage is uneven; `ScheduleUpdateCommand` and `ScheduleRemoveCommand` are the weakest, because interactive prompt paths are not exercised
- `Kernel` and some controller sort and manual-action branches remain partially covered
- `OidcClient`'s transport is covered through an injectable `sendRequest()` seam, but no test performs a real HTTP call
- `Linux::ssh()` and `Runtime\Ups::executeCommand()` spawn real processes and are covered only up to the argument-building boundary; the process execution itself is verified manually
- `NetworkService` ping and wake-on-LAN factories are not covered, for the same reason
- Mock objects without expectations still produce PHPUnit 12 notices in several suites

## 🎯 Next Priorities

1. Expand branch coverage for `ScheduleUpdateCommand` and `ScheduleRemoveCommand`
2. Add targeted controller tests for the remaining device sorting and manual action branches
3. Convert the remaining expectation-free mocks to test stubs to clear the PHPUnit 12 notices
