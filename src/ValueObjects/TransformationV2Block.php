<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\ValueObjects;

class TransformationV2Block
{
    private string $name = 'Block';

    /** @var TransformationV2Code[] $codes */
    private $codes = [];

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function addCode(TransformationV2Code $code): void
    {
        $this->codes[] = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return TransformationV2Code[]
     */
    public function getCodes(): array
    {
        return $this->codes;
    }
}
