# Test Implementation Plan

## 1. Goals

The primary goal of implementing a test suite is to ensure the long-term stability and reliability of the Homelab Assistant Tool. Key objectives include:

-   **Preventing Regressions**: Create a safety net that catches unintended side effects of new features or refactoring.
-   **Improving Code Quality**: Writing testable code often leads to better and more decoupled application architecture.
-   **Facilitating Future Development**: A solid test suite allows developers to make changes with confidence.
-   **Documenting Behavior**: Tests serve as living documentation of how individual components are expected to behave.

While aiming for high code coverage is beneficial, the initial focus will be on testing critical paths and business logic rather than strictly enforcing a 100% coverage metric.

## 2. Environment Setup

This phase establishes the foundation for all testing activities.

-   **[x] Add PHPUnit Dependency**:
    -   Install PHPUnit via Composer as a development dependency:
        ```bash
        composer require --dev phpunit/phpunit
        ```

-   **[x] Create Directory Structure**:
    -   Create a `tests/` directory in the project root.
    -   Inside `tests/`, create two subdirectories:
        -   `tests/Unit/`: For unit tests that check components in isolation.
        -   `tests/Integration/`: For tests that verify the interaction between multiple components.

-   **[x] Configure PHPUnit**:
    -   Create a `phpunit.xml.dist` file in the project root.
    -   This file will configure the test runner, define the test suites, and enable code coverage reporting.

    ```xml
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
             xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
             bootstrap="vendor/autoload.php"
             colors="true">
        <testsuites>
            <testsuite name="Unit">
                <directory>tests/Unit</directory>
            </testsuite>
            <testsuite name="Integration">
                <directory>tests/Integration</directory>
            </testsuite>
        </testsuites>
        <source>
            <include>
                <directory>src</directory>
            </include>
        </source>
    </phpunit>
    ```

-   **[x] Update `.gitignore`**:
    -   Add `/.phpunit.cache/` and coverage reports directory (e.g., `coverage/`) to `.gitignore`.

## 3. Unit Testing Strategy

We will start with components that have the fewest external dependencies.

-   **[x] `Model/Schedule.php` (`ScheduleTest.php`)**:
    -   Test `isDue()` method with various cron expressions.
    -   Test with a fixed point in time to ensure predictable results.
    -   Case: A job that should run.
    -   Case: A job that should not run.

-   **[x] `Model/Ups.php` (`UpsTest.php`)**:
    -   Test parsing of `upsc` command output.
    -   Test `isBatteryRuntimeSafe()` logic with different battery levels and thresholds.
    -   **Edge Case**: Test behavior when the `upsc` command fails or is not available (should throw `UpsFailedUpdateStatus`). This will involve mocking the `exec` function or wrapping it in a testable service.

-   **[x] `Helper/Configuration.php` (`ConfigurationTest.php`)**:
    -   Test methods for retrieving devices, schedules, and UPS settings from a sample configuration array.
    -   Test behavior when a requested device or UPS is not found (should throw an exception).

-   **[x] `Model/DeviceFactory.php` (`DeviceFactoryTest.php`)**:
    -   Test that the factory returns the correct `DeviceInterface` implementation (`Linux`, `ProxmoxVE`, `Generic`) based on the `platform` key in the configuration.
    -   Test that it throws an exception for an unknown platform type.

-   **[x] `Model/Device/*` (`LinuxTest.php`, etc.)**:
    -   Test methods like `start()`, `stop()`, `checkStatus()`.
    -   Dependencies on external processes (`ping`, `ssh`, `wol`) will be mocked to test the logic within the device classes themselves.

## 4. Integration Testing Strategy

These tests will verify that different parts of the application work together as expected, focusing on the Symfony Console commands.

-   **[x] Providers Testing**:
    -   `ScheduleProvider` - create schedules from data, check cron schedules
    -   `UpsProvider` - create UPS list, get UPS by identifier, exception handling
    -   `DeviceProvider` - create devices with DeviceFactory, get device by name, exception handling

-   **[x] Commands Testing**:
    -   `ShowScheduleCommand` - Integration test with CommandTester ✅
    -   `ShowDevicesCommand` - Integration test without `--with-status` (no real ping) ✅
    -   `CheckDeviceStatusCommand` - Check device status with argument ✅
    -   `StartDeviceCommand` - Start device (non-interactive mode) ✅
    -   `StopDeviceCommand` - Stop device (non-interactive mode, with mocking) ✅

-   **[x] Test Fixes**:
    -   Fixed table format assertions (UTF-8 → ASCII)
    -   StopDeviceCommand uses Device mocking (SSH key issue)
    -   Start/CheckStatus commands now mocked to avoid real ping/WOL ✅

-   **[x] Service/Cron Testing** (20 tests):
    -   `execute()` - main entry point with UPS mode routing ✅
    -   `handleBatteryMode()` - stops devices based on UPS battery levels ✅
    -   `handleOnlineMode()` - executes scheduled tasks ✅
    -   `commandStart()` - starts devices with UPS battery checks ✅
    -   `commandStop()` - stops devices ✅
    -   Exception handling and logging ✅
    -   **Total: 175 tests, 453 assertions** 🎉

-   **[x] Additional Commands & Coverage Improvements** ⭐:
    -   Configuration::getSshKey() - File I/O test ✅
    -   ExecuteCronCommand - Cron execution integration (7 tests) ✅
    -   ShowUpsCommand - UPS display command (8 tests) ✅
    -   **Final: 175 tests, 453 assertions, 87.05% coverage (363/417 lines)** 🎊

**Notes:**
- Interactive mode for Start/Stop commands is not implemented (requires TTY simulation, low priority)
- Full --with-status test isn’t implemented (requires Ping mocking, basic test exists)
- Test configuration isolation achieved through test data arrays

## 5. CI/CD Integration

-   **[x] GitLab CI/CD**:
    -   Created `.gitlab-ci.yml` workflow file
    -   Pipeline triggers on every `push` and `merge_request`
    -   Automated workflow: checkout code → install dependencies (`composer install`) → run full test suite (`bin/phpunit`)
    -   Code coverage reports generated for main branches
    -   Artifacts stored for 1 week (JUnit) and 1 month (coverage reports)
