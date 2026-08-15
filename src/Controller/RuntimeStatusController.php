<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Controller;

use EvilStudio\HAT\Service\Runtime\RuntimeStatusResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/runtime')]
class RuntimeStatusController extends AbstractController
{
    protected const int RESPONSE_CACHE_TTL_SECONDS = 60;

    public function __construct(
        protected RuntimeStatusResolver $runtimeStatusResolver
    ) {
    }

    #[Route(path: '/statuses', name: 'hat_runtime_statuses', methods: ['GET'])]
    public function statuses(Request $request): JsonResponse
    {
        $deviceNames = $request->query->all('device_names');
        $upsIdentifiers = $request->query->all('ups_identifiers');

        $response = $this->json([
            'device_status_by_name' => $this->runtimeStatusResolver->resolveDeviceStatusByNames($deviceNames),
            'ups_status_by_identifier' => $this->runtimeStatusResolver->resolveUpsStatusByIdentifiers($upsIdentifiers),
        ]);

        $response->setPrivate();
        $response->setMaxAge(self::RESPONSE_CACHE_TTL_SECONDS);

        return $response;
    }
}
