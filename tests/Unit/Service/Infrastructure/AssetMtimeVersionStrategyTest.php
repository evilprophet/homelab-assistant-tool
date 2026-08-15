<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Infrastructure;

use EvilStudio\HAT\Service\Infrastructure\AssetMtimeVersionStrategy;
use EvilStudio\HAT\Tests\Support\TemporaryPathTrait;
use PHPUnit\Framework\TestCase;

class AssetMtimeVersionStrategyTest extends TestCase
{
    use TemporaryPathTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryPaths();

        parent::tearDown();
    }

    public function testAppliesVersionDerivedFromModificationTime(): void
    {
        $applicationDirectory = $this->createApplicationDirectoryWithAsset(1_700_000_000);
        $strategy = new AssetMtimeVersionStrategy($applicationDirectory);

        $this->assertSame(dechex(1_700_000_000), $strategy->getVersion('assets/app.css'));
        $this->assertSame(
            sprintf('assets/app.css?v=%s', dechex(1_700_000_000)),
            $strategy->applyVersion('assets/app.css')
        );
    }

    public function testRebuiltAssetGetsANewVersion(): void
    {
        $applicationDirectory = $this->createApplicationDirectoryWithAsset(1_700_000_000);
        $before = (new AssetMtimeVersionStrategy($applicationDirectory))->getVersion('assets/app.css');

        $assetPath = $applicationDirectory . '/public/assets/app.css';
        touch($assetPath, 1_800_000_000);
        clearstatcache(true, $assetPath);
        $after = (new AssetMtimeVersionStrategy($applicationDirectory))->getVersion('assets/app.css');

        $this->assertNotSame($before, $after);
    }

    public function testMissingAssetIsLeftUnversionedInsteadOfFailing(): void
    {
        $applicationDirectory = $this->createApplicationDirectoryWithAsset(1_700_000_000);
        $strategy = new AssetMtimeVersionStrategy($applicationDirectory);

        $this->assertSame('', $strategy->getVersion('assets/missing.css'));
        $this->assertSame('assets/missing.css', $strategy->applyVersion('assets/missing.css'));
    }

    protected function createApplicationDirectoryWithAsset(int $modifiedAt): string
    {
        $applicationDirectory = $this->createTemporaryPath('hat-asset-version-');
        mkdir($applicationDirectory . '/public/assets', 0777, true);

        $assetPath = $applicationDirectory . '/public/assets/app.css';
        file_put_contents($assetPath, 'body{}');
        touch($assetPath, $modifiedAt);

        return $applicationDirectory;
    }
}
