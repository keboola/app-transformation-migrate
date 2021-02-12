<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Configuration;

use Keboola\Component\Config\BaseConfig;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class Config extends BaseConfig
{
    public function getTransformationId(): int
    {
        return (int) $this->getValue(['parameters', 'transformationId']);
    }

    public function getKbcStorageToken(): string
    {
        $token = getenv('KBC_TOKEN');
        if (!$token) {
            throw new InvalidConfigurationException('"KBC_TOKEN" environment variable must be set.');
        }
        return $token;
    }

    public function getKbcUrl(): string
    {
        $url = getenv('KBC_URL');
        if (!$url) {
            throw new InvalidConfigurationException('"KBC_URL" environment variable must be set.');
        }
        return $url;
    }
}
