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
        $transformationConfig = $this->sortByDependencies($transformationConfig);
        return $transformationConfig;
    }

    public function migrateTransformationConfig(array $transformationConfig): array
    {
        $transformationConfigRows = $transformationConfig['rows'];

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
                if (!empty($transformationConfig['description'])) {
                    $transformationsV2[$transformationKey]->addDescription($transformationConfig['description']);
                }
            }

            $this->processRow($row, $transformationsV2[$transformationKey]);
        }

        $result = [];
        foreach ($transformationsV2 as $transformationTypeKey => $transformationV2) {
            $newConfig = $this->createTransformationConfig(
                $transformationTypeKey,
                $transformationV2->getName(),
                $transformationV2->getDescription(),
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
                $transformationColumnsDatatype = array_map(fn($v) => $v['column'], $item['datatypes']);

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

    private function sortByDependencies(array $transformationConfig): array
    {
        $rows = [];
        foreach ($transformationConfig['rows'] as $row) {
            $rows[$row['configuration']['phase']][$row['id']] = $row;
        }
        ksort($rows);

        $result = [];
        foreach ($rows as $phaseRows) {
            $phaseResult = [];
            $writeOutputRow = function (?array $writeBeforeValues = null, array $row) use (&$phaseResult): void {
                if (!$writeBeforeValues) {
                    $phaseResult[] = $row;
                    return;
                }
                $beforeKey = null;
                foreach ($writeBeforeValues as $writeBeforeValue) {
                    $actualKey = array_search($writeBeforeValue, array_map(fn($v) => $v['id'], $phaseResult));
                    if ($actualKey === false) {
                        continue;
                    }
                    $beforeKey = is_null($beforeKey) ? $actualKey : min($beforeKey, $actualKey);
                }

                if (is_null($beforeKey)) {
                    $phaseResult[] = $row;
                    return;
                }

                for ($i = count($phaseResult); $i > $beforeKey; $i--) {
                    $phaseResult[$i] = $phaseResult[$i-1];
                    unset($phaseResult[$i-1]);
                }

                $phaseResult[$beforeKey] = $row;
                ksort($phaseResult);
            };

            $dependentSettings = false;
            foreach ($phaseRows as $phaseRow) {
                if (!$dependentSettings) {
                    $dependentSettings = !empty($phaseRow['configuration']['requires']);
                }
                $writeOutputRow($phaseRow['configuration']['requires'] ?? null, $phaseRow);
            }

            $result = array_merge(
                $result,
                $dependentSettings ? array_reverse($phaseResult) : $phaseResult
            );
        }

        $transformationConfig['rows'] = $result;
        return $transformationConfig;
    }
}
