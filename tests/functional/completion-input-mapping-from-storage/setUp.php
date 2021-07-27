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
        'column_4',
    ]);

    try {
        $test->getStorageClient()->createBucket('testMigrate', 'in');
    } catch (ClientException $e) {
        $test->getStorageClient()->dropBucket('in.c-testMigrate', ['force' => true]);
        $test->getStorageClient()->createBucket('testMigrate', 'in');
    }
    $test->getStorageClient()->createTable(
        'in.c-testMigrate',
        'inputTable1',
        $file,
        ['primaryKey' => 'column_3']
    );

    $metadata = new Metadata($test->getStorageClient());

    $metadata->postColumnMetadata(
        'in.c-testMigrate.inputTable1.column_2',
        'user',
        [
            [
                'id' => '1236463282',
                'key' => 'KBC.datatype.basetype',
                'value' => 'INTEGER',
                'timestamp' => '2021-07-22T00:30:35+0200',
            ],
            [
                'id' => '1236463283',
                'key' => 'KBC.datatype.length',
                'value' => '2',
                'timestamp' => '2021-07-22T00:30:35+0200',
            ],
        ]
    );
    $metadata->postColumnMetadata(
        'in.c-testMigrate.inputTable1.column_2',
        'keboola.snowflake-transformation',
        [
            [
                'id' => '1236463282',
                'key' => 'KBC.datatype.basetype',
                'value' => 'NUMERIC',
                'timestamp' => '2021-07-22T00:30:35+0200',
            ],
            [
                'id' => '1236463283',
                'key' => 'KBC.datatype.length',
                'value' => '5,0',
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
                        'column_1' => [
                            'column' => 'column_1',
                            'type' => 'VARCHAR',
                            'length' => null,
                            'convertEmptyValuesToNull' => false,
                        ],
                        'column_4' => null,
                    ],
                ],
                [
                    'source' => 'in.c-testMigrate.inputTable1',
                    'destination' => 'test2',
                    'datatypes' => [
                        'column_1' => [
                            'column' => 'column_1',
                            'type' => 'VARCHAR',
                            'length' => null,
                            'convertEmptyValuesToNull' => false,
                        ],
                    ],
                    'columns' => [
                        'column_1',
                    ],
                ],
            ],
        ],
    );

    putenv('TRANSFORMATION_BUCKET_ID=' . $configuration->getConfigurationId());
};
