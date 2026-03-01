<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Support;

use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeviceSelectionTraitTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testResolveDeviceIdReturnsParsedIdFromArgument(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('id')->willReturn('5');
        $io->expects($this->never())->method('error');
        $deviceService->expects($this->never())->method('listDevices');

        $result = $helper->callResolveDeviceId($input, $io);

        $this->assertSame(5, $result);
    }

    public function testResolveDeviceIdReturnsFalseForInvalidId(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('id')->willReturn('invalid');
        $io->expects($this->once())->method('error')->with('id must be a positive integer.');

        $result = $helper->callResolveDeviceId($input, $io);

        $this->assertFalse($result);
    }

    public function testResolveDeviceIdReturnsFalseWhenNonInteractiveAndIdRequired(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('id')->willReturn(null);
        $input->expects($this->once())->method('isInteractive')->willReturn(false);
        $io->expects($this->once())->method('error')->with("Argument 'id' is required.");

        $result = $helper->callResolveDeviceId($input, $io);

        $this->assertFalse($result);
    }

    public function testResolveDeviceIdReturnsSelectedIdWhenChosenInteractively(): void
    {
        $device = $this->createDeviceEntity(7, 'node-7');
        $deviceService = $this->createMock(DeviceService::class);
        $helper = $this->createHelper($deviceService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('id')->willReturn(null);
        $input->expects($this->once())->method('isInteractive')->willReturn(true);
        $deviceService->expects($this->once())->method('listDevices')->willReturn([$device]);
        $io->expects($this->once())
            ->method('choice')
            ->with('Device', ['7: node-7'])
            ->willReturn('7: node-7');

        $result = $helper->callResolveDeviceId($input, $io);

        $this->assertSame(7, $result);
    }

    protected function createHelper(DeviceService $deviceService): object
    {
        return new class ($deviceService) {
            use \EvilStudio\HAT\Command\Support\DeviceSelectionTrait;

            public function __construct(
                protected DeviceService $deviceService
            ) {
            }

            public function callResolveDeviceId(
                InputInterface $input,
                SymfonyStyle $io,
                string $question = 'Device',
                string $emptyListMessage = 'No devices available.',
                bool $requireArgumentWhenNonInteractive = true
            ): int|false {
                return $this->resolveDeviceId(
                    $input,
                    $io,
                    $question,
                    $emptyListMessage,
                    $requireArgumentWhenNonInteractive
                );
            }
        };
    }
}
