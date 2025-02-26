<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Tests;

use Keboola\Orchestrator\Client;
use Keboola\StorageApi\Client as StorageClient;
use Keboola\TransformationMigrate\OrchestrationsUpdater;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class OrchestrationsUpdaterTest extends TestCase
{
    public function testUpdateOrchestrations(): void
    {
        // Create mocks
        $storageClientMock = $this->createMock(StorageClient::class);
        $storageClientMock->method('getTokenString')->willReturn('test-token');
        $storageClientMock->method('getServiceUrl')->willReturn('https://connection.keboola.com/orchestrator');
        
        $logger = new TestLogger();
        
        $transformationConfigMap = [
            'old-config-id-1' => 'new-config-id-1',
            'old-config-id-2' => 'new-config-id-2',
        ];
        
        // Mock the orchestrator client
        $orchestratorClientMock = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock the responses
        $orchestratorClientMock->method('getOrchestrations')->willReturn([
            ['id' => '123', 'name' => 'Test Orchestration 1'],
            ['id' => '456', 'name' => 'Test Orchestration 2'],
        ]);
        
        $orchestration1 = [
            'id' => '123',
            'name' => 'Test Orchestration 1',
            'tasks' => [
                [
                    'id' => 1,
                    'component' => 'keboola.snowflake-transformation',
                    'actionParameters' => [
                        'configId' => 'old-config-id-1',
                    ],
                ],
                [
                    'id' => 2,
                    'component' => 'keboola.python-transformation-v2',
                    'actionParameters' => [
                        'configId' => 'different-config-id',
                    ],
                ],
            ],
        ];
        
        $orchestration2 = [
            'id' => '456',
            'name' => 'Test Orchestration 2',
            'tasks' => [
                [
                    'id' => 3,
                    'component' => 'keboola.python-transformation-v2',
                    'actionParameters' => [
                        'configId' => 'old-config-id-2',
                    ],
                ],
            ],
        ];
        
        $orchestratorClientMock->method('getOrchestration')
            ->willReturnMap([
                ['123', $orchestration1],
                ['456', $orchestration2],
            ]);
        
        // Expect updateTasks to be called with the correct parameters
        $orchestratorClientMock->expects($this->exactly(2))
            ->method('updateTasks')
            ->withConsecutive(
                [
                    '123',
                    $this->callback(function ($tasks) {
                        return isset($tasks[0]['actionParameters']['configId']) && 
                               $tasks[0]['actionParameters']['configId'] === 'new-config-id-1';
                    }),
                ],
                [
                    '456',
                    $this->callback(function ($tasks) {
                        return isset($tasks[0]['actionParameters']['configId']) && 
                               $tasks[0]['actionParameters']['configId'] === 'new-config-id-2';
                    }),
                ]
            );
        
        // Create the updater with a mock client factory
        $updater = new class($storageClientMock, $logger, $transformationConfigMap, $orchestratorClientMock) extends OrchestrationsUpdater {
            private $mockClient;
            
            public function __construct(
                StorageClient $storageClient,
                \Psr\Log\LoggerInterface $logger,
                array $transformationConfigMap,
                Client $mockClient
            ) {
                parent::__construct($storageClient, $logger, $transformationConfigMap);
                $this->mockClient = $mockClient;
            }
            
            protected function createClient(): Client
            {
                return $this->mockClient;
            }
        };
        
        // Run the update
        $result = $updater->updateOrchestrations();
        
        // Assert the results
        $this->assertCount(2, $result['updated']);
        $this->assertContains('123', $result['updated']);
        $this->assertContains('456', $result['updated']);
        $this->assertCount(0, $result['errors']);
        $this->assertCount(0, $result['skipped']);
        
        // Check the logs
        $this->assertTrue($logger->hasInfoThatContains('Initializing Orchestrator client'));
        $this->assertTrue($logger->hasInfoThatContains('Found 2 orchestrations'));
        $this->assertTrue($logger->hasInfoThatContains('changing config ID from old-config-id-1 to new-config-id-1'));
        $this->assertTrue($logger->hasInfoThatContains('changing config ID from old-config-id-2 to new-config-id-2'));
    }
    
    public function testUpdateOrchestrationsWithNoMatchingConfigs(): void
    {
        // Create mocks
        $storageClientMock = $this->createMock(StorageClient::class);
        $storageClientMock->method('getTokenString')->willReturn('test-token');
        $storageClientMock->method('getServiceUrl')->willReturn('https://connection.keboola.com/orchestrator');
        
        $logger = new TestLogger();
        
        $transformationConfigMap = [
            'old-config-id-1' => 'new-config-id-1',
            'old-config-id-2' => 'new-config-id-2',
        ];
        
        // Mock the orchestrator client
        $orchestratorClientMock = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock the responses
        $orchestratorClientMock->method('getOrchestrations')->willReturn([
            ['id' => '123', 'name' => 'Test Orchestration 1'],
        ]);
        
        $orchestration = [
            'id' => '123',
            'name' => 'Test Orchestration 1',
            'tasks' => [
                [
                    'id' => 1,
                    'component' => 'keboola.snowflake-transformation',
                    'actionParameters' => [
                        'configId' => 'different-config-id',
                    ],
                ],
            ],
        ];
        
        $orchestratorClientMock->method('getOrchestration')
            ->willReturn($orchestration);
        
        // Expect updateTasks not to be called
        $orchestratorClientMock->expects($this->never())
            ->method('updateTasks');
        
        // Create the updater with a mock client factory
        $updater = new class($storageClientMock, $logger, $transformationConfigMap, $orchestratorClientMock) extends OrchestrationsUpdater {
            private $mockClient;
            
            public function __construct(
                StorageClient $storageClient,
                \Psr\Log\LoggerInterface $logger,
                array $transformationConfigMap,
                Client $mockClient
            ) {
                parent::__construct($storageClient, $logger, $transformationConfigMap);
                $this->mockClient = $mockClient;
            }
            
            protected function createClient(): Client
            {
                return $this->mockClient;
            }
        };
        
        // Run the update
        $result = $updater->updateOrchestrations();
        
        // Assert the results
        $this->assertCount(0, $result['updated']);
        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['skipped']);
        $this->assertContains('123', $result['skipped']);
        
        // Check the logs
        $this->assertTrue($logger->hasInfoThatContains('No changes needed for orchestration 123'));
    }
} 