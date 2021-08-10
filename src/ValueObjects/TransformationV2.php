<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\ValueObjects;

use Keboola\TransformationMigrate\Configuration\Config;

class TransformationV2
{
    /** @var TransformationV2Block[] $blocks */
    private array $blocks = [];

    private string $name;

    private int $phase;

    private array $descriptions = [];

    private string $type;

    private string $backend;

    private array $inputMappingTables = [];

    private array $outputMappingTables = [];

    private array $packages = [];

    private array $fileTags = [];

    public function __construct(string $name, string $type, string $backend, int $phase)
    {
        $this->name = $name;
        $this->type = $type;
        $this->backend = $backend;
        $this->phase = $phase;
    }

    public function addBlock(TransformationV2Block $block): void
    {
        $this->blocks[] = $block;
    }

    public function addInputMappingTable(array $inputMappingTable): void
    {
        $newInputMappingTable = $this->replaceInputMappingValues($inputMappingTable);
        $renamedInputMapping = $this->renameInputMappingKeys($newInputMappingTable);
        if (isset($this->inputMappingTables[$inputMappingTable['destination']])) {
            $savedInputMapping = $this->inputMappingTables[$inputMappingTable['destination']];
            $renamedInputMapping['column_types'] = $this->mergeInputMappingColumnTypes(
                $savedInputMapping['column_types'] ?? [],
                $renamedInputMapping['column_types'] ?? []
            );
            $renamedInputMapping['columns'] = $this->mergeInputMappingColumns(
                $savedInputMapping['columns'] ?? [],
                $renamedInputMapping['columns'] ?? []
            );
        }
        $this->inputMappingTables[$inputMappingTable['destination']] = $renamedInputMapping;
    }

    public function addOutputMappingTable(array $outputMappingTable): void
    {
        $this->outputMappingTables[$outputMappingTable['source']] =
            $this->renameOutputMappingKeys($outputMappingTable);
    }

    public function addPackages(array $packages): void
    {
        array_walk($packages, function (string $v): void {
            if (!in_array($v, $this->packages)) {
                $this->packages[] = $v;
            }
        });
    }

    public function addFileTags(array $fileTags): void
    {
        array_walk($fileTags, function (string $v): void {
            if (!in_array($v, $this->fileTags)) {
                $this->fileTags[] = $v;
            }
        });
    }

    public function addDescription(string $description): self
    {
        $this->descriptions[] = $description;
        return $this;
    }

    public function hasPackages(): bool
    {
        return $this->packages !== [];
    }

    public function hasFileTags(): bool
    {
        return $this->fileTags !== [];
    }

    public function getPackages(): array
    {
        return $this->packages;
    }

    public function getFileTags(): array
    {
        return $this->fileTags;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getInputMappingTables(): array
    {
        return $this->inputMappingTables;
    }

    public function getOutputMappingTables(): array
    {
        return $this->outputMappingTables;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getBackend(): string
    {
        return $this->backend;
    }

    public function getComponentId(): string
    {
        return Config::getComponentId(sprintf(
            '%s-%s',
            $this->getBackend(),
            $this->getType()
        ));
    }

    public function getPhase(): int
    {
        return $this->phase;
    }

    public function getBlockByPhase(int $phase): ?TransformationV2Block
    {
        foreach ($this->getBlocks() as $block) {
            if ($block->getPhase() === $phase) {
                return $block;
            }
        }

        return null;
    }

    public function getDescription(): string
    {
        return implode("\n\n", $this->descriptions);
    }

    private function replaceInputMappingValues(array $inputMappingTable): array
    {
        if (isset($inputMappingTable['loadType']) && $inputMappingTable['loadType'] === 'clone') {
            $inputMappingTable['datatypes'] = [];
            $inputMappingTable['columns'] = [];
        }
        return $inputMappingTable;
    }

    private function renameInputMappingKeys(array $inputMappingTable, ?string $keyPrefix = null): array
    {
        $result = [];
        foreach ($inputMappingTable as $k => $v) {
            if ($keyPrefix) {
                $switchKey = sprintf('%s-%s', $keyPrefix, $k);
            } else {
                $switchKey = $k;
            }
            switch ($switchKey) {
                case 'loadType': // skip this config
                    continue 2;
                case 'changedSince':
                    $result['changed_since'] = $v;
                    break;
                case 'whereColumn':
                    $result['where_column'] = $v;
                    break;
                case 'whereValues':
                    $result['where_values'] = $v;
                    break;
                case 'whereOperator':
                    $result['where_operator'] = $v;
                    break;
                case 'datatypes':
                    $columnTypes = [];
                    foreach ($v as $item) {
                        if (is_array($item)) {
                            $columnTypes[] = $this->renameInputMappingKeys($item, $k);
                        } else {
                            $columnTypes[] = $item;
                        }
                    }
                    $result['column_types'] = $columnTypes;
                    break;
                case 'datatypes-convertEmptyValuesToNull':
                    $result['convert_empty_values_to_null'] = (bool) $v;
                    break;
                case 'datatypes-column':
                    $result['source'] = $v;
                    break;
                default:
                    $result[$k] = $v;
            }
        }
        return $result;
    }

    private function renameOutputMappingKeys(array $outputMappingTable): array
    {
        $result = [];
        foreach ($outputMappingTable as $k => $v) {
            switch ($k) {
                case 'primaryKey':
                    $result['primary_key'] = $v;
                    break;
                case 'deleteWhereColumn':
                    $result['delete_where_column'] = $v;
                    break;
                case 'deleteWhereOperator':
                    $result['delete_where_operator'] = $v;
                    break;
                case 'deleteWhereValues':
                    $result['delete_where_values'] = $v;
                    break;
                default:
                    $result[$k] = $v;
            }
        }
        return $result;
    }

    private function mergeInputMappingColumnTypes(array $savedColumns, array $inputMappingColumns): array
    {
        if ($savedColumns === [] || $inputMappingColumns === []) {
            return [];
        }

        $listOfSavedColumns = array_map(fn($v) => $v['source'], $savedColumns);
        foreach ($inputMappingColumns as $inputMappingColumn) {
            if (!in_array($inputMappingColumn['source'], $listOfSavedColumns)) {
                $savedColumns[] = $inputMappingColumn;
            }
        }

        return $savedColumns;
    }

    private function mergeInputMappingColumns(array $savedColumns, array $inputMappingColumns): array
    {
        if ($savedColumns === [] || $inputMappingColumns === []) {
            return [];
        }

        return array_keys(array_merge(
            array_flip($savedColumns),
            array_flip($inputMappingColumns)
        ));
    }
}
