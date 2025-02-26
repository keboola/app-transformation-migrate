<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\ValueObjects;

class TransformationV2Block
{
    private int $phase;

    public function __construct(int $phase)
    {
        $this->phase = $phase;
    }

    /** @var TransformationV2Code[] $codes */
    private array $codes = [];

    public function addCode(TransformationV2Code $code): void
    {
        $this->codes[] = $code;
    }

    public function getName(): string
    {
        return sprintf('Phase %s', $this->phase);
    }

    public function getPhase(): int
    {
        return $this->phase;
    }

    /**
     * @return TransformationV2Code[]
     */
    public function getCodes(): array
    {
        return $this->codes;
    }
}
