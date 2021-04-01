<?php

declare(strict_types=1);

use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;
use PHPUnit\Framework\Assert;

return function (DatadirTest $test): string {
    $manager = new TestManager($test->getComponentsClient());

    $result = [];
    foreach ($test->getOutput() as $item) {
        $transformation = $manager->getTransformationV2($item);
        $result[] = $transformation['configuration'];

        $oldTransformation = $manager->getTransformation($test->getTransformationBucketId());

        Assert::assertArrayHasKey('configuration', $oldTransformation);
        Assert::assertArrayHasKey('migrated', $oldTransformation['configuration']);
        Assert::assertTrue($oldTransformation['configuration']['migrated']);

        $manager->removeTransformationV2($item);
    }

    return (string) json_encode($result, JSON_PRETTY_PRINT);
};
