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
            'tags' => [
                'fileTag1',
                'fileTag2',
                'fileTag3',
                'fileTag4',
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
            'tags' => [
                'fileTag2',
                'fileTag3',
            ],
        ],
    );

    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
