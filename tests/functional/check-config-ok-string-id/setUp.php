<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket(
        TestManager::TRANSFORMATION_BUCKET_NAME,
        null,
        'string-id',
    );

    $manager->createTransformation(
        $configuration,
        'snflk row',
    );

    $manager->createTransformation(
        $configuration,
        'snflk row 2',
    );

    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
