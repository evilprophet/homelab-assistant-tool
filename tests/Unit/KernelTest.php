<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit;

use EvilStudio\HAT\Kernel;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class KernelTest extends TestCase
{
    protected const string ISOLATED_CACHE_PREFIX = 'kernel-test-';

    protected string $projectDirectory;
    protected string $parametersFilePath;
    protected ?string $parametersBackupPath = null;
    protected ?string $isolatedCacheDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDirectory = dirname(__DIR__, 2);
        $this->parametersFilePath = $this->projectDirectory . '/config/parameters.yaml';
    }

    protected function tearDown(): void
    {
        $this->restoreParametersFile();
        $this->removeIsolatedCacheDirectory();

        parent::tearDown();
    }

    public function testGetNameReturnsApplicationName(): void
    {
        $kernel = new Kernel('test', true);

        $this->assertSame('HAT (HomeLab Assistant Tools)', $kernel->getName());
    }

    public function testBootInTestEnvironmentDoesNotRequireParametersFile(): void
    {
        $this->moveParametersFileAwayIfExists();

        $kernel = $this->createIsolatedCacheKernel('test');
        $kernel->boot();

        try {
            $this->assertTrue($kernel->getContainer()->hasParameter('sqlite_database_path'));
            $this->assertSame(
                'var/data/hat_test.sqlite',
                (string)$kernel->getContainer()->getParameter('sqlite_database_path')
            );
        } finally {
            $kernel->shutdown();
        }
    }

    public function testBootInProdEnvironmentThrowsWhenParametersFileIsMissing(): void
    {
        $this->moveParametersFileAwayIfExists();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Missing required configuration file '{$this->parametersFilePath}'");

        $kernel = $this->createIsolatedCacheKernel('prod');
        $kernel->boot();
    }

    protected function createIsolatedCacheKernel(string $environment): Kernel
    {
        $this->isolatedCacheDirectory = sprintf(
            '%s/var/cache/%s%s',
            $this->projectDirectory,
            self::ISOLATED_CACHE_PREFIX,
            str_replace('.', '', uniqid('', true))
        );

        return new class ($environment, true, $this->isolatedCacheDirectory) extends Kernel {
            public function __construct(
                string $environment,
                bool $debug,
                private readonly string $cacheDirectory
            ) {
                parent::__construct($environment, $debug);
            }

            public function getCacheDir(): string
            {
                return $this->cacheDirectory;
            }
        };
    }

    protected function moveParametersFileAwayIfExists(): void
    {
        if (!is_file($this->parametersFilePath)) {
            return;
        }

        $this->parametersBackupPath = $this->parametersFilePath . '.bak-kernel-test-' . uniqid('', true);

        rename($this->parametersFilePath, $this->parametersBackupPath);
    }

    protected function restoreParametersFile(): void
    {
        if ($this->parametersBackupPath === null) {
            return;
        }

        if (is_file($this->parametersBackupPath)) {
            rename($this->parametersBackupPath, $this->parametersFilePath);
        }

        $this->parametersBackupPath = null;
    }

    protected function removeIsolatedCacheDirectory(): void
    {
        $cacheDirectory = $this->isolatedCacheDirectory;
        if ($cacheDirectory === null || !is_dir($cacheDirectory)) {
            return;
        }

        $this->isolatedCacheDirectory = null;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isDir()) {
                rmdir($path);
                continue;
            }

            unlink($path);
        }

        rmdir($cacheDirectory);
    }
}
