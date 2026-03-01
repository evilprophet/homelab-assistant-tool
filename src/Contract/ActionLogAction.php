<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Contract;

enum ActionLogAction: string
{
    case AUTH_LOGIN = 'auth.login';
    case AUTH_CALLBACK = 'auth.callback';
    case AUTH_LOGOUT = 'auth.logout';
    case CRON_EXECUTE = 'cron.execute';
    case LOGS_CLEANUP = 'logs.cleanup';

    case DEVICE_CREATE = 'device.create';
    case DEVICE_UPDATE = 'device.update';
    case DEVICE_REMOVE = 'device.remove';
    case DEVICE_START = 'device.start';
    case DEVICE_STOP = 'device.stop';

    case SCHEDULE_CREATE = 'schedule.create';
    case SCHEDULE_UPDATE = 'schedule.update';
    case SCHEDULE_REMOVE = 'schedule.remove';

    case UPS_CREATE = 'ups.create';
    case UPS_UPDATE = 'ups.update';
    case UPS_REMOVE = 'ups.remove';

    case USER_CREATE = 'user.create';
    case USER_UPDATE = 'user.update';
    case USER_REMOVE = 'user.remove';

    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
