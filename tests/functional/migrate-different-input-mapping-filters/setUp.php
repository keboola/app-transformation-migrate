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
            'queries' => [
                'SELECT 1;',
            ],
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'changedSince' => '-10 minutes',
                    'whereColumn' => 'column',
                    'whereValues' => 'values',
                    'whereOperator' => 'operator',
                ],
            ],
        ],
    );

    $manager->createTransformation(
        $configuration,
        'snflk row',
        null,
        [
            'queries' => [
                'SELECT 2;',
            ],
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'changedSince' => '-10 minutes',
                    'whereColumn' => 'column',
                    'whereValues' => 'values',
                    'whereOperator' => 'operator2',
                ],
            ],
        ],
    );

    $manager->createTransformation(
        $configuration,
        'snflk row',
        null,
        [
            'queries' => [
                'SELECT 3;',
            ],
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'changedSince' => '-10 minutes',
                    'whereColumn' => 'column',
                    'whereValues' => 'values2',
                    'whereOperator' => 'operator',
                ],
            ],
        ],
    );

    $manager->createTransformation(
        $configuration,
        'snflk row',
        null,
        [
            'queries' => [
                'SELECT 4;',
            ],
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'changedSince' => '-2 minutes',
                    'whereColumn' => 'column',
                    'whereValues' => 'values',
                    'whereOperator' => 'operator',
                ],
            ],
        ],
    );

    $manager->createTransformation(
        $configuration,
        'snflk row',
        null,
        [
            'queries' => [
                'SELECT 5;',
            ],
            'input' => [
                [
                    'source' => 'inputTable1',
                    'destination' => 'inputTable1',
                    'changedSince' => '-2 minutes',
                    'whereColumn' => 'column',
                    'whereValues' => 'values',
                    'whereOperator' => 'operator',
                ],
            ],
        ],
    );

    $configurationId = $configuration->getConfigurationId();
    assert(is_string($configurationId));

    $test->setTransformationBucketId($configurationId);
    putenv('TRANSFORMATION_BUCKET_ID=' . $configurationId);
};
