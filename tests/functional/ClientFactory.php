<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\FunctionalTests;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;

class ClientFactory
{
    public static function createComponentsClient(): Components
    {
        return new Components(self::createStorageClient());
    }

    public static function createStorageClient(): Client
    {
        return new Client([
            'url' => getenv('KBC_URL'),
            'token' => getenv('KBC_TOKEN'),
        ]);
    }
}
