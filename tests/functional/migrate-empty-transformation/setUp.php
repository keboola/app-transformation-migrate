<?php

declare(strict_types=1);

use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket(
        TestManager::TRANSFORMATION_BUCKET_NAME,
        "test bucket description\n\nwith new line"
    );

    $row = new ConfigurationRow($configuration);
    $row->setName('Empty config');
    $row->setConfiguration([
        'backend' => 'snowflake',
        'type' => 'simple',
    ]);

    $test->getComponentsClient()->addConfigurationRow($row);

    $test->setTransformationBucketId($configuration->getConfigurationId());
    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
