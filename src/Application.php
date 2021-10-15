<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\TransformationMigrate\Configuration\Config;
use Keboola\TransformationMigrate\ValueObjects\TransformationV2;
use Keboola\TransformationMigrate\ValueObjects\TransformationV2Block;
use Keboola\TransformationMigrate\ValueObjects\TransformationV2Code;

class Application
{
    private Client $storageApiClient;

    private Components $componentsClient;

    public function __construct(Client $storageApiClient)
    {
        $this->storageApiClient = $storageApiClient;
        $this->componentsClient = new Components($storageApiClient);
    }

    public function getTransformationConfig(int $transformationId): array
    {
        $transformationConfig = $this->componentsClient->getConfiguration('transformation', $transformationId);
        $transformationConfig = LegacyTransformationHelper::removeDisableTransformation($transformationConfig);
        $transformationConfig = LegacyTransformationHelper::checkAndCompletionInputMapping(
            $this->storageApiClient,
            $transformationConfig
        );
        $transformationConfig = LegacyTransformationHelper::sortTransformationRows($transformationConfig);

        return $transformationConfig;
    }

    public function migrateTransformationConfig(array $transformationConfig): array
    {
        $transformationConfigRows = $transformationConfig['rows'];

        $transformationsV2 = [];
        foreach ($transformationConfigRows as $row) {
            $transformationKey = sprintf(
                '%s-%s-%s',
                $row['configuration']['backend'],
                $row['configuration']['type'],
                $row['configuration']['phase']
            );

            if (!isset($transformationsV2[$transformationKey])) {
                $transformationV2 = TransformationV2::createFromConfig($transformationConfig, $row);
                $transformationsV2[$transformationKey][] = $transformationV2;
            } else {
                $transformationV2 = null;
                foreach ($transformationsV2[$transformationKey] as $key => $savedTransformationV2) {
                    if (LegacyTransformationHelper::isSuitableTransformation($row, $savedTransformationV2) === true) {
                        $transformationV2 = $savedTransformationV2;
                        $transformationsV2[$transformationKey][$key] = $transformationV2;
                        break;
                    }
                }

                if (is_null($transformationV2)) {
                    $transformationV2 = TransformationV2::createFromConfig($transformationConfig, $row);
                    $transformationsV2[$transformationKey][] = $transformationV2;
                }
            }

            $this->processRow($row, $transformationV2);
        }
        $resultTransformationsV2 = [];
        foreach ($transformationsV2 as $item) {
            $resultTransformationsV2 = array_merge(
                $resultTransformationsV2,
                array_values($item)
            );
        }

        $result = [];
        $hasMultiplePhases = count(array_unique(array_map(fn($v) => $v->getPhase(), $resultTransformationsV2))) > 1;

        foreach ($resultTransformationsV2 as $resultTransformationV2) {
            $name = $resultTransformationV2->getName();
            if ($hasMultiplePhases) {
                $name .= sprintf(' - %s. phase', $resultTransformationV2->getPhase());
            }
            $newConfig = $this->createTransformationConfig(
                sprintf(
                    '%s-%s',
                    $resultTransformationV2->getBackend(),
                    $resultTransformationV2->getType()
                ),
                $name,
                $resultTransformationV2->getDescription(),
                $this->prepareTransformationConfigV2($resultTransformationV2)
            );

            $result[] = [
                'componentId' => $resultTransformationV2->getComponentId(),
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

    private function createTransformationConfig(
        string $transformationTypeKey,
        string $name,
        string $description,
        array $config
    ): array {
        $options = new Configuration();
        $options
            ->setName($name)
            ->setDescription($description)
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

        if (!empty($row['description'])) {
            $transformationV2->addDescription($row['description']);
        }

        $code = new TransformationV2Code();
        $transformationName = $row['name'];
        if (empty($transformationName) && !empty($row['configuration']['name'])) {
            $transformationName = $row['configuration']['name'];
        }
        $code->setName($transformationName);
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
}
