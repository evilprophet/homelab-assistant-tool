<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Controller;

use EvilStudio\HAT\Controller\DeviceController;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\RuntimeStatusResolver;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class DeviceControllerTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExtractFormDataNormalizesMacAddress(): void
    {
        $controller = $this->createTestableController();
        $request = Request::create('/devices/new', 'POST', [
            'name' => 'node-1',
            'ip' => '10.0.0.10',
            'mac' => 'AA-BB-CC-DD-EE-FF',
            'platform' => 'generic',
            'username' => 'root',
            'ups_id' => '1',
            'threshold_minutes' => '10',
        ]);

        $result = $controller->callExtractFormData($request);

        $this->assertSame('AA:BB:CC:DD:EE:FF', $result['mac']);
        $this->assertSame('node-1', $result['name']);
    }

    public function testExtractFormDataMapsEmptyUsernameToNull(): void
    {
        $controller = $this->createTestableController();
        $request = Request::create('/devices/new', 'POST', [
            'name' => 'node-2',
            'ip' => '10.0.0.11',
            'mac' => '00:11:22:33:44:66',
            'platform' => 'generic',
            'username' => '  ',
            'ups_id' => '',
            'threshold_minutes' => '',
        ]);

        $result = $controller->callExtractFormData($request);

        $this->assertNull($result['username']);
    }

    public function testExtractFormDataMapsAutoStopCheckboxState(): void
    {
        $controller = $this->createTestableController();

        $uncheckedRequest = Request::create('/devices/new', 'POST', [
            'allow_auto_stop_present' => '1',
        ]);
        $uncheckedResult = $controller->callExtractFormData($uncheckedRequest);
        $this->assertFalse($uncheckedResult['allow_auto_stop']);

        $checkedRequest = Request::create('/devices/new', 'POST', [
            'allow_auto_stop_present' => '1',
            'allow_auto_stop' => '1',
        ]);
        $checkedResult = $controller->callExtractFormData($checkedRequest);
        $this->assertTrue($checkedResult['allow_auto_stop']);

        $legacyRequest = Request::create('/devices/new', 'POST', []);
        $legacyResult = $controller->callExtractFormData($legacyRequest);
        $this->assertTrue($legacyResult['allow_auto_stop']);
    }

    public function testValidateFormDataReturnsErrorsForInvalidInputAndDuplicates(): void
    {
        $existingDevice = $this->createDeviceEntity(2, 'node-1');
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $deviceRepository->expects($this->once())->method('findOneByName')->with('node-1')->willReturn($existingDevice);
        $upsRepository->expects($this->once())->method('findById')->with(3)->willReturn(null);

        $controller = $this->createTestableController($deviceRepository, $upsRepository);
        $errors = $controller->callValidateFormData(
            [
                'name' => 'node-1',
                'ip' => 'invalid-ip',
                'mac' => 'invalid-mac',
                'platform' => 'invalid-platform',
                'username' => 'root',
                'ups_id' => '3',
                'threshold_minutes' => '-1',
            ],
            1
        );

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('ip', $errors);
        $this->assertArrayHasKey('mac', $errors);
        $this->assertArrayHasKey('platform', $errors);
        $this->assertArrayHasKey('ups_id', $errors);
        $this->assertArrayHasKey('threshold_minutes', $errors);
    }

    protected function createTestableController(
        ?DeviceRepository $deviceRepository = null,
        ?UpsRepository $upsRepository = null
    ): object {
        return new class (
            $this->createMock(DeviceService::class),
            $this->createMock(UpsService::class),
            $this->createMock(DeviceOperationsService::class),
            $this->createMock(ActionLogService::class),
            $deviceRepository ?? $this->createMock(DeviceRepository::class),
            $upsRepository ?? $this->createMock(UpsRepository::class),
            $this->createMock(RuntimeStatusResolver::class)
        ) extends DeviceController {
            public function callExtractFormData(Request $request): array
            {
                return $this->extractFormData($request);
            }

            public function callValidateFormData(array $formData, ?int $currentDeviceId): array
            {
                return $this->validateFormData($formData, $currentDeviceId);
            }
        };
    }
}
