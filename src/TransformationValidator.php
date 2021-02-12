<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

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
        $this->validateBackend();
        $this->validatePhases();
    }

    private function validateBackend(): void
    {
        $backends = array_map(
            fn(array $v) => sprintf('%s-%s', $v['configuration']['backend'], $v['configuration']['type']),
            $this->config['rows']
        );
        $uniqueBackends = array_unique($backends);

        if (count($uniqueBackends) > 1) {
            throw new CheckConfigException(sprintf(
                'Transformations in the bucket "%s" don\'t have the same backend.',
                $this->getTransformationName()
            ));
        }
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
}
