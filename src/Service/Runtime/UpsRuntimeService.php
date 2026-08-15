<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Factory\RuntimeUpsFactory;
use EvilStudio\HAT\Service\Application\UpsService;
use Throwable;

class UpsRuntimeService
{
    public function __construct(
        protected UpsService $upsService,
        protected RuntimeUpsFactory $runtimeUpsFactory
    ) {
    }

    public function listRuntimeUps(): array
    {
        $runtimeUpsList = [];
        foreach ($this->upsService->listUps() as $upsEntity) {
            $runtimeUps = $this->runtimeUpsFactory->createFromEntity($upsEntity);
            $runtimeUpsList[$runtimeUps->getIdentifier()] = $runtimeUps;
        }

        return $runtimeUpsList;
    }

    public function getRuntimeUpsByIdentifier(string $identifier): UpsInterface
    {
        $upsEntity = $this->upsService->getUpsByIdentifier($identifier);

        return $this->runtimeUpsFactory->createFromEntity($upsEntity);
    }

    public function pollAllUpsStatus(): array
    {
        $upsByIdentifier = [];
        foreach ($this->listRuntimeUps() as $identifier => $ups) {
            try {
                $ups->updateStatus();
                $upsByIdentifier[$identifier] = $ups;
            } catch (Throwable) {
                $upsByIdentifier[$identifier] = null;
            }
        }

        return $upsByIdentifier;
    }
}
