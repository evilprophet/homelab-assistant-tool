<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Controller;

use DateTimeImmutable;
use DateTimeZone;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use InvalidArgumentException;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/logs')]
class LogsController extends AbstractController
{
    protected const string CSRF_LOGS_CLEANUP = 'logs.cleanup';

    public function __construct(
        protected ActionLogService $actionLogService,
        protected Configuration $configuration
    ) {
    }

    #[Route(path: '', name: 'hat_logs_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $timezone = new DateTimeZone($this->configuration->getTimezone());
        $query = $request->query;

        $filters = [
            'source' => trim((string)$query->get('source', '')),
            'level' => trim((string)$query->get('level', '')),
            'action' => trim((string)$query->get('action', '')),
            'entity' => trim((string)$query->get('entity', '')),
            'from' => trim((string)$query->get('from', '')),
            'to' => trim((string)$query->get('to', '')),
        ];

        $errors = [];
        $source = $this->resolveSourceFilter($filters['source'], $errors);
        $level = $this->resolveLevelFilter($filters['level'], $errors);
        $action = $filters['action'] === '' ? null : $filters['action'];
        $entityText = $filters['entity'] === '' ? null : $filters['entity'];
        $fromDateUtc = $this->resolveDateFilter($filters['from'], false, $timezone, 'from', $errors);
        $toDateUtc = $this->resolveDateFilter($filters['to'], true, $timezone, 'to', $errors);

        if ($fromDateUtc !== null && $toDateUtc !== null && $fromDateUtc > $toDateUtc) {
            $errors[] = 'Date range is invalid: "from" date must be before or equal to "to" date.';
        }

        $page = max(1, (int)$query->get('page', 1));
        $perPage = ActionLogService::DEFAULT_LIST_LIMIT;

        $logs = [];
        $total = 0;
        $totalPages = 1;

        try {
            $result = $this->actionLogService->listActionLogsPaginated(
                $source,
                $level,
                $action,
                $fromDateUtc,
                $toDateUtc,
                $entityText,
                $page,
                $perPage
            );
            $logs = $result['items'];
            $total = $result['total'];
            $totalPages = max(1, (int)ceil($total / $perPage));

            if ($page > $totalPages) {
                $page = $totalPages;
                $result = $this->actionLogService->listActionLogsPaginated(
                    $source,
                    $level,
                    $action,
                    $fromDateUtc,
                    $toDateUtc,
                    $entityText,
                    $page,
                    $perPage
                );
                $logs = $result['items'];
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        return $this->render('logs/index.html.twig', [
            'page_title' => 'Action Logs',
            'logs' => $logs,
            'filters' => $filters,
            'errors' => $errors,
            'allowed_sources' => ActionLogService::getAllowedSources(),
            'allowed_levels' => ActionLogService::getAllowedLevels(),
            'available_actions' => ActionLogAction::values(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'timezone_name' => $timezone->getName(),
        ]);
    }

    #[Route(path: '/cleanup', name: 'hat_logs_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): Response
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_LOGS_CLEANUP, $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            $this->safeCreateWebLog(
                ActionLogAction::LOGS_CLEANUP,
                ActionLog::LEVEL_WARNING,
                'Cleanup blocked: invalid CSRF token.'
            );

            return $this->redirectToRoute('hat_logs_index');
        }

        $retention = trim(
            (string)$request->request->get('retention', (string)$this->configuration->getActionLogRetentionDays())
        );
        $levelFilterRaw = trim((string)$request->request->get('level', 'all'));
        $isAll = $retention === '0' || mb_strtolower($retention) === 'all';
        $levelFilter = null;
        $levelFilterError = null;

        if ($levelFilterRaw !== '' && mb_strtolower($levelFilterRaw) !== 'all') {
            if (!in_array($levelFilterRaw, ActionLogService::getAllowedLevels(), true)) {
                $levelFilterError = 'Cleanup level filter is invalid.';
            }

            if ($levelFilterError === null) {
                $levelFilter = $levelFilterRaw;
            }
        }

        $levelSuffix = $levelFilter === null ? '' : sprintf(' for level "%s"', $levelFilter);

        try {
            if ($levelFilterError !== null) {
                throw new InvalidArgumentException($levelFilterError);
            }

            if ($isAll) {
                $removed = $this->actionLogService->cleanupAll($levelFilter);
                $message = sprintf('Removed all action logs%s (%d rows).', $levelSuffix, $removed);
            } else {
                $days = filter_var($retention, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($days === false) {
                    throw new InvalidArgumentException('Retention must be a positive integer or 0 for all logs.');
                }

                $removed = $this->actionLogService->cleanupOlderThanDays((int)$days, $levelFilter);
                $message = sprintf('Removed %d action logs older than %d days%s.', $removed, (int)$days, $levelSuffix);
            }

            $this->addFlash('success', $message);
            $this->safeCreateWebLog(ActionLogAction::LOGS_CLEANUP, ActionLog::LEVEL_WARNING, $message);
        } catch (Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
            $this->safeCreateWebLog(ActionLogAction::LOGS_CLEANUP, ActionLog::LEVEL_ERROR, $exception->getMessage());
        }

        return $this->redirectToRoute('hat_logs_index');
    }

    protected function resolveSourceFilter(string $value, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }

        if (in_array($value, ActionLogService::getAllowedSources(), true)) {
            return $value;
        }

        $errors[] = sprintf('Unknown source filter: %s.', $value);

        return null;
    }

    protected function resolveLevelFilter(string $value, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }

        if (in_array($value, ActionLogService::getAllowedLevels(), true)) {
            return $value;
        }

        $errors[] = sprintf('Unknown level filter: %s.', $value);

        return null;
    }

    protected function resolveDateFilter(
        string $date,
        bool $isEndOfDay,
        DateTimeZone $timezone,
        string $fieldName,
        array &$errors
    ): ?DateTimeImmutable {
        if ($date === '') {
            return null;
        }

        $dateTimeString = $isEndOfDay ? sprintf('%s 23:59:59', $date) : sprintf('%s 00:00:00', $date);
        $localDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTimeString, $timezone);
        if ($localDateTime === false) {
            $errors[] = sprintf('Invalid date format for "%s". Use YYYY-MM-DD.', $fieldName);

            return null;
        }

        return $localDateTime->setTimezone(new DateTimeZone('UTC'));
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
