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
        null,
        [
            'output' => [
                [
                    'source' => 'outputTable1',
                    'destination' => 'outputTable1',
                    'columns' => [],
                ],
                [
                    'source' => 'outputTable1',
                    'destination' => 'outputTable2',
                    'columns' => [],
                ],
                [
                    'source' => 'outputTable2',
                    'destination' => 'outputTable1',
                    'columns' => [],
                ],
                [
                    'source' => 'outputTable2',
                    'destination' => 'outputTable2',
                    'columns' => [],
                ],
            ],
        ],
    );

    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
