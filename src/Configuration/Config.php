<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Configuration;

use InvalidArgumentException;
use Keboola\Component\Config\BaseConfig;
use Keboola\Component\UserException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class Config extends BaseConfig
{
    private const SNOWFLAKE_COMPONENT_ID = 'keboola.snowflake-transformation';

    private const PYTHON_COMPONENT_ID = 'keboola.python-transformation-v2';

    private const REDSHIFT_COMPONENT_ID = 'keboola.redshift-transformation';

    private const R_COMPONENT_ID = 'keboola.r-transformation-v2';

    private const TRANSFORMATION_TYPE_SNOWFLAKE = 'snowflake-simple';

    private const TRANSFORMATION_TYPE_REDSHIFT = 'redshift-simple';

    private const TRANSFORMATION_TYPE_PYTHON = 'docker-python';

    private const TRANSFORMATION_TYPE_R = 'docker-r';

    public static function getComponentId(string $transformationTypeKey): string
    {
        switch ($transformationTypeKey) {
            case Config::TRANSFORMATION_TYPE_SNOWFLAKE:
                return self::SNOWFLAKE_COMPONENT_ID;
            case Config::TRANSFORMATION_TYPE_PYTHON:
                return self::PYTHON_COMPONENT_ID;
            case Config::TRANSFORMATION_TYPE_R:
                return self::R_COMPONENT_ID;
            case Config::TRANSFORMATION_TYPE_REDSHIFT:
                return self::REDSHIFT_COMPONENT_ID;
            default:
                throw new UserException(
                    sprintf('Unsupported backend type "%s".', $transformationTypeKey),
                );
        }
    }

    public function hasTransformationId(): bool
    {
        try {
            $this->getValue(['parameters', 'transformationId']);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public function getTransformationId(): string
    {
        // @phpstan-ignore-next-line
        return (string) $this->getValue(['parameters', 'transformationId']);
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
