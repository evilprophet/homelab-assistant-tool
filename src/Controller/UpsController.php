<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Controller;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Ups;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ups')]
class UpsController extends AbstractController
{
    protected const string CSRF_UPS_FORM_CREATE = 'ups.form.create';
    protected const string CSRF_UPS_FORM_EDIT_PREFIX = 'ups.form.edit.';
    protected const string CSRF_UPS_REMOVE_PREFIX = 'ups.remove.';

    public function __construct(
        protected UpsService $upsService,
        protected UpsRuntimeService $upsRuntimeService,
        protected UpsRepository $upsRepository,
        protected ActionLogService $actionLogService
    ) {
    }

    #[Route(path: '', name: 'hat_ups_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = ActionLogService::DEFAULT_LIST_LIMIT;

        $upsCollection = [];
        foreach ($this->upsRuntimeService->listRuntimeUps() as $runtimeUps) {
            try {
                $runtimeUps->updateStatus();
            } catch (Throwable) {
            }

            $upsCollection[] = $runtimeUps->toArray();
        }
        $total = count($upsCollection);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $upsCollection = array_slice($upsCollection, $offset, $perPage);

        return $this->render('ups/index.html.twig', [
            'page_title' => 'UPS Units',
            'ups_collection' => $upsCollection,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route(path: '/new', name: 'hat_ups_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $formData = $this->defaultFormData();
        $errors = [];

        if ($request->isMethod(Request::METHOD_POST)) {
            $formData = $this->extractFormData($request);
            $csrfToken = (string)$request->request->get('_token', '');
            if (!$this->isCsrfTokenValid(self::CSRF_UPS_FORM_CREATE, $csrfToken)) {
                $errors['_global'][] = 'Invalid CSRF token.';
            } else {
                $errors = $this->validateFormData($formData, null);
            }

            if (empty($errors)) {
                try {
                    $ups = $this->upsService->createUps(
                        $formData['name'],
                        $formData['identifier'],
                        $formData['host'],
                        $this->resolveThresholdSeconds($formData['safe_threshold_minutes'])
                    );

                    $message = sprintf("UPS '%s' created with ID %d.", $ups->getIdentifier(), (int)$ups->getId());
                    $this->addFlash('success', $message);
                    $this->safeCreateWebLog(ActionLogAction::UPS_CREATE, ActionLog::LEVEL_INFO, $message);

                    return $this->redirectToRoute('hat_ups_index');
                } catch (Throwable $exception) {
                    $errors['_global'][] = $exception->getMessage();
                }
            }
        }

        return $this->render('ups/form.html.twig', [
            'page_title' => 'New UPS',
            'mode' => 'create',
            'form_data' => $formData,
            'errors' => $errors,
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'hat_ups_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        try {
            $ups = $this->upsService->getUpsById($id);
        } catch (EntityNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $formData = $this->mapEntityToFormData($ups);
        $errors = [];

        if ($request->isMethod(Request::METHOD_POST)) {
            $formData = $this->extractFormData($request);
            $csrfToken = (string)$request->request->get('_token', '');
            if (!$this->isCsrfTokenValid(self::CSRF_UPS_FORM_EDIT_PREFIX . $id, $csrfToken)) {
                $errors['_global'][] = 'Invalid CSRF token.';
            } else {
                $errors = $this->validateFormData($formData, $id);
            }

            if (empty($errors)) {
                try {
                    $updatedUps = $this->upsService->updateUps(
                        $id,
                        $formData['name'],
                        $formData['identifier'],
                        $formData['host'],
                        $this->resolveThresholdSeconds($formData['safe_threshold_minutes'])
                    );

                    $message = sprintf("UPS '%s' updated.", $updatedUps->getIdentifier());
                    $this->addFlash('success', $message);
                    $this->safeCreateWebLog(ActionLogAction::UPS_UPDATE, ActionLog::LEVEL_INFO, $message);

                    return $this->redirectToRoute('hat_ups_edit', ['id' => $id]);
                } catch (Throwable $exception) {
                    $errors['_global'][] = $exception->getMessage();
                }
            }
        }

        return $this->render('ups/form.html.twig', [
            'page_title' => sprintf('Edit UPS #%d', $id),
            'breadcrumbs' => [
                [
                    'label' => 'UPS Units',
                    'href' => $this->generateUrl('hat_ups_index'),
                ],
                [
                    'label' => sprintf('Edit UPS #%d', $id),
                ],
            ],
            'mode' => 'edit',
            'ups' => $ups,
            'form_data' => $formData,
            'errors' => $errors,
        ]);
    }

    #[Route(path: '/{id}/remove', name: 'hat_ups_remove_confirm', methods: ['GET'])]
    public function removeConfirm(int $id): Response
    {
        try {
            $ups = $this->upsService->getUpsById($id);
        } catch (EntityNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $linkedDeviceNames = [];
        foreach ($ups->getDevices()->toArray() as $device) {
            if ($device instanceof Device) {
                $linkedDeviceNames[] = $device->getName();
            }
        }

        return $this->render('ups/delete.html.twig', [
            'page_title' => sprintf('Remove UPS #%d', $id),
            'ups' => $ups,
            'linked_device_names' => $linkedDeviceNames,
        ]);
    }

    #[Route(path: '/{id}/remove', name: 'hat_ups_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): RedirectResponse
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_UPS_REMOVE_PREFIX . $id, $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('hat_ups_index');
        }

        try {
            $ups = $this->upsService->getUpsById($id);
            $linkedDevicesCount = $ups->getDevices()->count();
        } catch (EntityNotFound $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('hat_ups_index');
        }

        try {
            $this->upsService->removeUps($id);

            $message = sprintf("UPS '%s' removed. Detached devices: %d.", $ups->getIdentifier(), $linkedDevicesCount);
            $this->addFlash('success', $message);
            $this->safeCreateWebLog(ActionLogAction::UPS_REMOVE, ActionLog::LEVEL_WARNING, $message);
        } catch (Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('hat_ups_index');
    }

    protected function defaultFormData(): array
    {
        return [
            'name' => '',
            'identifier' => '',
            'host' => '',
            'safe_threshold_minutes' => '',
        ];
    }

    protected function mapEntityToFormData(Ups $ups): array
    {
        $thresholdSeconds = $ups->getSafeBatteryRuntimeThreshold();

        $safeThresholdMinutes = $thresholdSeconds !== null ? (string)max(0, (int)floor($thresholdSeconds / 60)) : '';

        return [
            'name' => $ups->getName(),
            'identifier' => $ups->getIdentifier(),
            'host' => $ups->getHost(),
            'safe_threshold_minutes' => $safeThresholdMinutes,
        ];
    }

    protected function extractFormData(Request $request): array
    {
        return [
            'name' => trim((string)$request->request->get('name', '')),
            'identifier' => trim((string)$request->request->get('identifier', '')),
            'host' => trim((string)$request->request->get('host', '')),
            'safe_threshold_minutes' => trim((string)$request->request->get('safe_threshold_minutes', '')),
        ];
    }

    protected function validateFormData(array $formData, ?int $currentUpsId): array
    {
        $errors = [];

        if ($formData['name'] === '') {
            $errors['name'][] = 'UPS name is required.';
        }

        if ($formData['identifier'] === '') {
            $errors['identifier'][] = 'UPS identifier is required.';
        }

        if ($formData['host'] === '') {
            $errors['host'][] = 'UPS host is required.';
        }

        if ($formData['safe_threshold_minutes'] !== '') {
            $thresholdMinutes = filter_var(
                $formData['safe_threshold_minutes'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]]
            );
            if ($thresholdMinutes === false) {
                $errors['safe_threshold_minutes'][] = 'Safe threshold must be a non-negative integer.';
            }
        }

        if ($formData['identifier'] !== '') {
            $existingUps = $this->upsRepository->findOneByIdentifier($formData['identifier']);
            if ($existingUps !== null && $existingUps->getId() !== $currentUpsId) {
                $errors['identifier'][] = sprintf("UPS with identifier '%s' already exists.", $formData['identifier']);
            }
        }

        return $errors;
    }

    protected function resolveThresholdSeconds(string $thresholdMinutes): ?int
    {
        if ($thresholdMinutes === '') {
            return null;
        }

        return ((int)$thresholdMinutes) * 60;
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
