<?php

declare(strict_types=1);

use Keboola\StorageApi\Options\Components\ListConfigurationMetadataOptions;
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

        $configurationMetadataOptions = new ListConfigurationMetadataOptions();
        $configurationMetadataOptions
            ->setComponentId($item['componentId'])
            ->setConfigurationId($item['id'])
        ;

        $metadata = $test->getComponentsClient(true)->listConfigurationMetadata($configurationMetadataOptions);

        Assert::assertEquals('KBC.configurationFolder', $metadata[0]['key']);
        Assert::assertEquals('test_transformation_bucket', $metadata[0]['value']);
        Assert::assertArrayHasKey('configuration', $oldTransformation);
        Assert::assertArrayHasKey('migrated', $oldTransformation['configuration']);
        Assert::assertTrue($oldTransformation['configuration']['migrated']);
        Assert::assertEquals('Mark as migrated', $oldTransformation['currentVersion']['changeDescription']);

        $manager->removeTransformationV2($item);
    }

    return (string) json_encode($result, JSON_PRETTY_PRINT);
};
