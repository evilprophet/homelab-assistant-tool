<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use DateTimeImmutable;
use DateTimeZone;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class LogsControllerFunctionalTest extends HttpFunctionalTestCase
{
    public function testIndexAppliesSourceLevelActionAndEntityFilters(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/logs');

        $actionLogService = static::getContainer()->get(ActionLogService::class);
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'auth.callback',
            ActionLog::LEVEL_ERROR,
            'oidc-error-target-node-1'
        );
        $actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            'cron.execute',
            ActionLog::LEVEL_INFO,
            'cron-info-should-not-be-visible'
        );

        $response = $this->request(
            'GET',
            '/logs?source=WEB&level=error&action=auth.callback&entity=node-1'
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('oidc-error-target-node-1', (string)$response->getContent());
        $this->assertStringNotContainsString('cron-info-should-not-be-visible', (string)$response->getContent());
    }

    public function testCleanupRemovesOnlySelectedLevel(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/logs');

        $actionLogService = static::getContainer()->get(ActionLogService::class);
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'logs.warning',
            ActionLog::LEVEL_WARNING,
            'warning-log-to-delete-unique'
        );
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'logs.info',
            ActionLog::LEVEL_INFO,
            'info-log-to-keep-unique'
        );

        $cleanupResponse = $this->request('POST', '/logs/cleanup', [
            'retention' => '0',
            'level' => ActionLog::LEVEL_WARNING,
        ]);

        $this->assertSame(Response::HTTP_FOUND, $cleanupResponse->getStatusCode());
        $this->assertSame('/logs', $cleanupResponse->headers->get('Location'));

        $logsResponse = $this->request('GET', '/logs');
        $this->assertSame(Response::HTTP_OK, $logsResponse->getStatusCode());
        $this->assertStringNotContainsString('warning-log-to-delete-unique', (string)$logsResponse->getContent());
        $this->assertStringContainsString('info-log-to-keep-unique', (string)$logsResponse->getContent());
    }

    public function testIndexShowsErrorsForInvalidDateFilters(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/logs');

        $response = $this->request('GET', '/logs?from=invalid-date&to=2026-02-20');
        $content = html_entity_decode((string)$response->getContent(), ENT_QUOTES);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString(
            'Invalid date format for "from". Use YYYY-MM-DD.',
            $content
        );
    }

    public function testIndexNormalizesOutOfRangePageToLastPage(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/logs');

        $actionLogService = static::getContainer()->get(ActionLogService::class);
        for ($index = 1; $index <= 30; $index++) {
            $actionLogService->createActionLog(
                ActionLog::SOURCE_WEB,
                'logs.page-test',
                ActionLog::LEVEL_INFO,
                sprintf('pagination-log-%02d', $index)
            );
        }

        $response = $this->request('GET', '/logs?page=999');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Showing page 2 / 2 (31 entries)', (string)$response->getContent());
        $this->assertStringContainsString('pagination-log-01', (string)$response->getContent());
    }

    public function testCleanupShowsErrorForInvalidRetentionAndKeepsLogs(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/logs');

        $actionLogService = static::getContainer()->get(ActionLogService::class);
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'logs.invalid-retention',
            ActionLog::LEVEL_WARNING,
            'log-should-stay-after-invalid-cleanup'
        );

        $cleanupResponse = $this->request('POST', '/logs/cleanup', [
            'retention' => 'bad-value',
            'level' => 'all',
        ]);
        $this->assertSame(Response::HTTP_FOUND, $cleanupResponse->getStatusCode());
        $this->assertSame('/logs', $cleanupResponse->headers->get('Location'));

        $logsResponse = $this->request('GET', '/logs');
        $this->assertSame(Response::HTTP_OK, $logsResponse->getStatusCode());
        $this->assertStringContainsString(
            'Retention must be a positive integer or 0 for all logs.',
            (string)$logsResponse->getContent()
        );
        $this->assertStringContainsString('log-should-stay-after-invalid-cleanup', (string)$logsResponse->getContent());
    }

    public function testCleanupShowsErrorForInvalidLevelFilterAndKeepsLogs(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/logs');

        $actionLogService = static::getContainer()->get(ActionLogService::class);
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'logs.invalid-level',
            ActionLog::LEVEL_INFO,
            'log-should-stay-after-invalid-level'
        );

        $cleanupResponse = $this->request('POST', '/logs/cleanup', [
            'retention' => '30',
            'level' => 'invalid-level',
        ]);
        $this->assertSame(Response::HTTP_FOUND, $cleanupResponse->getStatusCode());
        $this->assertSame('/logs', $cleanupResponse->headers->get('Location'));

        $logsResponse = $this->request('GET', '/logs');
        $this->assertSame(Response::HTTP_OK, $logsResponse->getStatusCode());
        $content = (string)$logsResponse->getContent();
        $this->assertStringContainsString('Cleanup level filter is invalid.', $content);
        $this->assertStringContainsString('log-should-stay-after-invalid-level', $content);
    }

    public function testCleanupOlderThanDaysWithLevelRemovesOnlyMatchingLevelAndAge(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/logs');

        $actionLogService = static::getContainer()->get(ActionLogService::class);
        $utc = new DateTimeZone('UTC');
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'logs.cleanup.warning.old',
            ActionLog::LEVEL_WARNING,
            'old-warning-to-delete',
            new DateTimeImmutable('-40 days', $utc)
        );
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'logs.cleanup.info.old',
            ActionLog::LEVEL_INFO,
            'old-info-should-stay',
            new DateTimeImmutable('-40 days', $utc)
        );
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'logs.cleanup.warning.recent',
            ActionLog::LEVEL_WARNING,
            'recent-warning-should-stay',
            new DateTimeImmutable('-5 days', $utc)
        );

        $cleanupResponse = $this->request('POST', '/logs/cleanup', [
            'retention' => '30',
            'level' => ActionLog::LEVEL_WARNING,
        ]);
        $this->assertSame(Response::HTTP_FOUND, $cleanupResponse->getStatusCode());
        $this->assertSame('/logs', $cleanupResponse->headers->get('Location'));

        $logsResponse = $this->request('GET', '/logs');
        $this->assertSame(Response::HTTP_OK, $logsResponse->getStatusCode());
        $content = (string)$logsResponse->getContent();
        $this->assertStringNotContainsString('old-warning-to-delete', $content);
        $this->assertStringContainsString('old-info-should-stay', $content);
        $this->assertStringContainsString('recent-warning-should-stay', $content);
    }
}
