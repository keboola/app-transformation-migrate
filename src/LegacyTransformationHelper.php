<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\StorageApi\Client;
use Keboola\TransformationMigrate\ValueObjects\TransformationV2;
use MJS\TopSort\Implementations\StringSort;
use Throwable;

class LegacyTransformationHelper
{
    public static function removeDisableTransformation(array $config): array
    {
        $config['rows'] = array_filter(
            $config['rows'],
            fn(array $row) => !isset($row['configuration']['disabled']) || !$row['configuration']['disabled']
        );
        return $config;
    }

    public static function checkAndCompletionInputMapping(Client $storageApi, array $transformationConfig): array
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
                    $storageTable = $storageApi->getTable($item['source']);
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

    public static function sortTransformationRows(array $transformationConfig): array
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

    public static function isSuitableTransformation(array $row, TransformationV2 $savedTransformation): bool
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
