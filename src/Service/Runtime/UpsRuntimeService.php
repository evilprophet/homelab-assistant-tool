<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Factory\RuntimeUpsFactory;
use EvilStudio\HAT\Service\Application\UpsService;

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

    public function updateAllUpsStatus(): void
    {
        foreach ($this->listRuntimeUps() as $ups) {
            $ups->updateStatus();
        }
    }

    public function isAnyUpsOnBattery(): bool
    {
        foreach ($this->listRuntimeUps() as $ups) {
            $ups->updateStatus();

            if ($ups->isOnBattery()) {
                return true;
            }
        }

        return false;
    }
}
