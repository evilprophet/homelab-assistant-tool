<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Repository\ActionLogRepository;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The per-command tests build commands by hand with mocked services, so none of
 * them would notice a DI wiring break: a renamed constructor argument, a dropped
 * services.yaml bind, or a command that stopped being registered. This resolves
 * commands from the real container and runs them against the test database.
 */
class CommandWiringTest extends DatabaseIntegrationTestCase
{
    public function testEveryHatCommandIsRegisteredAndConstructible(): void
    {
        $application = new Application(self::$kernel);
        $names = [];

        foreach ($application->all() as $name => $command) {
            if (!str_starts_with($name, 'hat:')) {
                continue;
            }

            $names[] = $name;
            // Resolving the command already instantiated it through the container,
            // so a broken dependency would have thrown before this point.
            $this->assertNotSame('', $command->getDescription(), $name);
        }

        sort($names);
        $this->assertSame(
            [
                'hat:cron:execute',
                'hat:device:check-status',
                'hat:device:create',
                'hat:device:list',
                'hat:device:remove',
                'hat:device:ssh',
                'hat:device:start',
                'hat:device:stop',
                'hat:device:update',
                'hat:logs:cleanup',
                'hat:logs:list',
                'hat:schedule:create',
                'hat:schedule:list',
                'hat:schedule:remove',
                'hat:schedule:update',
                'hat:setup:configure',
                'hat:setup:db',
                'hat:setup:init',
                'hat:ups:create',
                'hat:ups:list',
                'hat:ups:remove',
                'hat:ups:update',
                'hat:user:create',
                'hat:user:remove',
                'hat:user:reset-password',
            ],
            $names
        );
    }

    public function testDeviceCreateCommandPersistsThroughTheRealServiceStack(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('hat:device:create'));

        $exitCode = $tester->execute([
            'name' => 'wired-node',
            'ip' => '10.9.9.9',
            'mac' => 'aa:bb:cc:dd:ee:01',
            'platform' => DevicePlatform::LINUX->value,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $device = static::getContainer()->get(DeviceService::class)->getDeviceByName('wired-node');
        $this->assertSame('10.9.9.9', $device->getIp());

        // The action log is written by a second service reached through the same
        // container graph, so this also pins that wiring.
        $logs = (new ActionLogRepository($this->entityManager))
            ->findByFilters(ActionLog::SOURCE_CLI, null, 'device.create', 10);
        $this->assertNotEmpty($logs);
    }

    public function testDeviceListCommandRendersRowsFromTheDatabase(): void
    {
        static::getContainer()->get(DeviceService::class)->createDevice(
            'listed-node',
            '10.9.9.10',
            'aa:bb:cc:dd:ee:02',
            DevicePlatform::GENERIC->value
        );

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('hat:device:list'));

        $this->assertSame(Command::SUCCESS, $tester->execute([], ['interactive' => false]));
        $this->assertStringContainsString('listed-node', $tester->getDisplay());
    }
}
