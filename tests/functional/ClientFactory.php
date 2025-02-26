<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\FunctionalTests;

use Keboola\StorageApi\BranchAwareClient;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\DevBranches;

class ClientFactory
{
    public static function createComponentsClient(bool $awareBranch = false): Components
    {
        if ($awareBranch) {
            return new Components(self::createStorageBranchAwareClient());
        }
        return new Components(self::createStorageClient());
    }

    public static function createStorageClient(): Client
    {
        return new Client([
            'url' => getenv('KBC_URL'),
            'token' => getenv('KBC_TOKEN'),
        ]);
    }

    public static function createStorageBranchAwareClient(): BranchAwareClient
    {
        $devBranches = new DevBranches(self::createStorageClient());
        $listBranches = $devBranches->listBranches();
        $defaultBranch = current(array_filter($listBranches, fn($v) => $v['isDefault'] === true));
        return new BranchAwareClient(
            $defaultBranch['id'],
            [
                'url' => getenv('KBC_URL'),
                'token' => getenv('KBC_TOKEN'),
            ],
        );
    }
}
