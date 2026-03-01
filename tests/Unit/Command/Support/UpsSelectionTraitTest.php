<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Support;

use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpsSelectionTraitTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testResolveUpsIdReturnsParsedIdFromArgument(): void
    {
        $upsService = $this->createMock(UpsService::class);
        $helper = $this->createHelper($upsService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('id')->willReturn('3');
        $upsService->expects($this->never())->method('listUps');

        $result = $helper->callResolveUpsId($input, $io);

        $this->assertSame(3, $result);
    }

    public function testResolveUpsIdReturnsFalseWhenIdMissingInNonInteractiveMode(): void
    {
        $upsService = $this->createMock(UpsService::class);
        $helper = $this->createHelper($upsService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('id')->willReturn(null);
        $input->expects($this->once())->method('isInteractive')->willReturn(false);
        $io->expects($this->once())->method('error')->with("Argument 'id' is required.");

        $result = $helper->callResolveUpsId($input, $io);

        $this->assertFalse($result);
    }

    public function testPromptUpsIdReturnsSelectedUpsId(): void
    {
        $upsA = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local');
        $upsB = $this->createUpsEntity(2, 'Backup UPS', 'ups-backup', 'ups2.local');
        $upsService = $this->createMock(UpsService::class);
        $helper = $this->createHelper($upsService);
        $io = $this->createMock(SymfonyStyle::class);

        $upsService->expects($this->once())->method('listUps')->willReturn([$upsA, $upsB]);
        $io->expects($this->once())
            ->method('choice')
            ->with(
                'UPS',
                ['None', '1: Main UPS (ups-main)', '2: Backup UPS (ups-backup)'],
                '2: Backup UPS (ups-backup)'
            )
            ->willReturn('2: Backup UPS (ups-backup)');

        $result = $helper->callPromptUpsId($io, 2);

        $this->assertSame(2, $result);
    }

    protected function createHelper(UpsService $upsService): object
    {
        return new class ($upsService) {
            use \EvilStudio\HAT\Command\Support\UpsSelectionTrait;

            public function __construct(
                protected UpsService $upsService
            ) {
            }

            public function callResolveUpsId(
                InputInterface $input,
                SymfonyStyle $io,
                string $question = 'UPS',
                string $emptyListMessage = 'No UPS entries available.',
                bool $requireArgumentWhenNonInteractive = true
            ): int|false {
                return $this->resolveUpsId(
                    $input,
                    $io,
                    $question,
                    $emptyListMessage,
                    $requireArgumentWhenNonInteractive
                );
            }

            public function callPromptUpsId(SymfonyStyle $io, ?int $defaultUpsId = null): int|false|null
            {
                return $this->promptUpsId($io, $defaultUpsId);
            }
        };
    }
}
