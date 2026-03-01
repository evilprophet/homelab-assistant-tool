<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class BooleanOptionTraitTest extends TestCase
{
    public function testParseBoolOptionReturnsTrueForTruthyValue(): void
    {
        $helper = $this->createHelper();
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('error');

        $result = $helper->callParseBoolOption('yes', '--is-enabled', $io);

        $this->assertTrue($result);
    }

    public function testParseBoolOptionReturnsNullAndReportsErrorForInvalidValue(): void
    {
        $helper = $this->createHelper();
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Option --is-enabled must be a boolean value (1/0, true/false, yes/no).');

        $result = $helper->callParseBoolOption('maybe', '--is-enabled', $io);

        $this->assertNull($result);
    }

    protected function createHelper(): object
    {
        return new class {
            use \EvilStudio\HAT\Command\Support\BooleanOptionTrait;

            public function callParseBoolOption(mixed $value, string $optionName, SymfonyStyle $io): ?bool
            {
                return $this->parseBoolOption($value, $optionName, $io);
            }
        };
    }
}
