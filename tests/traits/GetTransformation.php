<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Traits;

use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Configuration\Config;

trait GetTransformation
{
    protected Components $componentsClient;

    public function getTransformation(string $id): array
    {
        return $this->componentsClient->getConfiguration('transformation', $id);
    }

    public function getTransformationV2(array $item): array
    {
        return $this->componentsClient->getConfiguration($item['componentId'], $item['id']);
    }
}
