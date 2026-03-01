<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Controller;

use EvilStudio\HAT\Controller\AuthController;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Service\Auth\JwtTokenService;
use EvilStudio\HAT\Service\Auth\OidcClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthControllerTest extends TestCase
{
    public function testNormalizeNextPathReturnsValidRelativePath(): void
    {
        $controller = $this->createTestableController();

        $result = $controller->callNormalizeNextPath('/devices');

        $this->assertSame('/devices', $result);
    }

    public function testNormalizeNextPathFallsBackToDashboardForInvalidValue(): void
    {
        $controller = $this->createTestableController();

        $this->assertSame('/dashboard', $controller->callNormalizeNextPath(''));
        $this->assertSame('/dashboard', $controller->callNormalizeNextPath('//evil'));
        $this->assertSame('/dashboard', $controller->callNormalizeNextPath('http://external'));
    }

    public function testSafeCreateWebLogSwallowsLoggingExceptions(): void
    {
        $actionLogService = $this->createMock(ActionLogService::class);
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(ActionLog::SOURCE_WEB, 'auth.login', ActionLog::LEVEL_ERROR, 'failure')
            ->willThrowException(new \RuntimeException('write failed'));

        $controller = $this->createTestableController($actionLogService);
        $controller->callSafeCreateWebLog('auth.login', ActionLog::LEVEL_ERROR, 'failure');

        $this->addToAssertionCount(1);
    }

    protected function createTestableController(?ActionLogService $actionLogService = null): object
    {
        return new class (
            $this->createMock(AuthModeResolver::class),
            $this->createMock(AuthUserService::class),
            $this->createMock(JwtTokenService::class),
            $this->createMock(OidcClient::class),
            $actionLogService ?? $this->createMock(ActionLogService::class)
        ) extends AuthController {
            public function callNormalizeNextPath(string $candidate): string
            {
                return $this->normalizeNextPath($candidate);
            }

            public function callSafeCreateWebLog(string $action, string $level, string $message): void
            {
                $this->safeCreateWebLog($action, $level, $message);
            }

            public function generateUrl(
                string $route,
                array $parameters = [],
                int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
            ): string {
                return '/dashboard';
            }
        };
    }
}
