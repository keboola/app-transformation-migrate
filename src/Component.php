<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\Component\BaseComponent;
use Keboola\StorageApi\BranchAwareClient;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Configuration\Config;
use Keboola\TransformationMigrate\Configuration\ConfigDefinition;

class Component extends BaseComponent
{
    private const CHECK_ACTION = 'check';

    private const CHECK_MIGRATE = 'migrate';

    protected function migrate(): array
    {
        $application = new Application(new Components($this->getStorageClient()));

        if ($this->getConfig()->hasTransformationId()) {
            $transformationConfig = $application->getTransformationConfig($this->getConfig()->getTransformationId());
            $application->checkConfigIsValid($transformationConfig);
            $result = $application->migrateTransformationConfig($transformationConfig);
            $application->markOldTransformationAsMigrated($transformationConfig);
            return $result;
        }

        return [];
    }

    protected function checkTransformation(): array
    {
        $application = new Application(new Components($this->getStorageClient()));

        $transformationConfig = $application->getTransformationConfig($this->getConfig()->getTransformationId());

        $application->checkConfigIsValid($transformationConfig);

        return [
            'status' => 'success',
        ];
    }

    public function getConfig(): Config
    {
        /** @var Config $config */
        $config = parent::getConfig();
        return $config;
    }

    protected function getSyncActions(): array
    {
        return [
            self::CHECK_MIGRATE => 'migrate',
            self::CHECK_ACTION => 'checkTransformation',
        ];
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }

    private function getStorageClient(): Client
    {
        $branchId = getenv('KBC_BRANCHID');
        if ($branchId) {
            return new BranchAwareClient(
                $branchId,
                [
                    'url' => $this->getConfig()->getKbcUrl(),
                    'token' => $this->getConfig()->getKbcStorageToken(),
                ]
            );
        }

        return new Client(
            [
                'url' => $this->getConfig()->getKbcUrl(),
                'token' => $this->getConfig()->getKbcStorageToken(),
            ]
        );
    }
}
