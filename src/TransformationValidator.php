<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate;

use Keboola\Component\UserException;
use Keboola\TransformationMigrate\Configuration\Config;
use Keboola\TransformationMigrate\Exception\CheckConfigException;

class TransformationValidator
{
    private const REQUIRED_ROWS_CONFIG = ['phase', 'backend', 'type', 'queries'];

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function validate(): void
    {
        $this->validateSupportedBackends();
        $this->validateBucketRows();
        $this->validatePhases();
    }

    private function validatePhases(): void
    {
        $phases = array_map(fn(array $v) => $v['configuration']['phase'], $this->config['rows']);
        $backends = array_map(
            fn(array $v) => sprintf('%s-%s', $v['configuration']['backend'], $v['configuration']['type']),
            $this->config['rows']
        );

        $uniqueBackends = array_unique($backends);
        $uniquePhases = array_unique($phases);

        if (count($uniquePhases) > 1 && count($uniqueBackends) > 1) {
            throw new CheckConfigException(sprintf(
                'Cannot migrate transformations in the bucket "%s" with multiple backends and phases.',
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

        array_walk($this->config['rows'], function ($v): void {
            $missingConfig = [];
            foreach (self::REQUIRED_ROWS_CONFIG as $item) {
                if (!isset($v['configuration'][$item])) {
                    $missingConfig[] = $item;
                }
            }
            if ($missingConfig) {
                throw new CheckConfigException(sprintf(
                    'Transformation "%s" is empty. Please add queries or mapping to continue with migration.',
                    $v['name']
                ));
            }
        });
    }

    private function validateSupportedBackends(): void
    {
        try {
            array_map(
                fn(array $v) => Config::getComponentId(
                    sprintf('%s-%s', $v['configuration']['backend'], $v['configuration']['type'])
                ),
                $this->config['rows']
            );
        } catch (UserException $e) {
            throw new CheckConfigException($e->getMessage(), 0, $e);
        }
    }
}
