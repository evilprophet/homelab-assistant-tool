<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Support;

use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScheduleDeviceSelectionTraitTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testParseDeviceIdsReturnsUniqueNormalizedIds(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $io = $this->createMock(SymfonyStyle::class);

        $result = $helper->callParseDeviceIds(['1', '2', '1'], $io);

        $this->assertSame([1, 2], $result);
    }

    public function testParseDeviceIdsReturnsNullForInvalidValue(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('All --device-id values must be positive integers.');

        $result = $helper->callParseDeviceIds(['1', 'invalid'], $io);

        $this->assertNull($result);
    }

    public function testPromptDeviceIdsReturnsIdsFromSelectedLabels(): void
    {
        $deviceA = $this->createDeviceEntity(1, 'node-1');
        $deviceB = $this->createDeviceEntity(2, 'node-2');
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $io = $this->createMock(SymfonyStyle::class);

        $deviceService->expects($this->once())->method('listDevices')->willReturn([$deviceA, $deviceB]);
        $io->expects($this->once())
            ->method('choice')
            ->with('Schedule devices', ['None', '1: node-1', '2: node-2'], '2: node-2', true)
            ->willReturn(['1: node-1', '2: node-2']);

        $result = $helper->callPromptDeviceIds($io, [2], 'Schedule devices');

        $this->assertSame([1, 2], $result);
    }

    public function testPromptDeviceIdsDefaultsToNoneWhenNoDevicesAreAttached(): void
    {
        $deviceA = $this->createDeviceEntity(1, 'node-1');
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $io = $this->createMock(SymfonyStyle::class);

        $deviceService->expects($this->once())->method('listDevices')->willReturn([$deviceA]);
        $io->expects($this->once())
            ->method('choice')
            ->with('Schedule devices', ['None', '1: node-1'], 'None', true)
            ->willReturn(['None']);

        $result = $helper->callPromptDeviceIds($io, [], 'Schedule devices');

        $this->assertSame([], $result);
    }

    protected function createHelper(DeviceService $deviceService): object
    {
        return new class ($deviceService) {
            use \EvilStudio\HAT\Command\Support\ScheduleDeviceSelectionTrait;

            public function __construct(
                protected DeviceService $deviceService
            ) {
            }

            public function callParseDeviceIds(array $values, SymfonyStyle $io): ?array
            {
                return $this->parseDeviceIds($values, $io);
            }

            public function callPromptDeviceIds(
                SymfonyStyle $io,
                array $defaultDeviceIds,
                string $question = 'Schedule devices'
            ): ?array {
                return $this->promptDeviceIds($io, $defaultDeviceIds, $question);
            }
        };
    }
}
