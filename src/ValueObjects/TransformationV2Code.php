<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\ValueObjects;

class TransformationV2Code
{
    private string $name = 'New Code';

    private array $scripts = [];

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function addScript(string $script): void
    {
        $this->scripts[] = $script;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getScripts(): array
    {
        return $this->scripts;
    }
}
