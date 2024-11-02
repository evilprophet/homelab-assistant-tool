# HAT - Homelab Assistant Tool

## Overview

HAT is a tool to help manage a homelab. It is designed to be run on single node which is always on, and this node is
used to manage the rest of the homelab.

## Installation

1. Create app via composer `composer create-project evilstudio/homelab-assistant-tool`.
2. Copy `config/parameters.yaml.template` to `config/parameters.yaml` and set the correct values.
3. Check if your ssh key can be used to ssh into devices without password.

## Commands Overview

Here is a list of commands available in HAT.<br>
Be aware that some commands might not be available on all platforms.

| Command             | Description                                              | Usage                                         |
|---------------------|----------------------------------------------------------|-----------------------------------------------|
| **Show Devices**    | Show list of all devices.                                | `php bin/console.php hat:device:show-all`     |
| **Check Status**    | Check status of a specified device.                      | `php bin/console.php hat:device:check-status` |
| **SSH Into Device** | SSH into a specified device.                             | `php bin/console.php hat:device:ssh`          |
| **Start Device**    | Start a specified device via WOL.                        | `php bin/console.php hat:device:start`        |
| **Stop Device**     | Stop a specified device.                                 | `php bin/console.php hat:device:stop`         |
| **Cron Job**        | Execute cron schedules <br/> should be added to crontab. | `php bin/console.php hat:cron:run`            |

**_NOTE:_**  Logs for cron job can be found in `var/logs/cron.log`.
