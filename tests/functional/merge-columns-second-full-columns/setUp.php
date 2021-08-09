<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket();

    $manager->createTransformation(
        $configuration,
        'snflk row 1',
        null,
        [
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'columns' => [],
                    'changedSince' => '-10 minutes',
                    'datatypes' => [
                        'column_1' => [
                            'column' => 'column_1',
                            'type' => 'VARCHAR',
                            'length' => null,
                            'convertEmptyValuesToNull' => false,
                        ],
                    ],
                ],
            ],
        ]
    );

    $manager->createTransformation(
        $configuration,
        'snflk row 2',
        null,
        [
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'columns' => [],
                    'changedSince' => '-10 minutes',
                    'datatypes' => [
                        'column_2' => [
                            'column' => 'column_2',
                            'type' => 'VARCHAR',
                            'length' => null,
                            'convertEmptyValuesToNull' => false,
                        ],
                    ],
                ],
            ],
        ]
    );

    $test->setTransformationBucketId($configuration->getConfigurationId());
    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
