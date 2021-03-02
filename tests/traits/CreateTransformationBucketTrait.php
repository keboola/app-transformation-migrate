<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Traits;

use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

trait CreateTransformationBucketTrait
{
    protected Components $components;

    public function createBucket(string $name = TestManager::TRANSFORMATION_BUCKET_NAME): Configuration
    {
        $configuration = new Configuration();
        $configuration
            ->setComponentId('transformation')
            ->setChangeDescription('Create test transformation configuration for migration app')
            ->setName($name)
        ;

        $transformationBucket = $this->components->addConfiguration($configuration);
        $configuration->setConfigurationId((int) $transformationBucket['id']);
        return $configuration;
    }
}
