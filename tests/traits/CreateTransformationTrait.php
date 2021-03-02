<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Traits;

use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;

trait CreateTransformationTrait
{
    protected Components $components;

    public function createTransformation(
        Configuration $configuration,
        string $name,
        array $config = []
    ): ConfigurationRow {
        $row = new ConfigurationRow($configuration);
        $row->setName($name)
            ->setConfiguration(
                array_merge([
                    'backend' => 'snowflake',
                    'type' => 'simple',
                    'phase' => 1,
                    'queries' => [
                        'CREATE TABLE "out_table" AS SELECT * FROM "in_table";',
                    ],
                ], $config)
            )
        ;

        $rowResult = $this->components->addConfigurationRow($row);
        $row->setRowId((int) $rowResult['id']);

        return $row;
    }
}
