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
    private Components $componentsClient;

    public function __construct(Components $componentsClient)
    {
        $this->componentsClient = $componentsClient;
    }

    public function getTransformationConfig(int $transformationId): array
    {
        $transformationConfig = $this->componentsClient->getConfiguration('transformation', $transformationId);
        return $this->removeDisableTransformation($transformationConfig);
    }

    public function migrateTransformationConfig(array $transformationConfig): array
    {
        $transformationConfigRows = $this->sortConfigRowsByPhase($transformationConfig['rows']);

        $transformationsV2 = [];
        foreach ($transformationConfigRows as $row) {
            $transformationKey = sprintf(
                '%s-%s',
                $row['configuration']['backend'],
                $row['configuration']['type']
            );

            if (!isset($transformationsV2[$transformationKey])) {
                $transformationsV2[$transformationKey] = new TransformationV2(
                    $transformationConfig['name'],
                    $row['configuration']['type'],
                    $row['configuration']['backend']
                );
            }

            $this->processRow($row, $transformationsV2[$transformationKey]);
        }

        $result = [];
        foreach ($transformationsV2 as $transformationTypeKey => $transformationV2) {
            $newConfig = $this->createTransformationConfig(
                $transformationTypeKey,
                $transformationV2->getName(),
                $this->prepareTransformationConfigV2($transformationV2)
            );

            $result[] = [
                'componentId' => $transformationV2->getComponentId(),
                'id' => $newConfig['id'],
            ];
        }

        return $result;
    }

    public function markOldTransformationAsMigrated(array $transformationConfig): void
    {
        $configuration = new Configuration();
        $configuration
            ->setChangeDescription('Mark as migrated')
            ->setComponentId('transformation')
            ->setConfigurationId($transformationConfig['id'])
            ->setConfiguration(
                array_merge(
                    (array) $transformationConfig['configuration'],
                    ['migrated' => true]
                )
            )
        ;

        $this->componentsClient->updateConfiguration($configuration);
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

    private function processRow(array $row, TransformationV2 $transformationV2): void
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
        if (isset($row['configuration']['packages'])) {
            $transformationV2->addPackages($row['configuration']['packages']);
        }

        if (isset($row['configuration']['tags'])) {
            $transformationV2->addFileTags($row['configuration']['tags']);
        }

        $code = new TransformationV2Code();
        $code->setName($row['name']);
        foreach ($row['configuration']['queries'] as $query) {
            $code->addScript($query);
        }
        $phase = (int) $row['configuration']['phase'];
        $block = $transformationV2->getBlockByPhase($phase);
        if (!$block) {
            $block = new TransformationV2Block($phase);
            $block->addCode($code);
            $transformationV2->addBlock($block);
        } else {
            $block->addCode($code);
        }
    }

    private function prepareTransformationConfigV2(TransformationV2 $transformationV2): array
    {
        $parameters = ['blocks' => []];

        if ($transformationV2->hasPackages()) {
            $parameters['packages'] = $transformationV2->getPackages();
        }

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

        $inputMapping = [
            'tables' => array_values($transformationV2->getInputMappingTables()),
        ];

        if ($transformationV2->hasFileTags()) {
            $inputMapping['files'] = [['tags' => array_values($transformationV2->getFileTags())]];
        }

        return [
            'parameters' => $parameters,
            'storage' => [
                'input' => $inputMapping,
                'output' => [
                    'tables' => array_values($transformationV2->getOutputMappingTables()),
                ],
            ],
        ];
    }

    private function sortConfigRowsByPhase(array $transformationConfigRows): array
    {
        $phases = array_map(fn(array $v) => $v['configuration']['phase'], $transformationConfigRows);

        if (count(array_unique($phases)) > 1) {
            usort($transformationConfigRows, function (array $a, array $b): int {
                return $a['configuration']['phase'] < $b['configuration']['phase'] ? -1 : 1;
            });
        }

        return $transformationConfigRows;
    }
}
