<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Traits\RemoveTrasformationBucketsTrait;
use RuntimeException;

class DatadirTest extends DatadirTestCase
{
    use RemoveTrasformationBucketsTrait;

    protected Components $componentsClient;

    protected string $testProjectDir;

    protected string $testTempDir;

    private int $transformationBucketId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testProjectDir = $this->getTestFileDir() . '/' . $this->dataName();
        $this->testTempDir = $this->temp->getTmpFolder();

        $this->componentsClient = ClientFactory::createComponentsClient();

        $this->removeTransformationBuckets(TestManager::TRANSFORMATION_BUCKET_NAME);

        // Load setUp.php file - used to init database state
        $setUpPhpFile = $this->testProjectDir . '/setUp.php';
        if (file_exists($setUpPhpFile)) {
            // Get callback from file and check it
            $initCallback = require $setUpPhpFile;
            if (!is_callable($initCallback)) {
                throw new RuntimeException(sprintf('File "%s" must return callback!', $setUpPhpFile));
            }

            // Invoke callback
            $initCallback($this);
        }
    }

    public function tearDown(): void
    {
        $this->removeTransformationBuckets(TestManager::TRANSFORMATION_BUCKET_NAME);
    }

    public function setTransformationBucketId(int $transformationBucketId): self
    {
        $this->transformationBucketId = $transformationBucketId;
        return $this;
    }

    public function getComponentsClient(): Components
    {
        return $this->componentsClient;
    }
}
