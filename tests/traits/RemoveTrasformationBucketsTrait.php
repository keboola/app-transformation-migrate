<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Traits;

use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\ListComponentConfigurationsOptions;

trait RemoveTrasformationBucketsTrait
{
    protected Components $componentsClient;

    public function removeTransformationBuckets(string $name): void
    {
        $options = new ListComponentConfigurationsOptions();
        $options->setComponentId('transformation');
        $listConfigurations = $this->componentsClient->listComponentConfigurations($options);

        foreach ($listConfigurations as $configuration) {
            if ($configuration['name'] === $name) {
                $this->componentsClient->deleteConfiguration('transformation', $configuration['id']);
                // second call - remove permanently from trash
                $this->componentsClient->deleteConfiguration('transformation', $configuration['id']);
            }
        }
    }
}
