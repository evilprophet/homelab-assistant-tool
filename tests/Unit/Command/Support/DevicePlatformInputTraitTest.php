<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class DevicePlatformInputTraitTest extends TestCase
{
    public function testNormalizePlatformTrimsAndLowercasesValue(): void
    {
        $helper = $this->createHelper();

        $result = $helper->callNormalizePlatform('  UBUNTU  ');

        $this->assertSame('ubuntu', $result);
    }

    public function testIsPlatformSupportedReturnsTrueForKnownPlatform(): void
    {
        $helper = $this->createHelper();
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('error');

        $result = $helper->callIsPlatformSupported('linux', $io);

        $this->assertTrue($result);
    }

    public function testIsPlatformSupportedReturnsFalseForUnknownPlatform(): void
    {
        $helper = $this->createHelper();
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');

        $result = $helper->callIsPlatformSupported('unknown-os', $io);

        $this->assertFalse($result);
    }

    protected function createHelper(): object
    {
        return new class {
            use \EvilStudio\HAT\Command\Support\DevicePlatformInputTrait;

            public function callNormalizePlatform(string $platform): string
            {
                return $this->normalizePlatform($platform);
            }

            public function callIsPlatformSupported(string $platform, SymfonyStyle $io): bool
            {
                return $this->isPlatformSupported($platform, $io);
            }
        };
    }
}
