<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket();

    $transformations = [];
    for ($i = 1; $i <= 8; $i++) {
        $transformations[$i] = $manager->createTransformation(
            $configuration,
            'transformation ' . $i,
        );
    }

    $transformations[1]->setConfiguration(
        array_merge(
            (array) $transformations[1]->getConfiguration(),
            [
                'requires' => [
                    (string) $transformations[4]->getRowId(),
                    (string) $transformations[2]->getRowId(),
                    '123456789',
                ],
            ],
        ),
    );

    $transformations[2]->setConfiguration(
        array_merge(
            $transformations[2]->getConfiguration(),
            ['requires' => [(string) $transformations[4]->getRowId()]],
        ),
    );

    $transformations[3]->setConfiguration(
        array_merge(
            $transformations[3]->getConfiguration(),
            ['requires' => [(string) $transformations[1]->getRowId(), (string) $transformations[2]->getRowId()]],
        ),
    );

    $transformations[5]->setConfiguration(
        array_merge(
            $transformations[5]->getConfiguration(),
            [
                'phase' => 2,
                'requires' => [(string) $transformations[7]->getRowId()],
            ],
        ),
    );

    $transformations[6]->setConfiguration(
        array_merge(
            $transformations[6]->getConfiguration(),
            [
                'phase' => 2,
                'requires' => [(string) $transformations[5]->getRowId()],
            ],
        ),
    );

    $transformations[7]->setConfiguration(
        array_merge(
            $transformations[7]->getConfiguration(),
            [
                'phase' => 2,
            ],
        ),
    );

    $transformations[8]->setConfiguration(
        array_merge(
            $transformations[8]->getConfiguration(),
            [
                'phase' => 2,
                'requires' => [
                    (string) $transformations[5]->getRowId(),
                    (string) $transformations[6]->getRowId(),
                    (string) $transformations[7]->getRowId(),
                ],
            ],
        ),
    );

    foreach ($transformations as $transformation) {
        $test->getComponentsClient()->updateConfigurationRow($transformation);
    }

    $test->setTransformationBucketId($configuration->getConfigurationId());
    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
