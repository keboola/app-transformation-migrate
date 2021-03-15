<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\TransformationMigrate\Configuration\Config;
use Keboola\TransformationMigrate\ValueObjects\TransformationV2;
use Keboola\TransformationMigrate\ValueObjects\TransformationV2Block;
use Keboola\TransformationMigrate\ValueObjects\TransformationV2Code;

class Application
{
    private Config $config;

    private Components $componentsClient;

    public function __construct(Config $config, Components $componentsClient)
    {
        $this->config = $config;
        $this->componentsClient = $componentsClient;
    }

    public function getTransformationConfig(int $transformationId): array
    {
        $transformationConfig = $this->componentsClient->getConfiguration('transformation', $transformationId);
        return $this->removeDisableTransformation($transformationConfig);
    }

    public function migrateTransformationConfig(array $transformationConfig): array
    {
        $transformationsV2 = [];
        foreach ($transformationConfig['rows'] as $row) {
            $transformationKey = sprintf(
                '%s-%s',
                $row['configuration']['backend'],
                $row['configuration']['type']
            );

            $transformationV2 = new TransformationV2(
                $transformationConfig['name'],
                $row['configuration']['type'],
                $row['configuration']['backend']
            );
            if (isset($transformationsV2[$transformationKey])) {
                $transformationV2 = $transformationsV2[$transformationKey];
            }

            $transformationV2 = $this->processRow($row, $transformationV2);

            $transformationsV2[$transformationKey] = $transformationV2;
        }

        $result = [];
        foreach ($transformationsV2 as $transformationTypeKey => $transformationV2) {
            $newConfig = $this->createTransformationConfig(
                $transformationTypeKey,
                $transformationV2->getName(),
                $this->prepareTransformationConfigV2($transformationV2)
            );

            $result[] = [
                'type' => $transformationV2->getType(),
                'backend' => $transformationV2->getBackend(),
                'id' => $newConfig['id'],
            ];
        }

        return $result;
    }

    public function checkConfigIsValid(array $transformationConfig): void
    {
        $transformationValidator = new TransformationValidator($transformationConfig);
        $transformationValidator->validate();
    }

    private function removeDisableTransformation(array $config): array
    {
        $config['rows'] = array_filter(
            $config['rows'],
            fn(array $row) => !isset($row['configuration']['disabled']) || !$row['configuration']['disabled']
        );
        return $config;
    }

    private function createTransformationConfig(string $transformationTypeKey, string $name, array $config): array
    {
        $options = new Configuration();
        $options
            ->setName($name)
            ->setConfiguration($config)
            ->setComponentId(Config::getComponentId($transformationTypeKey))
        ;

        return $this->componentsClient->addConfiguration($options);
    }

    private function processRow(array $row, TransformationV2 $transformationV2): TransformationV2
    {
        if (isset($row['configuration']['input'])) {
            foreach ($row['configuration']['input'] as $inputMapping) {
                $transformationV2->addInputMappingTable($inputMapping);
            }
        }
        if (isset($row['configuration']['output'])) {
            foreach ($row['configuration']['output'] as $outputMapping) {
                $transformationV2->addOutputMappingTable($outputMapping);
            }
        }

        $code = new TransformationV2Code();
        foreach ($row['configuration']['queries'] as $query) {
            $code->addScript($query);
        }
        $block = new TransformationV2Block();
        $block->setName($row['name']);
        $block->addCode($code);
        $transformationV2->addBlock($block);

        return $transformationV2;
    }

    private function prepareTransformationConfigV2(TransformationV2 $transformationV2): array
    {
        $parameters = ['blocks' => []];
        foreach ($transformationV2->getBlocks() as $block) {
            $blockArr = [
                'name' => $block->getName(),
                'codes' => [],
            ];
            foreach ($block->getCodes() as $code) {
                $blockArr['codes'][] = [
                    'name' => $code->getName(),
                    'script' => $code->getScripts(),
                ];
            }
            $parameters['blocks'][] = $blockArr;
        }
        $newConfig = [
            'parameters' => $parameters,
            'storage' => [
                'input' => [
                    'tables' => array_values($transformationV2->getInputMappingTables()),
                ],
                'output' => [
                    'tables' => array_values($transformationV2->getOutputMappingTables()),
                ],
            ],
        ];

        return $newConfig;
    }
}
