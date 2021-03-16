<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\ValueObjects;

class TransformationV2
{
    /** @var TransformationV2Block[] $blocks */
    private $blocks;

    private string $name;

    private string $type;

    private string $backend;

    private array $inputMappingTables = [];

    private array $outputMappingTables = [];

    public function __construct(string $name, string $type, string $backend)
    {
        $this->name = $name;
        $this->type = $type;
        $this->backend = $backend;
    }

    public function addBlock(TransformationV2Block $block): void
    {
        $this->blocks[] = $block;
    }

    public function addInputMappingTable(array $inputMappingTable): void
    {
        $this->inputMappingTables[$inputMappingTable['source']] = $inputMappingTable;
    }

    public function addOutputMappingTable(array $outputMappingTable): void
    {
        $this->outputMappingTables[$outputMappingTable['destination']] = $outputMappingTable;
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
}
