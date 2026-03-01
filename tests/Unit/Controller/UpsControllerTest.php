<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Controller;

use EvilStudio\HAT\Controller\UpsController;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class UpsControllerTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testMapEntityToFormDataConvertsThresholdToMinutes(): void
    {
        $controller = $this->createTestableController();
        $ups = $this->createUpsEntity(10, 'Main UPS', 'ups-main', 'ups.local');
        $ups->setSafeBatteryRuntimeThreshold(650);

        $result = $controller->callMapEntityToFormData($ups);

        $this->assertSame('10', $result['safe_threshold_minutes']);
        $this->assertSame('ups-main', $result['identifier']);
    }

    public function testValidateFormDataReturnsErrorsForInvalidAndDuplicateValues(): void
    {
        $existingUps = $this->createUpsEntity(20, 'Existing', 'ups-main', 'ups.local');
        $upsRepository = $this->createMock(UpsRepository::class);
        $upsRepository->expects($this->once())
            ->method('findOneByIdentifier')
            ->with('ups-main')
            ->willReturn($existingUps);

        $controller = $this->createTestableController(null, $upsRepository);
        $errors = $controller->callValidateFormData(
            [
                'name' => '',
                'identifier' => 'ups-main',
                'host' => '',
                'safe_threshold_minutes' => '-1',
            ],
            10
        );

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('host', $errors);
        $this->assertArrayHasKey('safe_threshold_minutes', $errors);
        $this->assertArrayHasKey('identifier', $errors);
    }

    public function testExtractFormDataTrimsInputValues(): void
    {
        $controller = $this->createTestableController();
        $request = Request::create('/ups/new', 'POST', [
            'name' => '  Main UPS ',
            'identifier' => ' ups-main ',
            'host' => ' ups.local ',
            'safe_threshold_minutes' => ' 5 ',
        ]);

        $result = $controller->callExtractFormData($request);

        $this->assertSame('Main UPS', $result['name']);
        $this->assertSame('ups-main', $result['identifier']);
        $this->assertSame('ups.local', $result['host']);
        $this->assertSame('5', $result['safe_threshold_minutes']);
    }

    public function testSafeCreateWebLogSwallowsExceptions(): void
    {
        $actionLogService = $this->createMock(ActionLogService::class);
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(ActionLog::SOURCE_WEB, 'ups.update', ActionLog::LEVEL_INFO, 'done')
            ->willThrowException(new class ('write failed') extends \RuntimeException {
            });

        $controller = $this->createTestableController($actionLogService);

        $controller->callSafeCreateWebLog('ups.update', ActionLog::LEVEL_INFO, 'done');
        $this->addToAssertionCount(1);
    }

    protected function createTestableController(
        ?ActionLogService $actionLogService = null,
        ?UpsRepository $upsRepository = null
    ): object {
        return new class (
            $this->createMock(UpsService::class),
            $this->createMock(UpsRuntimeService::class),
            $upsRepository ?? $this->createMock(UpsRepository::class),
            $actionLogService ?? $this->createMock(ActionLogService::class)
        ) extends UpsController {
            public function callMapEntityToFormData(\EvilStudio\HAT\Entity\Ups $ups): array
            {
                return $this->mapEntityToFormData($ups);
            }

            public function callValidateFormData(array $formData, ?int $currentUpsId): array
            {
                return $this->validateFormData($formData, $currentUpsId);
            }

            public function callExtractFormData(Request $request): array
            {
                return $this->extractFormData($request);
            }

            public function callSafeCreateWebLog(string $action, string $level, string $message): void
            {
                $this->safeCreateWebLog($action, $level, $message);
            }
        };
    }
}
