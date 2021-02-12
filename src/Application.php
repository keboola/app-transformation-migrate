<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Configuration\Config;

class Application
{
    private Config $config;

    private Components $componentsClient;

    public function __construct(Config $config, Components $componentsClient)
    {
        $this->config = $config;
        $this->componentsClient = $componentsClient;
    }

    public function getTransformationConfig(int $transformationId): array
    {
        $transformationConfig = $this->componentsClient->getConfiguration('transformation', $transformationId);
        return $this->removeDisableTransformation($transformationConfig);
    }

    public function checkConfigIsValid(array $transformationConfig): void
    {
        $transformationValidator = new TransformationConfigValidator($transformationConfig);
        $transformationValidator->validate();
    }

    private function removeDisableTransformation(array $config): array
    {
        $config['rows'] = array_filter(
            $config['rows'],
            fn(array $row) => !isset($row['configuration']['disabled']) || !$row['configuration']['disabled']
        );
        return $config;
    }
}
