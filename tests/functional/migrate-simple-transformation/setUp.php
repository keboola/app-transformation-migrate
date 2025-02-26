<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket();

    $manager->createTransformation(
        $configuration,
        'snflk row',
    );

    $configurationId = $configuration->getConfigurationId();
    assert(is_string($configurationId));

    $test->setTransformationBucketId($configurationId);
    putenv('TRANSFORMATION_BUCKET_ID=' . $configurationId);
};
