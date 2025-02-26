<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\Component\UserException;
use Keboola\Orchestrator\Client;
use Keboola\StorageApi\Client as StorageClient;
use Psr\Log\LoggerInterface;

class OrchestrationsUpdater
{
    private StorageClient $storageClient;
    private LoggerInterface $logger;
    private array $transformationConfigMap;

    public function __construct(
        StorageClient $storageClient,
        LoggerInterface $logger,
        array $transformationConfigMap
    ) {
        $this->storageClient = $storageClient;
        $this->logger = $logger;
        $this->transformationConfigMap = $transformationConfigMap;
    }

    public function updateOrchestrations(): array
    {
        $this->logger->info('Initializing Orchestrator client');
        $client = $this->createClient();

        // Fetch list of all orchestrations
        $this->logger->info('Fetching list of orchestrations');
        $orchestrations = $client->getOrchestrations();
        $this->logger->info(sprintf('Found %d orchestrations', count($orchestrations)));

        $results = [
            'updated' => [],
            'errors' => [],
            'skipped' => [],
        ];

        foreach ($orchestrations as $orchestration) {
            try {
                $updated = $this->processOrchestration($client, $orchestration);
                if ($updated) {
                    $results['updated'][] = $orchestration['id'];
                } else {
                    $results['skipped'][] = $orchestration['id'];
                }
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Error updating orchestration %s: %s',
                    $orchestration['id'],
                    $e->getMessage()
                ));
                $results['errors'][] = [
                    'id' => $orchestration['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Process a single orchestration and update its tasks if needed
     * 
     * @param Client $client Orchestrator API client
     * @param array $orchestration Orchestration data
     * @return bool True if the orchestration was updated, false otherwise
     */
    private function processOrchestration(Client $client, array $orchestration): bool
    {
        $this->logger->info(sprintf('Processing orchestration %s (%s)', $orchestration['id'], $orchestration['name']));
        
        // Fetch orchestration details
        $detail = $client->getOrchestration($orchestration['id']);
        $tasks = $detail['tasks'] ?? [];
        
        if (empty($tasks)) {
            $this->logger->info(sprintf('Orchestration %s has no tasks, skipping', $orchestration['id']));
            return false;
        }

        $updated = false;
        $newTasks = [];
        
        foreach ($tasks as $task) {
            $newTask = $task;
            
            // Check if this is a transformation task
            if (isset($task['component']) 
                && isset($task['actionParameters']['configId'])
                && $this->isTransformationComponent($task['component'])
            ) {
                $oldConfigId = $task['actionParameters']['configId'];
                
                // Check if this configuration is in our mapping
                if (isset($this->transformationConfigMap[$oldConfigId])) {
                    $newConfigId = $this->transformationConfigMap[$oldConfigId];
                    $this->logger->info(sprintf(
                        'Updating task in orchestration %s: changing config ID from %s to %s',
                        $orchestration['id'],
                        $oldConfigId,
                        $newConfigId
                    ));
                    
                    $newTask['actionParameters']['configId'] = $newConfigId;
                    $updated = true;
                }
            }
            
            $newTasks[] = $newTask;
        }
        
        if ($updated) {
            $this->logger->info(sprintf('Updating orchestration %s with new config IDs', $orchestration['id']));
            
            // Update the orchestration with new tasks
            $client->updateTasks($orchestration['id'], $newTasks);
            
            return true;
        }
        
        $this->logger->info(sprintf('No changes needed for orchestration %s', $orchestration['id']));
        return false;
    }

    /**
     * Check if the component ID is a transformation component
     * 
     * @param string $componentId Component ID to check
     * @return bool True if it's a transformation component
     */
    private function isTransformationComponent(string $componentId): bool
    {
        $transformationComponents = [
            'keboola.snowflake-transformation',
            'keboola.python-transformation-v2',
            'keboola.r-transformation-v2',
            'keboola.redshift-transformation',
        ];
        
        return in_array($componentId, $transformationComponents, true);
    }
    
    /**
     * Create a new Orchestrator client
     * 
     * @return Client
     */
    protected function createClient(): Client
    {
        return new Client([
            'token' => $this->storageClient->getTokenString(),
            'url' => $this->storageClient->getServiceUrl('orchestrator'),
        ]);
    }
} 