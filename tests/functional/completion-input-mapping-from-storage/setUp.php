<?php

declare(strict_types=1);

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Metadata;
use Keboola\Temp\Temp;
use Keboola\TransformationMigrate\FunctionalTests\DatadirTest;
use Keboola\TransformationMigrate\FunctionalTests\TestManager;

return function (DatadirTest $test): void {
    $manager = new TestManager($test->getComponentsClient());
    $configuration = $manager->createBucket();

    $tmp = new Temp();
    $file = new CsvFile($tmp->getTmpFolder() . '/inputTable1.csv');
    $file->writeRow([
        'column_1',
        'column_2',
        'column_3',
    ]);

    try {
        $test->getStorageClient()->createBucket('testMigrate', 'in');
    } catch (ClientException $e) {
        $test->getStorageClient()->dropBucket('in.c-testMigrate', ['force' => true]);
        $test->getStorageClient()->createBucket('testMigrate', 'in');
    }
    $test->getStorageClient()->createTable('in.c-testMigrate', 'inputTable1', $file);

    $metadata = new Metadata($test->getStorageClient());

    $metadata->postColumnMetadata(
        'in.c-testMigrate.inputTable1.column_2',
        'user',
        [
            [
                'id' => '1236463282',
                'key' => 'KBC.datatype.basetype',
                'value' => 'INTEGER',
                'provider' => 'user',
                'timestamp' => '2021-07-22T00:30:35+0200',
            ],
            [
                'id' => '1236463283',
                'key' => 'KBC.datatype.length',
                'value' => '2',
                'provider' => 'user',
                'timestamp' => '2021-07-22T00:30:35+0200',
            ],
        ]
    );

    $manager->createTransformation(
        $configuration,
        'snflk row',
        null,
        [
            'input' => [
                [
                    'source' => 'in.c-testMigrate.inputTable1',
                    'destination' => 'in.c-testMigrate.inputTable1',
                    'datatypes' => [
                        'age' => [
                            'column' => 'column_1',
                            'type' => 'VARCHAR',
                            'length' => null,
                            'convertEmptyValuesToNull' => false,
                        ],
                    ],
                ],
            ],
        ],
    );

    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
