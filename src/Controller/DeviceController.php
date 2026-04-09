<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Controller;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/devices')]
class DeviceController extends AbstractController
{
    protected const string MAC_PATTERN = '/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i';
    protected const string CSRF_DEVICE_FORM_CREATE = 'device.form.create';
    protected const string CSRF_DEVICE_FORM_EDIT_PREFIX = 'device.form.edit.';
    protected const string CSRF_DEVICE_REMOVE_PREFIX = 'device.remove.';
    protected const string CSRF_DEVICE_START_PREFIX = 'device.start.';
    protected const string CSRF_DEVICE_STOP_PREFIX = 'device.stop.';
    protected const string SORT_BY_NAME_ID = 'name_id';
    protected const string SORT_BY_NETWORK = 'network';
    protected const string SORT_BY_PLATFORM = 'platform';
    protected const string SORT_BY_UPS = 'ups';
    protected const string SORT_DIRECTION_ASC = 'asc';
    protected const string SORT_DIRECTION_DESC = 'desc';
    protected const ALLOWED_SORT_BY = [
        self::SORT_BY_NAME_ID,
        self::SORT_BY_NETWORK,
        self::SORT_BY_PLATFORM,
        self::SORT_BY_UPS,
    ];
    protected const ALLOWED_SORT_DIRECTIONS = [
        self::SORT_DIRECTION_ASC,
        self::SORT_DIRECTION_DESC,
    ];

    public function __construct(
        protected DeviceService $deviceService,
        protected UpsService $upsService,
        protected DeviceOperationsService $deviceOperationsService,
        protected ActionLogService $actionLogService,
        protected DeviceRepository $deviceRepository,
        protected UpsRepository $upsRepository
    ) {
    }

    #[Route(path: '', name: 'hat_devices_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $selectedPlatform = $this->resolvePlatformFilter(trim((string)$request->query->get('platform', '')));
        $sortBy = $this->resolveSortBy((string)$request->query->get('sort_by', self::SORT_BY_NETWORK));
        $sortDirection = $this->resolveSortDirection((string)$request->query->get('sort_dir', self::SORT_DIRECTION_ASC));
        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = ActionLogService::DEFAULT_LIST_LIMIT;

        $devices = array_map(
            static fn ($runtimeDevice): array => $runtimeDevice->toArray(),
            $this->deviceOperationsService->listDevices(false)
        );
        if ($selectedPlatform !== null) {
            $devices = array_values(array_filter(
                $devices,
                static fn (array $device): bool => ($device['platform_key'] ?? '') === $selectedPlatform
            ));
        }
        $this->sortDevices($devices, $sortBy, $sortDirection);

        $total = count($devices);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $devices = array_slice($devices, $offset, $perPage);

        return $this->render('devices/index.html.twig', [
            'page_title' => 'Devices',
            'devices' => $devices,
            'status_by_name' => [],
            'available_platforms' => DevicePlatform::values(),
            'selected_platform' => $selectedPlatform,
            'platform_labels' => DevicePlatform::labels(),
            'platform_linux_runtime' => DevicePlatform::linuxRuntimeMap(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'current_sort_by' => $sortBy,
            'current_sort_dir' => $sortDirection,
        ]);
    }

    #[Route(path: '/new', name: 'hat_devices_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $formData = $this->defaultFormData();
        $errors = [];

        if ($request->isMethod(Request::METHOD_POST)) {
            $formData = $this->extractFormData($request);
            $csrfToken = (string)$request->request->get('_token', '');
            if (!$this->isCsrfTokenValid(self::CSRF_DEVICE_FORM_CREATE, $csrfToken)) {
                $errors['_global'][] = 'Invalid CSRF token.';
            } else {
                $errors = $this->validateFormData($formData, null);
            }

            if (empty($errors)) {
                try {
                    $device = $this->deviceService->createDevice(
                        $formData['name'],
                        $formData['ip'],
                        $formData['mac'],
                        $formData['platform'],
                        $formData['username'],
                        $this->resolveThresholdSeconds($formData['threshold_minutes']),
                        $this->resolveUpsId($formData['ups_id'])
                    );

                    $message = sprintf("Device '%s' created with ID %d.", $device->getName(), (int)$device->getId());
                    $this->addFlash('success', $message);
                    $this->safeCreateWebLog(ActionLogAction::DEVICE_CREATE, ActionLog::LEVEL_INFO, $message);

                    return $this->redirectToRoute('hat_devices_index');
                } catch (Throwable $exception) {
                    $errors['_global'][] = $exception->getMessage();
                }
            }
        }

        return $this->render('devices/form.html.twig', [
            'page_title' => 'New Device',
            'mode' => 'create',
            'form_data' => $formData,
            'errors' => $errors,
            'platforms' => DevicePlatform::values(),
            'platform_labels' => DevicePlatform::labels(),
            'ups_collection' => $this->upsService->listUps(),
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'hat_devices_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        try {
            $device = $this->deviceService->getDeviceById($id);
        } catch (EntityNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $formData = $this->mapEntityToFormData($device);
        $errors = [];

        if ($request->isMethod(Request::METHOD_POST)) {
            $formData = $this->extractFormData($request);
            $csrfToken = (string)$request->request->get('_token', '');
            if (!$this->isCsrfTokenValid(self::CSRF_DEVICE_FORM_EDIT_PREFIX . $id, $csrfToken)) {
                $errors['_global'][] = 'Invalid CSRF token.';
            } else {
                $errors = $this->validateFormData($formData, $id);
            }

            if (empty($errors)) {
                try {
                    $updatedDevice = $this->deviceService->updateDevice(
                        $id,
                        $formData['name'],
                        $formData['ip'],
                        $formData['mac'],
                        $formData['platform'],
                        $formData['username'],
                        $this->resolveThresholdSeconds($formData['threshold_minutes']),
                        $this->resolveUpsId($formData['ups_id'])
                    );

                    $message = sprintf("Device '%s' updated.", $updatedDevice->getName());
                    $this->addFlash('success', $message);
                    $this->safeCreateWebLog(ActionLogAction::DEVICE_UPDATE, ActionLog::LEVEL_INFO, $message);

                    return $this->redirectToRoute('hat_devices_edit', ['id' => $id]);
                } catch (Throwable $exception) {
                    $errors['_global'][] = $exception->getMessage();
                }
            }
        }

        return $this->render('devices/form.html.twig', [
            'page_title' => sprintf('Edit Device #%d', $id),
            'breadcrumbs' => [
                [
                    'label' => 'Devices',
                    'href' => $this->generateUrl('hat_devices_index'),
                ],
                [
                    'label' => sprintf('Edit Device #%d', $id),
                ],
            ],
            'mode' => 'edit',
            'device' => $device,
            'form_data' => $formData,
            'errors' => $errors,
            'platforms' => DevicePlatform::values(),
            'platform_labels' => DevicePlatform::labels(),
            'ups_collection' => $this->upsService->listUps(),
        ]);
    }

    #[Route(path: '/{id}/remove', name: 'hat_devices_remove_confirm', methods: ['GET'])]
    public function removeConfirm(int $id): Response
    {
        try {
            $device = $this->deviceService->getDeviceById($id);
        } catch (EntityNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $linkedScheduleNames = [];
        foreach ($device->getSchedules()->toArray() as $schedule) {
            $linkedScheduleNames[] = $schedule->getName();
        }

        return $this->render('devices/delete.html.twig', [
            'page_title' => sprintf('Remove Device #%d', $id),
            'device' => $device,
            'linked_schedule_names' => $linkedScheduleNames,
        ]);
    }

    #[Route(path: '/{id}/remove', name: 'hat_devices_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): RedirectResponse
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_DEVICE_REMOVE_PREFIX . $id, $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('hat_devices_index');
        }

        try {
            $device = $this->deviceService->getDeviceById($id);
            $linkedSchedulesCount = $device->getSchedules()->count();
        } catch (EntityNotFound $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('hat_devices_index');
        }

        try {
            $this->deviceService->removeDevice($id);

            $message = sprintf(
                "Device '%s' removed. Removed schedule links: %d.",
                $device->getName(),
                $linkedSchedulesCount
            );
            $this->addFlash('success', $message);
            $this->safeCreateWebLog(ActionLogAction::DEVICE_REMOVE, ActionLog::LEVEL_WARNING, $message);
        } catch (Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('hat_devices_index');
    }

    #[Route(path: '/{id}/start', name: 'hat_devices_start', methods: ['POST'])]
    public function start(int $id, Request $request): RedirectResponse
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_DEVICE_START_PREFIX . $id, $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('hat_devices_index');
        }

        $this->handleManualAction($id, 'start');

        return $this->redirectToRoute('hat_devices_index');
    }

    #[Route(path: '/{id}/stop', name: 'hat_devices_stop', methods: ['POST'])]
    public function stop(int $id, Request $request): RedirectResponse
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_DEVICE_STOP_PREFIX . $id, $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('hat_devices_index');
        }

        $this->handleManualAction($id, 'stop');

        return $this->redirectToRoute('hat_devices_index');
    }

    protected function handleManualAction(int $deviceId, string $action): void
    {
        $actionName = match ($action) {
            'start' => ActionLogAction::DEVICE_START,
            'stop' => ActionLogAction::DEVICE_STOP,
            default => null,
        };

        if ($actionName === null) {
            $message = sprintf("Unsupported device action '%s'.", $action);
            $this->addFlash('error', $message);

            return;
        }

        try {
            $device = $this->deviceService->getDeviceById($deviceId);
        } catch (EntityNotFound $exception) {
            $message = $exception->getMessage();
            $this->addFlash('error', $message);
            $this->safeCreateWebLog($actionName, ActionLog::LEVEL_ERROR, $message);

            return;
        }

        try {
            if ($action === 'start') {
                $result = $this->deviceOperationsService->startDevice($device->getName());
                $level = $result ? ActionLog::LEVEL_INFO : ActionLog::LEVEL_WARNING;
                $message = sprintf("Device '%s' started: %s.", $device->getName(), $result ? 'yes' : 'no');
            } else {
                $result = $this->deviceOperationsService->stopDevice($device->getName());
                $level = $result ? ActionLog::LEVEL_INFO : ActionLog::LEVEL_WARNING;
                $message = sprintf("Device '%s' stopped: %s.", $device->getName(), $result ? 'yes' : 'no');
            }

            $flashLevel = $level === ActionLog::LEVEL_WARNING ? 'warning' : 'success';
            $this->addFlash($flashLevel, $message);
            $this->safeCreateWebLog($actionName, $level, $message);
        } catch (Throwable $exception) {
            $message = sprintf("Device '%s': %s", $device->getName(), $exception->getMessage());
            $this->addFlash('error', $message);
            $this->safeCreateWebLog($actionName, ActionLog::LEVEL_ERROR, $message);
        }
    }

    protected function defaultFormData(): array
    {
        return [
            'name' => '',
            'ip' => '',
            'mac' => '',
            'platform' => DevicePlatform::GENERIC->value,
            'username' => '',
            'ups_id' => '',
            'threshold_minutes' => '',
        ];
    }

    protected function mapEntityToFormData(Device $device): array
    {
        $thresholdSeconds = $device->getUpsLowBatteryRuntimeThreshold();

        return [
            'name' => $device->getName(),
            'ip' => $device->getIp(),
            'mac' => $device->getMac(),
            'platform' => $device->getPlatform(),
            'username' => $device->getUsername() ?? '',
            'ups_id' => $device->getUps()?->getId() === null ? '' : (string)$device->getUps()->getId(),
            'threshold_minutes' => $thresholdSeconds === null ? '' : (string)max(0, (int)floor($thresholdSeconds / 60)),
        ];
    }

    protected function extractFormData(Request $request): array
    {
        $mac = str_replace('-', ':', trim((string)$request->request->get('mac', '')));
        $username = trim((string)$request->request->get('username', ''));

        return [
            'name' => trim((string)$request->request->get('name', '')),
            'ip' => trim((string)$request->request->get('ip', '')),
            'mac' => $mac,
            'platform' => trim((string)$request->request->get('platform', '')),
            'username' => $username === '' ? null : $username,
            'ups_id' => trim((string)$request->request->get('ups_id', '')),
            'threshold_minutes' => trim((string)$request->request->get('threshold_minutes', '')),
        ];
    }

    protected function validateFormData(array $formData, ?int $currentDeviceId): array
    {
        $errors = [];

        if ($formData['name'] === '') {
            $errors['name'][] = 'Device name is required.';
        }

        if ($formData['ip'] === '' || filter_var($formData['ip'], FILTER_VALIDATE_IP) === false) {
            $errors['ip'][] = 'A valid IP address is required.';
        }

        if ($formData['mac'] === '' || preg_match(self::MAC_PATTERN, $formData['mac']) !== 1) {
            $errors['mac'][] = 'A valid MAC address is required (format: 00:11:22:33:44:55).';
        }

        if (!in_array($formData['platform'], DevicePlatform::values(), true)) {
            $errors['platform'][] = sprintf('Platform must be one of: %s.', implode(', ', DevicePlatform::values()));
        }

        if ($formData['threshold_minutes'] !== '') {
            $thresholdMinutes = filter_var(
                $formData['threshold_minutes'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]]
            );
            if ($thresholdMinutes === false) {
                $errors['threshold_minutes'][] = 'Threshold must be a non-negative integer.';
            }
        }

        if ($formData['ups_id'] !== '') {
            $upsId = filter_var($formData['ups_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($upsId === false) {
                $errors['ups_id'][] = 'UPS selection is invalid.';
            } elseif ($this->upsRepository->findById((int)$upsId) === null) {
                $errors['ups_id'][] = 'Selected UPS entry does not exist.';
            }
        }

        if ($formData['name'] !== '') {
            $existingDevice = $this->deviceRepository->findOneByName($formData['name']);
            if ($existingDevice !== null && $existingDevice->getId() !== $currentDeviceId) {
                $errors['name'][] = sprintf("Device with name '%s' already exists.", $formData['name']);
            }
        }

        return $errors;
    }

    protected function resolveThresholdSeconds(string $thresholdMinutes): ?int
    {
        if ($thresholdMinutes === '') {
            return null;
        }

        $resolvedMinutes = (int)$thresholdMinutes;

        return $resolvedMinutes * 60;
    }

    protected function resolveUpsId(string $upsId): ?int
    {
        if ($upsId === '') {
            return null;
        }

        return (int)$upsId;
    }

    protected function resolvePlatformFilter(string $platform): ?string
    {
        if ($platform === '') {
            return null;
        }

        return in_array($platform, DevicePlatform::values(), true) ? $platform : null;
    }

    protected function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, self::ALLOWED_SORT_BY, true) ? $sortBy : self::SORT_BY_NETWORK;
    }

    protected function resolveSortDirection(string $sortDirection): string
    {
        return in_array($sortDirection, self::ALLOWED_SORT_DIRECTIONS, true)
            ? $sortDirection
            : self::SORT_DIRECTION_ASC;
    }

    protected function sortDevices(array &$devices, string $sortBy, string $sortDirection): void
    {
        usort(
            $devices,
            function (array $left, array $right) use ($sortBy, $sortDirection): int {
                $comparison = match ($sortBy) {
                    self::SORT_BY_NAME_ID => $this->compareDevicesByNameId($left, $right),
                    self::SORT_BY_PLATFORM => $this->compareDevicesByPlatform($left, $right),
                    self::SORT_BY_UPS => $this->compareDevicesByUps($left, $right),
                    default => $this->compareDevicesByNetwork($left, $right),
                };

                if ($comparison === 0 && $sortBy !== self::SORT_BY_NAME_ID) {
                    $comparison = $this->compareDevicesByNameId($left, $right);
                }

                if ($sortDirection === self::SORT_DIRECTION_DESC) {
                    return -$comparison;
                }

                return $comparison;
            }
        );
    }

    protected function compareDevicesByNameId(array $left, array $right): int
    {
        $leftId = $this->resolveDeviceIdForSorting($left);
        $rightId = $this->resolveDeviceIdForSorting($right);
        $idComparison = $leftId <=> $rightId;
        if ($idComparison !== 0) {
            return $idComparison;
        }

        return strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
    }

    protected function compareDevicesByNetwork(array $left, array $right): int
    {
        $leftIp = trim((string)($left['ip'] ?? ''));
        $rightIp = trim((string)($right['ip'] ?? ''));
        $leftIpBinary = inet_pton($leftIp);
        $rightIpBinary = inet_pton($rightIp);

        if ($leftIpBinary !== false && $rightIpBinary !== false) {
            $lengthComparison = strlen($leftIpBinary) <=> strlen($rightIpBinary);
            if ($lengthComparison !== 0) {
                return $lengthComparison;
            }

            $binaryComparison = strcmp($leftIpBinary, $rightIpBinary);
            if ($binaryComparison !== 0) {
                return $binaryComparison;
            }
        }

        return strcasecmp($leftIp, $rightIp);
    }

    protected function compareDevicesByPlatform(array $left, array $right): int
    {
        $leftPlatformKey = (string)($left['platform_key'] ?? '');
        $rightPlatformKey = (string)($right['platform_key'] ?? '');
        $platformComparison = strcasecmp($leftPlatformKey, $rightPlatformKey);
        if ($platformComparison !== 0) {
            return $platformComparison;
        }

        return strcasecmp((string)($left['platform'] ?? ''), (string)($right['platform'] ?? ''));
    }

    protected function compareDevicesByUps(array $left, array $right): int
    {
        $leftUps = $this->resolveUpsSortData((string)($left['ups'] ?? '-'));
        $rightUps = $this->resolveUpsSortData((string)($right['ups'] ?? '-'));

        $leftHasUps = $leftUps['has_ups'];
        $rightHasUps = $rightUps['has_ups'];
        if ($leftHasUps !== $rightHasUps) {
            return $leftHasUps ? -1 : 1;
        }

        $upsNameComparison = strcasecmp($leftUps['name'], $rightUps['name']);
        if ($upsNameComparison !== 0) {
            return $upsNameComparison;
        }

        return $leftUps['id'] <=> $rightUps['id'];
    }

    protected function resolveDeviceIdForSorting(array $device): int
    {
        $deviceId = filter_var(
            $device['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($deviceId === false) {
            return PHP_INT_MAX;
        }

        return (int)$deviceId;
    }

    protected function resolveUpsSortData(string $upsLink): array
    {
        $normalizedUpsLink = trim($upsLink);
        if ($normalizedUpsLink === '' || $normalizedUpsLink === '-') {
            return [
                'has_ups' => false,
                'id' => 0,
                'name' => '',
            ];
        }

        $parts = explode(':', $normalizedUpsLink, 2);
        $upsId = filter_var(
            $parts[0] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $upsName = $parts[1] ?? $normalizedUpsLink;

        return [
            'has_ups' => true,
            'id' => $upsId === false ? 0 : (int)$upsId,
            'name' => trim($upsName),
        ];
    }

    protected function safeCreateWebLog(string|ActionLogAction $action, string $level, string $message): void
    {
        try {
            $resolvedAction = $action instanceof ActionLogAction ? $action->value : $action;
            $this->actionLogService->createActionLog(ActionLog::SOURCE_WEB, $resolvedAction, $level, $message);
        } catch (Throwable) {
        }
    }
}
