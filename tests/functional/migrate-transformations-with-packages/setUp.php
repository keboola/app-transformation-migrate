<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket();

    $manager->createTransformation(
        $configuration,
        'python row',
        null,
        [
            'backend' => 'docker',
            'type' => 'python',
            'phase' => 1,
            'queries' => [
                'print(1)',
            ],
            'packages' => [
                'xgboost',
            ],
        ],
    );

    $manager->createTransformation(
        $configuration,
        'python row 2',
        null,
        [
            'backend' => 'docker',
            'type' => 'python',
            'phase' => 1,
            'queries' => [
                'print(2)',
            ],
            'packages' => [
                'xgboost',
                'secondPackage',
            ],
        ],
    );

    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
