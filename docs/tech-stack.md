# Tech Stack

This document outlines the technology stack used in the Homelab Assistant Tool.

## Language

- **PHP 8.1+**

## Core Framework & Components

The application is built as a command-line tool using the following Symfony components:

- **Symfony Console**: For building the CLI interface and commands.
- **Symfony Dependency Injection**: For managing services and dependencies.
- **Symfony Config**: For handling application configuration.
- **Symfony YAML**: For parsing the `parameters.yaml` configuration file.
- **Symfony Filesystem**: For file system operations.
- **Symfony Process**: For executing external processes (like `ssh` or `ping`).

## Key Libraries

- **Monolog**: For logging application events, particularly for cron jobs and errors.
- **PHPSECLib**: For handling SSH connections to manage remote devices.
- **diegonz/php-wake-on-lan**: For sending Wake-on-LAN magic packets to start devices.
- **dragonmantank/cron-expression**: For parsing and calculating cron schedule expressions.
- **geerlingguy/ping**: A simple library for checking device status via ICMP pings.
- **Network UPS Tools (NUT)**: Used via the `upsc` command-line client to monitor the status of the UPS.

## Development & Tooling

- **Composer**: For dependency management.
