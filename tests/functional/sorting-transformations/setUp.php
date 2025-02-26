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

    $config1 = $transformations[1]->getConfiguration();
    assert(is_array($config1));
    $config2 = $transformations[2]->getConfiguration();
    assert(is_array($config2));
    $config3 = $transformations[3]->getConfiguration();
    assert(is_array($config3));
    $config5 = $transformations[5]->getConfiguration();
    assert(is_array($config5));
    $config6 = $transformations[6]->getConfiguration();
    assert(is_array($config6));
    $config7 = $transformations[7]->getConfiguration();
    assert(is_array($config7));
    $config8 = $transformations[8]->getConfiguration();
    assert(is_array($config8));

    $row1Id = $transformations[1]->getRowId();
    assert(is_int($row1Id));
    $row2Id = $transformations[2]->getRowId();
    assert(is_int($row2Id));
    $row4Id = $transformations[4]->getRowId();
    assert(is_int($row4Id));
    $row5Id = $transformations[5]->getRowId();
    assert(is_int($row5Id));
    $row6Id = $transformations[6]->getRowId();
    assert(is_int($row6Id));
    $row7Id = $transformations[7]->getRowId();
    assert(is_int($row7Id));

    $transformations[1]->setConfiguration(
        array_merge(
            $config1,
            [
                'requires' => [
                    $row4Id,
                    $row2Id,
                    '123456789',
                ],
            ],
        ),
    );

    $transformations[2]->setConfiguration(
        array_merge(
            $config2,
            ['requires' => [$row4Id]],
        ),
    );

    $transformations[3]->setConfiguration(
        array_merge(
            $config3,
            ['requires' => [$row1Id, $row2Id]],
        ),
    );

    $transformations[5]->setConfiguration(
        array_merge(
            $config5,
            [
                'phase' => 2,
                'requires' => [$row7Id],
            ],
        ),
    );

    $transformations[6]->setConfiguration(
        array_merge(
            $config6,
            [
                'phase' => 2,
                'requires' => [$row5Id],
            ],
        ),
    );

    $transformations[7]->setConfiguration(
        array_merge(
            $config7,
            [
                'phase' => 2,
            ],
        ),
    );

    $transformations[8]->setConfiguration(
        array_merge(
            $config8,
            [
                'phase' => 2,
                'requires' => [
                    $row5Id,
                    $row6Id,
                    $row7Id,
                ],
            ],
        ),
    );

    foreach ($transformations as $transformation) {
        $test->getComponentsClient()->updateConfigurationRow($transformation);
    }

    $configurationId = $configuration->getConfigurationId();
    assert(is_string($configurationId));

    $test->setTransformationBucketId($configurationId);
    putenv('TRANSFORMATION_BUCKET_ID=' . $configurationId);
};
