<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Provider;

use EvilStudio\HAT\Api\UpsInterface;
use EvilStudio\HAT\Exception\MissingUps;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Ups;

class UpsProvider extends AbstractProvider
{
    protected array $properties = ['Name', 'Model Name', 'Serial Number', 'Status', 'Battery'];
    protected array $upsList = [];

    public function __construct(
        Configuration $configuration,
        array $upsData
    ) {
        parent::__construct($configuration);

        if (empty($upsData)) {
            return;
        }

        foreach ($upsData as $ups) {
            $ups = new Ups(
                $ups['name'],
                $ups['identifier'],
                $ups['host']
            );

            $this->upsList[$ups->getIdentifier()] = $ups;
        }
    }

    public function getUpsList(): array
    {
        return $this->upsList;
    }

    public function getUps(string $upsName): UpsInterface
    {
        if (!array_key_exists($upsName, $this->upsList)) {
            throw new MissingUps(sprintf("UPS with ups_name '%s' not found.", $upsName));
        }

        return $this->upsList[$upsName];
    }

    public function updateAllUpsStatus(): void
    {
        foreach ($this->getUpsList() as $ups) {
            $ups->updateStatus();
        }
    }
}
