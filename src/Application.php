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
use MJS\TopSort\Implementations\StringSort;
use Throwable;

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
        $transformationConfig = $this->removeDisableTransformation($transformationConfig);
        $transformationConfig = $this->checkAndCompletionInputMapping($transformationConfig);
        $transformationConfig = $this->sortTransformationRows($transformationConfig);
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
                    if ($this->isSuitableTransformation($row, $savedTransformationV2) === true) {
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

    private function removeDisableTransformation(array $config): array
    {
        $config['rows'] = array_filter(
            $config['rows'],
            fn(array $row) => !isset($row['configuration']['disabled']) || !$row['configuration']['disabled']
        );
        return $config;
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

    private function checkAndCompletionInputMapping(array $transformationConfig): array
    {
        foreach ($transformationConfig['rows'] as $rowKey => $row) {
            if (!isset($row['configuration']['input'])) {
                continue;
            }
            foreach ($row['configuration']['input'] as $itemKey => $item) {
                if (!empty($item['columns'])) {
                    continue;
                }
                if (empty($item['datatypes'])) {
                    continue;
                }

                try {
                    $storageTable = $this->storageApiClient->getTable($item['source']);
                } catch (Throwable $e) {
                    continue;
                }
                $datatypes = array_filter($item['datatypes'], fn($v) => !is_null($v));

                $transformationColumnsDatatype = array_map(fn($v) => $v['column'], $datatypes);

                $missingColumns = array_diff($storageTable['columns'], $transformationColumnsDatatype);
                foreach ($missingColumns as $missingColumn) {
                    if (!isset($storageTable['columnMetadata'][$missingColumn])) {
                        $newColumn = [
                            'type' => 'VARCHAR',
                            'column' => $missingColumn,
                            'length' => in_array($missingColumn, $storageTable['primaryKey']) ? 255 : null,
                            'convertEmptyValuesToNull' => false,
                        ];

                        if ($row['configuration']['backend'] === 'redshift' &&
                            $row['configuration']['type'] === 'simple'
                        ) {
                            $newColumn['compression'] = '';
                        }
                    } else {
                        $storageColumnMetadata = $storageTable['columnMetadata'][$missingColumn];
                        $storageColumnMetadata = (array) array_combine(
                            array_map(fn($v) => $v['key'], $storageColumnMetadata),
                            array_map(fn($v) => $v['value'], $storageColumnMetadata),
                        );
                        $type = $storageColumnMetadata['KBC.datatype.basetype'] ?? 'VARCHAR';
                        $defaultLength = $type === 'VARCHAR' ? 255 : null;
                        $newColumn = [
                            'type' => $type,
                            'column' => $missingColumn,
                            'length' => $storageColumnMetadata['KBC.datatype.length'] ?? $defaultLength,
                            'convertEmptyValuesToNull' => $storageColumnMetadata['KBC.datatype.nullable'] ?? true,
                        ];
                    }
                    $transformationConfig
                    ['rows']
                    [$rowKey]
                    ['configuration']
                    ['input']
                    [$itemKey]
                    ['datatypes']
                    [$missingColumn] = $newColumn;
                }
            }
        }
        return $transformationConfig;
    }

    private function sortTransformationRows(array $transformationConfig): array
    {
        $rows = [];
        foreach ($transformationConfig['rows'] as $row) {
            $phase = $row['configuration']['phase'] ?? '1';
            $rows[$phase][$row['id']] = $row;
        }
        ksort($rows);

        $result = [];
        foreach ($rows as $phaseRows) {
            $sorter = new StringSort();
            foreach ($phaseRows as $rowId => $row) {
                $requires = $row['configuration']['requires'] ?? [];
                $filteredRequires = array_filter($requires, fn($v) => isset($phaseRows[$v]));
                $sorter->add((string) $rowId, $filteredRequires);
            }
            $phaseResult = $sorter->sort();

            foreach ($phaseResult as $item) {
                $result[] = $phaseRows[$item];
            }
        }

        $transformationConfig['rows'] = $result;
        return $transformationConfig;
    }

    private function isSuitableTransformation(array $row, TransformationV2 $savedTransformation): bool
    {
        if (empty($row['configuration']['input'])) {
            return true;
        }
        $rowInputMappings = (array) array_combine(
            array_map(fn($v) => $v['destination'], $row['configuration']['input']),
            $row['configuration']['input']
        );

        foreach ($savedTransformation->getInputMappingTables() as $savedInputMappingTable) {
            if (isset($rowInputMappings[$savedInputMappingTable['destination']])) {
                $actualInputMappingTable = array_merge(
                    [
                        'changedSince' => null,
                        'whereColumn' => null,
                        'whereValues' => null,
                        'whereOperator' => null,
                    ],
                    $rowInputMappings[$savedInputMappingTable['destination']]
                );
                $savedInputMappingTable = array_merge(
                    [
                        'changed_since' => null,
                        'where_column' => null,
                        'where_values' => null,
                        'where_operator' => null,
                    ],
                    $savedInputMappingTable
                );
                if ($actualInputMappingTable['changedSince'] !== $savedInputMappingTable['changed_since']) {
                    return false;
                }
                if ($actualInputMappingTable['whereColumn'] !== $savedInputMappingTable['where_column']) {
                    return false;
                }
                if ($actualInputMappingTable['whereValues'] !== $savedInputMappingTable['where_values']) {
                    return false;
                }
                if ($actualInputMappingTable['whereOperator'] !== $savedInputMappingTable['where_operator']) {
                    return false;
                }
            }
        }

        return true;
    }
}
