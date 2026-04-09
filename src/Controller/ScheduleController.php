<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Controller;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Schedule;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Repository\ScheduleRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\ScheduleRuntimeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/schedules')]
class ScheduleController extends AbstractController
{
    protected const array ALLOWED_COMMANDS = [
        ScheduleInterface::COMMAND_START,
        ScheduleInterface::COMMAND_STOP,
    ];
    protected const string CSRF_SCHEDULE_FORM_CREATE = 'schedule.form.create';
    protected const string CSRF_SCHEDULE_FORM_EDIT_PREFIX = 'schedule.form.edit.';
    protected const string CSRF_SCHEDULE_REMOVE_PREFIX = 'schedule.remove.';

    public function __construct(
        protected ScheduleService $scheduleService,
        protected ScheduleRuntimeService $scheduleRuntimeService,
        protected DeviceService $deviceService,
        protected ScheduleRepository $scheduleRepository,
        protected ActionLogService $actionLogService,
        protected DeviceOperationsService $deviceOperationsService,
        protected Configuration $configuration
    ) {
    }

    #[Route(path: '', name: 'hat_schedules_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = ActionLogService::DEFAULT_LIST_LIMIT;

        $schedules = array_map(
            static fn ($runtimeSchedule): array => $runtimeSchedule->toArray(),
            $this->scheduleRuntimeService->listRuntimeSchedules()
        );
        $total = count($schedules);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $schedules = array_slice($schedules, $offset, $perPage);
        $nextRunsById = [];

        foreach ($schedules as $schedule) {
            $scheduleId = $schedule['id'] ?? null;
            if (!is_int($scheduleId)) {
                continue;
            }

            $cronExpression = (string)($schedule['cron_expression'] ?? '');
            $nextRunsById[$scheduleId] = $this->buildNextRuns($cronExpression);
        }

        return $this->render('schedules/index.html.twig', [
            'page_title' => 'Schedules',
            'schedules' => $schedules,
            'next_runs_by_id' => $nextRunsById,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'timezone_name' => $this->configuration->getTimezone(),
        ]);
    }

    #[Route(path: '/new', name: 'hat_schedules_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $formData = $this->defaultFormData();
        $errors = [];

        if ($request->isMethod(Request::METHOD_POST)) {
            $formData = $this->extractFormData($request);
            $csrfToken = (string)$request->request->get('_token', '');
            if (!$this->isCsrfTokenValid(self::CSRF_SCHEDULE_FORM_CREATE, $csrfToken)) {
                $errors['_global'][] = 'Invalid CSRF token.';
            } else {
                $errors = $this->validateFormData($formData, null);
            }

            if (empty($errors)) {
                try {
                    $schedule = $this->scheduleService->createSchedule(
                        $formData['name'],
                        $formData['cron_expression'],
                        $formData['command'],
                        $formData['device_ids'],
                        $formData['is_enabled']
                    );

                    $message = sprintf(
                        "Schedule '%s' created with ID %d.",
                        $schedule->getName(),
                        (int)$schedule->getId()
                    );
                    $this->addFlash('success', $message);
                    $this->safeCreateWebLog(ActionLogAction::SCHEDULE_CREATE, ActionLog::LEVEL_INFO, $message);

                    return $this->redirectToRoute('hat_schedules_index');
                } catch (Throwable $exception) {
                    $errors['_global'][] = $exception->getMessage();
                }
            }
        }

        return $this->render('schedules/form.html.twig', [
            'page_title' => 'New Schedule',
            'mode' => 'create',
            'form_data' => $formData,
            'errors' => $errors,
            'devices' => $this->deviceService->listDevices(),
            'allowed_commands' => self::ALLOWED_COMMANDS,
            'preview_next_runs' => $this->buildNextRuns($formData['cron_expression']),
            'timezone_name' => $this->configuration->getTimezone(),
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'hat_schedules_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        try {
            $schedule = $this->scheduleService->getScheduleById($id);
        } catch (EntityNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $formData = $this->mapEntityToFormData($schedule);
        $errors = [];

        if ($request->isMethod(Request::METHOD_POST)) {
            $formData = $this->extractFormData($request);
            $csrfToken = (string)$request->request->get('_token', '');
            if (!$this->isCsrfTokenValid(self::CSRF_SCHEDULE_FORM_EDIT_PREFIX . $id, $csrfToken)) {
                $errors['_global'][] = 'Invalid CSRF token.';
            } else {
                $errors = $this->validateFormData($formData, $id);
            }

            if (empty($errors)) {
                try {
                    $updatedSchedule = $this->scheduleService->updateSchedule(
                        $id,
                        $formData['name'],
                        $formData['is_enabled'],
                        $formData['cron_expression'],
                        $formData['command'],
                        $formData['device_ids']
                    );

                    $message = sprintf("Schedule '%s' updated.", $updatedSchedule->getName());
                    $this->addFlash('success', $message);
                    $this->safeCreateWebLog(ActionLogAction::SCHEDULE_UPDATE, ActionLog::LEVEL_INFO, $message);

                    return $this->redirectToRoute('hat_schedules_edit', ['id' => $id]);
                } catch (Throwable $exception) {
                    $errors['_global'][] = $exception->getMessage();
                }
            }
        }

        return $this->render('schedules/form.html.twig', [
            'page_title' => sprintf('Edit Schedule #%d', $id),
            'breadcrumbs' => [
                [
                    'label' => 'Schedules',
                    'href' => $this->generateUrl('hat_schedules_index'),
                ],
                [
                    'label' => sprintf('Edit Schedule #%d', $id),
                ],
            ],
            'mode' => 'edit',
            'schedule' => $schedule,
            'form_data' => $formData,
            'errors' => $errors,
            'devices' => $this->deviceService->listDevices(),
            'allowed_commands' => self::ALLOWED_COMMANDS,
            'preview_next_runs' => $this->buildNextRuns($formData['cron_expression']),
            'timezone_name' => $this->configuration->getTimezone(),
        ]);
    }

    #[Route(path: '/{id}/remove', name: 'hat_schedules_remove_confirm', methods: ['GET'])]
    public function removeConfirm(int $id): Response
    {
        try {
            $schedule = $this->scheduleService->getScheduleById($id);
        } catch (EntityNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $linkedDeviceNames = [];
        foreach ($schedule->getDevices()->toArray() as $device) {
            if ($device instanceof Device) {
                $linkedDeviceNames[] = $device->getName();
            }
        }

        return $this->render('schedules/delete.html.twig', [
            'page_title' => sprintf('Remove Schedule #%d', $id),
            'schedule' => $schedule,
            'linked_device_names' => $linkedDeviceNames,
        ]);
    }

    #[Route(path: '/{id}/remove', name: 'hat_schedules_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): RedirectResponse
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_SCHEDULE_REMOVE_PREFIX . $id, $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('hat_schedules_index');
        }

        try {
            $schedule = $this->scheduleService->getScheduleById($id);
            $linkedDevicesCount = $schedule->getDevices()->count();
        } catch (EntityNotFound $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('hat_schedules_index');
        }

        try {
            $this->scheduleService->removeSchedule($id);

            $message = sprintf(
                "Schedule '%s' removed. Detached devices: %d.",
                $schedule->getName(),
                $linkedDevicesCount
            );
            $this->addFlash('success', $message);
            $this->safeCreateWebLog(ActionLogAction::SCHEDULE_REMOVE, ActionLog::LEVEL_WARNING, $message);
        } catch (Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('hat_schedules_index');
    }

    #[Route(path: '/preview-next-runs', name: 'hat_schedules_preview_next_runs', methods: ['GET'])]
    public function previewNextRuns(Request $request): JsonResponse
    {
        $cronExpression = trim((string)$request->query->get('cron_expression', ''));
        if ($cronExpression === '' || !CronExpression::isValidExpression($cronExpression)) {
            return $this->json(['runs' => []]);
        }

        $runs = $this->buildNextRuns($cronExpression);
        $formattedRuns = array_map(
            fn (DateTimeImmutable $dateTime): string => $dateTime->format('Y-m-d H:i:s'),
            $runs
        );

        return $this->json(['runs' => $formattedRuns]);
    }

    protected function defaultFormData(): array
    {
        return [
            'name' => '',
            'cron_expression' => '* * * * *',
            'command' => ScheduleInterface::COMMAND_START,
            'is_enabled' => true,
            'device_ids' => [],
        ];
    }

    protected function mapEntityToFormData(Schedule $schedule): array
    {
        $deviceIds = [];
        foreach ($schedule->getDevices()->toArray() as $device) {
            if (!$device instanceof Device || $device->getId() === null) {
                continue;
            }

            $deviceIds[] = (int)$device->getId();
        }

        return [
            'name' => $schedule->getName(),
            'cron_expression' => $schedule->getCronExpression(),
            'command' => $schedule->getCommand(),
            'is_enabled' => $schedule->isEnabled(),
            'device_ids' => array_values(array_unique($deviceIds)),
        ];
    }

    protected function extractFormData(Request $request): array
    {
        $rawDeviceIds = $request->request->all('device_ids');
        $resolvedDeviceIds = [];
        foreach ($rawDeviceIds as $rawDeviceId) {
            $deviceId = filter_var($rawDeviceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($deviceId === false) {
                continue;
            }

            $resolvedDeviceIds[] = (int)$deviceId;
        }

        return [
            'name' => trim((string)$request->request->get('name', '')),
            'cron_expression' => trim((string)$request->request->get('cron_expression', '')),
            'command' => trim((string)$request->request->get('command', '')),
            'is_enabled' => (string)$request->request->get('is_enabled', '0') === '1',
            'device_ids' => array_values(array_unique($resolvedDeviceIds)),
        ];
    }

    protected function validateFormData(array $formData, ?int $currentScheduleId): array
    {
        $errors = [];

        if ($formData['name'] === '') {
            $errors['name'][] = 'Schedule name is required.';
        }

        if (!CronExpression::isValidExpression($formData['cron_expression'])) {
            $errors['cron_expression'][] = 'Cron expression is invalid.';
        }

        if (!in_array($formData['command'], self::ALLOWED_COMMANDS, true)) {
            $errors['command'][] = sprintf('Command must be one of: %s.', implode(', ', self::ALLOWED_COMMANDS));
        }

        $availableDeviceIds = [];
        foreach ($this->deviceService->listDevices() as $device) {
            $deviceId = $device->getId();
            if ($deviceId === null) {
                continue;
            }

            $availableDeviceIds[] = (int)$deviceId;
        }

        foreach ($formData['device_ids'] as $deviceId) {
            if (!in_array($deviceId, $availableDeviceIds, true)) {
                $errors['device_ids'][] = sprintf('Selected device ID %d does not exist.', $deviceId);
            }
        }

        if ($formData['name'] !== '') {
            $existingSchedule = $this->scheduleRepository->findOneByName($formData['name']);
            if ($existingSchedule !== null && $existingSchedule->getId() !== $currentScheduleId) {
                $errors['name'][] = sprintf("Schedule with name '%s' already exists.", $formData['name']);
            }
        }

        return $errors;
    }

    protected function buildNextRuns(string $cronExpression, int $count = 3): array
    {
        if (!CronExpression::isValidExpression($cronExpression)) {
            return [];
        }

        try {
            $timezone = new DateTimeZone($this->configuration->getTimezone());
            $cronExpressionParser = new CronExpression($cronExpression);
            $cursor = new DateTimeImmutable('now', $timezone);

            $nextRuns = [];
            for ($i = 0; $i < $count; $i++) {
                $nextRun = $cronExpressionParser->getNextRunDate($cursor, 0, $i === 0, $timezone->getName());
                $nextRunDateTime = DateTimeImmutable::createFromInterface($nextRun);
                $nextRuns[] = $nextRunDateTime;
                $cursor = $nextRunDateTime->modify('+1 second');
            }
        } catch (Throwable) {
            return [];
        }

        return $nextRuns;
    }

    protected function resolveStatusByDeviceName(): array
    {
        $statusByName = [];

        try {
            $runtimeDevices = $this->deviceOperationsService->listDevices(true);
            foreach ($runtimeDevices as $runtimeDevice) {
                $runtimeData = $runtimeDevice->toArray();
                $statusByName[$runtimeDevice->getName()] = $runtimeData['status'] ?? 'unknown';
            }
        } catch (Throwable) {
            return [];
        }

        return $statusByName;
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
