<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit;

use EvilStudio\HAT\Kernel;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class KernelTest extends TestCase
{
    protected const string ISOLATED_CACHE_PREFIX = 'kernel-test-';

    protected string $projectDirectory;
    protected string $parametersFilePath;
    protected ?string $isolatedProjectDirectory = null;
    protected ?string $isolatedCacheDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The kernel is booted against a throwaway copy of config/ so that a crash
        // mid-test can never leave the real installation without its parameters file.
        $this->projectDirectory = $this->createIsolatedProjectDirectory();
        $this->parametersFilePath = $this->projectDirectory . '/config/parameters.yaml';
    }

    protected function tearDown(): void
    {
        $this->removeIsolatedCacheDirectory();
        $this->removeIsolatedProjectDirectory();

        parent::tearDown();
    }

    public function testGetNameReturnsApplicationName(): void
    {
        $kernel = new Kernel('test', true);

        $this->assertSame('HAT (HomeLab Assistant Tools)', $kernel->getName());
    }

    public function testBootInTestEnvironmentDoesNotRequireParametersFile(): void
    {
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

        return new class ($environment, true, $this->isolatedCacheDirectory, $this->projectDirectory) extends Kernel {
            public function __construct(
                string $environment,
                bool $debug,
                private readonly string $cacheDirectory,
                private readonly string $projectDirectory
            ) {
                parent::__construct($environment, $debug);
            }

            public function getCacheDir(): string
            {
                return $this->cacheDirectory;
            }

            public function getProjectDir(): string
            {
                return $this->projectDirectory;
            }
        };
    }

    protected function createIsolatedProjectDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/hat-kernel-test-' . str_replace('.', '', uniqid('', true));
        $realProjectDirectory = dirname(__DIR__, 2);

        $filesystem = new Filesystem();
        $filesystem->mirror($realProjectDirectory . '/config', $directory . '/config');
        $filesystem->remove($directory . '/config/parameters.yaml');
        $filesystem->mkdir($directory . '/var/data');
        // services.yaml loads '../src/' and the Doctrine mapping points at src/Entity.
        $filesystem->symlink($realProjectDirectory . '/src', $directory . '/src');

        $this->isolatedProjectDirectory = $directory;

        return $directory;
    }

    protected function removeIsolatedProjectDirectory(): void
    {
        if ($this->isolatedProjectDirectory === null) {
            return;
        }

        (new Filesystem())->remove($this->isolatedProjectDirectory);
        $this->isolatedProjectDirectory = null;
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
