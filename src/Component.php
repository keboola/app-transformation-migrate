<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\Component\BaseComponent;
use Keboola\TransformationMigrate\Configuration\Config;
use Keboola\TransformationMigrate\Configuration\ConfigDefinition;

class Component extends BaseComponent
{
    private const CHECK_ACTION = 'check';

    private const CHECK_MIGRATE = 'migrate';

    protected function migrate(): array
    {
        $application = $this->getApplication();

        if ($this->getConfig()->hasTransformationId()) {
            $transformationConfig = $application->getTransformationConfig($this->getConfig()->getTransformationId());
            $application->checkConfigIsValid($transformationConfig);
            $result = $application->migrateTransformationConfig($transformationConfig, $this->getConfig());
            $application->markOldTransformationAsMigrated($transformationConfig);
            
            // If updateOrchestrations flag is set to true, update orchestrations as well
            if ($this->getConfig()->shouldUpdateOrchestrations()) {
                $this->getLogger()->info('Updating orchestrations referencing the migrated transformation.');
                $updatedOrchestrations = $application->updateOrchestrations($this->getLogger());
                
                if (count($updatedOrchestrations['updated']) > 0) {
                    $this->getLogger()->info(
                        sprintf(
                            'Updated %d orchestration(s) with the new transformation configuration.',
                            count($updatedOrchestrations['updated'])
                        )
                    );
                    
                    // Add information about updated orchestrations to the result
                    $result['updatedOrchestrations'] = $updatedOrchestrations;
                } else {
                    $this->getLogger()->info('No orchestrations found referencing the migrated transformation.');
                }
            }
            
            return $result;
        }

        return [];
    }

    protected function checkTransformation(): array
    {
        $application = $this->getApplication();

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

    private function getApplication(): Application
    {
        $branchId = null;
        if (getenv('KBC_BRANCHID')) {
            $branchId = (string) getenv('KBC_BRANCHID');
        }

        $storageApiClient = StorageApiClientFactory::getClient($this->getConfig(), $branchId);

        return new Application($storageApiClient);
    }
}
