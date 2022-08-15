<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Traits;

use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

trait CreateTransformationBucketTrait
{
    protected Components $componentsClient;

    public function createBucket(
        string $name = TestManager::TRANSFORMATION_BUCKET_NAME,
        ?string $description = null,
        ?string $configurationId = null
    ): Configuration {
        $configuration = new Configuration();
        $configuration
            ->setComponentId('transformation')
            ->setChangeDescription('Create test transformation configuration for migration app')
            ->setName($name)
        ;

        if ($description) {
            $configuration->setDescription($description);
        }

        if ($configurationId) {
            $configuration->setConfigurationId($configurationId);
        }

        $transformationBucket = $this->componentsClient->addConfiguration($configuration);
        $configuration->setConfigurationId($transformationBucket['id']);
        return $configuration;
    }
}
