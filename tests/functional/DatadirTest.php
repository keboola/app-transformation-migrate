<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\Exception\DatadirTestsException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Traits\RemoveTrasformationTrait;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DatadirTest extends DatadirTestCase
{
    use RemoveTrasformationTrait;

    protected Components $componentsClient;

    protected Client $storageApiClient;

    protected string $testProjectDir;

    protected string $testTempDir;

    private string $transformationBucketId;

    protected array $output = [];

    protected array $processEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->testProjectDir = $this->getTestFileDir() . '/' . $this->dataName();
        $this->testTempDir = $this->temp->getTmpFolder();

        $this->componentsClient = $this->getComponentsClient();
        $this->storageApiClient = ClientFactory::createStorageClient();

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

        $processEnvFIle = $this->testProjectDir . '/processEnv.php';
        if (file_exists($processEnvFIle)) {
            $this->processEnv = require $processEnvFIle;
        }

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

    protected function runScript(string $datadirPath): Process
    {
        $fs = new Filesystem();

        $script = $this->getScript();
        if (!$fs->exists($script)) {
            throw new DatadirTestsException(sprintf(
                'Cannot open script file "%s"',
                $script
            ));
        }

        $runCommand = [
            'php',
            $script,
        ];
        $runProcess = new Process($runCommand);
        $runProcess->setEnv(
            array_merge(
                $this->processEnv,
                [
                    'KBC_DATADIR' => $datadirPath,
                ]
            )
        );
        $runProcess->setTimeout(0.0);
        $runProcess->run();
        return $runProcess;
    }

    public function tearDown(): void
    {
        $this->removeTransformationBuckets(TestManager::TRANSFORMATION_BUCKET_NAME);
    }

    public function setTransformationBucketId(string $transformationBucketId): self
    {
        $this->transformationBucketId = $transformationBucketId;
        return $this;
    }

    public function getTransformationBucketId(): string
    {
        return $this->transformationBucketId;
    }

    public function getComponentsClient(bool $awareBranch = false): Components
    {
        return ClientFactory::createComponentsClient($awareBranch);
    }

    public function getStorageClient(): Client
    {
        return $this->storageApiClient;
    }

    public function getOutput(): array
    {
        return $this->output;
    }
}
