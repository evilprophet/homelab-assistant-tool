<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Controller;

use Cron\CronExpression;
use DateTimeImmutable;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\ScheduleRuntimeService;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class HomeController extends AbstractController
{
    public function __construct(
        protected DeviceOperationsService $deviceOperationsService,
        protected UpsRuntimeService $upsRuntimeService,
        protected ScheduleRuntimeService $scheduleRuntimeService,
        protected ActionLogService $actionLogService,
        protected Configuration $configuration
    ) {
    }

    #[Route(path: '/', name: 'hat_home', methods: ['GET'])]
    #[Route(path: '/', name: 'hat_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        $devices = array_map(
            static fn ($runtimeDevice): array => $runtimeDevice->toArray(),
            $this->deviceOperationsService->listDevices(false)
        );

        $upsCollection = array_map(
            static fn ($runtimeUps): array => $runtimeUps->toArray(),
            $this->upsRuntimeService->listRuntimeUps()
        );

        $schedules = array_map(
            static fn ($runtimeSchedule): array => $runtimeSchedule->toArray(),
            $this->scheduleRuntimeService->listRuntimeSchedules()
        );
        $activeSchedules = array_values(array_filter(
            $schedules,
            static fn (array $schedule): bool => ($schedule['enabled'] ?? 'no') === 'yes'
        ));

        $nextRunByScheduleId = $this->resolveNextRunByScheduleId($activeSchedules);

        $recentLogsData = $this->actionLogService->listActionLogsPaginated(
            null,
            null,
            null,
            null,
            null,
            null,
            1,
            10
        );
        $recentLogs = $recentLogsData['items'];

        return $this->render('dashboard/index.html.twig', [
            'page_title' => 'Dashboard',
            'stats' => [
                'devices' => count($devices),
                'ups' => count($upsCollection),
                'active_schedules' => count($activeSchedules),
            ],
            'devices' => $devices,
            'ups_collection' => $upsCollection,
            'schedules' => $activeSchedules,
            'recent_logs' => $recentLogs,
            'status_by_name' => [],
            'next_run_by_schedule_id' => $nextRunByScheduleId,
            'timezone_name' => $this->configuration->getResolvedTimezone()->getName(),
        ]);
    }

    protected function resolveNextRunByScheduleId(array $schedules): array
    {
        $nextRunByScheduleId = [];

        $timezone = $this->configuration->getResolvedTimezone();
        $cursor = new DateTimeImmutable('now', $timezone);
        foreach ($schedules as $schedule) {
            $scheduleId = $schedule['id'] ?? null;
            if (!is_int($scheduleId)) {
                continue;
            }

            $cronExpressionValue = (string)($schedule['cron_expression'] ?? '');
            if (!CronExpression::isValidExpression($cronExpressionValue)) {
                continue;
            }

            try {
                $cronExpression = new CronExpression($cronExpressionValue);
                $nextRun = $cronExpression->getNextRunDate($cursor, 0, true, $timezone->getName());
                $nextRunByScheduleId[$scheduleId] = DateTimeImmutable::createFromInterface($nextRun);
            } catch (Throwable) {
                continue;
            }
        }

        return $nextRunByScheduleId;
    }
}
