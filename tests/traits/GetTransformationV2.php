<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Traits;

use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Configuration\Config;

trait GetTransformationV2
{
    protected Components $components;

    public function getTransformationV2(array $item): array
    {
        $componentId = Config::getComponentId(sprintf('%s-%s', $item['backend'], $item['type']));
        return $this->components->getConfiguration($componentId, $item['id']);
    }
}
