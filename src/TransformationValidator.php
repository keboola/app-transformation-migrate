<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\TransformationMigrate\Configuration\Config;
use Keboola\TransformationMigrate\Exception\CheckConfigException;

class TransformationValidator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function validate(): void
    {
        $this->validateBucketRows();
        $this->validatePhases();
    }

    private function validatePhases(): void
    {
        $phases = array_map(fn(array $v) => $v['configuration']['phase'], $this->config['rows']);
        $uniquePhases = array_unique($phases);

        if (count($uniquePhases) > 1) {
            throw new CheckConfigException(sprintf(
                'Transformations in the bucket "%s" don\'t have the same phase.',
                $this->getTransformationName()
            ));
        }
    }

    private function getTransformationName(): string
    {
        return $this->config['name'];
    }

    private function validateBucketRows(): void
    {
        if (count($this->config['rows']) === 0) {
            throw new CheckConfigException(sprintf(
                'Transformation bucket "%s" is empty.',
                $this->getTransformationName()
            ));
        }
    }
}
