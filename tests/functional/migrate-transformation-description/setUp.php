<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket(
        TestManager::TRANSFORMATION_BUCKET_NAME,
        "test bucket description\n\nwith new line"
    );

    $manager->createTransformation(
        $configuration,
        'snflk row 1',
        'description snflk row 1'
    );

    $manager->createTransformation(
        $configuration,
        'snflk row 2',
        'description snflk row 2'
    );

    $test->setTransformationBucketId($configuration->getConfigurationId());
    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
