<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\FunctionalTests;

use Keboola\StorageApi\Components;
use Keboola\TransformationMigrate\Traits\CreateTransformationBucketTrait;
use Keboola\TransformationMigrate\Traits\CreateTransformationTrait;
use Keboola\TransformationMigrate\Traits\GetTransformation;
use Keboola\TransformationMigrate\Traits\RemoveTrasformationTrait;

class TestManager
{
    use CreateTransformationBucketTrait;
    use CreateTransformationTrait;
    use GetTransformation;
    use RemoveTrasformationTrait;

    public const TRANSFORMATION_BUCKET_NAME = 'test_transformation_bucket';

    protected Components $componentsClient;

    public function __construct(Components $components)
    {
        $this->componentsClient = $components;
    }
}
