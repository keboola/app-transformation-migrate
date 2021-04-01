<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Traits\RemoveTrasformationTrait;
use RuntimeException;

class DatadirTest extends DatadirTestCase
{
    use RemoveTrasformationTrait;

    protected Components $componentsClient;

    protected string $testProjectDir;

    protected string $testTempDir;

    private int $transformationBucketId;

    protected array $output = [];

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

    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $tempDatadir = $this->getTempDatadir($specification);

        $process = $this->runScript($tempDatadir->getTmpFolder());

        if ($process->getOutput()) {
            $this->output = json_decode($process->getOutput(), true);
        }

        // Load tearDown.php file
        $tearDownPhpFile = $this->testProjectDir . '/tearDown.php';
        if (file_exists($tearDownPhpFile)) {
            // Get callback from file and check it
            $initCallback = require $tearDownPhpFile;
            if (!is_callable($initCallback)) {
                throw new RuntimeException(sprintf('File "%s" must return callback!', $tearDownPhpFile));
            }

            // Invoke callback
            $result = $initCallback($this);

            if ($result) {
                $transformationDirPath = $this->testTempDir . '/out/transformationDump/';
                mkdir($transformationDirPath);
                file_put_contents($transformationDirPath . 'transformations.json', $result);
            }
        }

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
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

    public function getTransformationBucketId(): int
    {
        return $this->transformationBucketId;
    }

    public function getComponentsClient(): Components
    {
        return $this->componentsClient;
    }

    public function getOutput(): array
    {
        return $this->output;
    }
}
