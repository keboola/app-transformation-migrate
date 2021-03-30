<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): string {
    $manager = new TestManager($test->getComponentsClient());

    $result = [];
    foreach ($test->getOutput() as $item) {
        $transformation = $manager->getTransformationV2($item);
        $result[] = $transformation['configuration'];

        $manager->removeTransformationV2($item);
    }

    return (string) json_encode($result, JSON_PRETTY_PRINT);
};
