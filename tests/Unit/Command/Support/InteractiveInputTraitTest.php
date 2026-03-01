<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InteractiveInputTraitTest extends TestCase
{
    public function testResolveRequiredArgumentReturnsTrimmedArgumentValue(): void
    {
        $helper = $this->createHelper();
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('name')->willReturn('  node-1  ');
        $io->expects($this->never())->method('error');

        $result = $helper->callResolveRequiredArgument($input, $io, 'name', 'Device name');

        $this->assertSame('node-1', $result);
    }

    public function testResolveRequiredArgumentReturnsNullWhenMissingInNonInteractiveMode(): void
    {
        $helper = $this->createHelper();
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('getArgument')->with('name')->willReturn(null);
        $input->expects($this->once())->method('isInteractive')->willReturn(false);
        $io->expects($this->once())->method('error')->with("Argument 'name' is required.");

        $result = $helper->callResolveRequiredArgument($input, $io, 'name', 'Device name');

        $this->assertNull($result);
    }

    public function testResolveStringOptionUsesExplicitOptionValue(): void
    {
        $helper = $this->createHelper();
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $input->expects($this->once())->method('hasParameterOption')->with('--name')->willReturn(true);
        $input->expects($this->once())->method('getOption')->with('name')->willReturn('  node-2  ');
        $io->expects($this->never())->method('error');

        $result = $helper->callResolveStringOption($input, $io, 'name', 'Device name', 'default');

        $this->assertSame('node-2', $result);
    }

    public function testParseOptionalPositiveIntRejectsInvalidValue(): void
    {
        $helper = $this->createHelper();
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Option --ups-id must be a positive integer.');

        $result = $helper->callParseOptionalPositiveInt('0', '--ups-id', $io);

        $this->assertFalse($result);
    }

    public function testParseOptionalNonNegativeIntAllowsNullWhenNotProvided(): void
    {
        $helper = $this->createHelper();
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('error');

        $result = $helper->callParseOptionalNonNegativeInt(null, '--threshold', $io);

        $this->assertNull($result);
    }

    public function testNullIfEmptyNormalizesBlankStringToNull(): void
    {
        $helper = $this->createHelper();

        $result = $helper->callNullIfEmpty('   ');

        $this->assertNull($result);
    }

    protected function createHelper(): object
    {
        return new class {
            use \EvilStudio\HAT\Command\Support\InteractiveInputTrait;

            public function callResolveRequiredArgument(
                InputInterface $input,
                SymfonyStyle $io,
                string $argumentName,
                string $question
            ): ?string {
                return $this->resolveRequiredArgument($input, $io, $argumentName, $question);
            }

            public function callResolveStringOption(
                InputInterface $input,
                SymfonyStyle $io,
                string $optionName,
                string $question,
                string $defaultValue
            ): ?string {
                return $this->resolveStringOption($input, $io, $optionName, $question, $defaultValue);
            }

            public function callParseOptionalPositiveInt(
                mixed $value,
                string $optionName,
                SymfonyStyle $io
            ): int|false|null {
                return $this->parseOptionalPositiveInt($value, $optionName, $io);
            }

            public function callParseOptionalNonNegativeInt(
                mixed $value,
                string $optionName,
                SymfonyStyle $io
            ): int|false|null {
                return $this->parseOptionalNonNegativeInt($value, $optionName, $io);
            }

            public function callNullIfEmpty(mixed $value): ?string
            {
                return $this->nullIfEmpty($value);
            }
        };
    }
}
