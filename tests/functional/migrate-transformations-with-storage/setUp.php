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
        [
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'columns' => [],
                    'changedSince' => '-10 minutes',
                    'whereColumn' => 'column',
                    'whereValues' => 'values',
                    'whereOperator' => 'operator',
                ],
                [
                    'source' => 'inputTable2',
                    'destination' => 'inputTable2',
                    'columns' => [],
                ],
            ],
            'output' => [
                [
                    'source' => 'outputTable1',
                    'destination' => 'outputTable1',
                    'columns' => [],
                    'primaryKey' => 'primary key',
                    'deleteWhereColumn' => 'columns',
                    'deleteWhereOperator' => 'operator',
                    'deleteWhereValues' => 'values',
                ],
                [
                    'source' => 'outputTable2',
                    'destination' => 'outputTable2',
                    'columns' => [],
                ],
            ],
        ],
    );

    $manager->createTransformation(
        $configuration,
        'snflk row 2',
        [
            'input' => [
                [
                    'source' => 'inputTable2',
                    'destination' => 'inputTable2',
                    'columns' => [],
                ],
            ],
            'output' => [
                [
                    'source' => 'outputTable3',
                    'destination' => 'outputTable3',
                    'columns' => [],
                ],
            ],
        ],
    );

    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
