<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Tests;

use Generator;
use Keboola\TransformationMigrate\Exception\CheckConfigException;
use Keboola\TransformationMigrate\TransformationValidator;
use PHPUnit\Framework\TestCase;

class TransformationValidatorTest extends TestCase
{
    /**
     * @dataProvider validConfigDataProvider
     */
    public function testValidConfig(array $config): void
    {
        $transformationValidator = new TransformationValidator($config);
        $transformationValidator->validate();

        $this->expectNotToPerformAssertions();
    }

    /**
     * @dataProvider invalidConfigDataProvider
     */
    public function testInvalidConfig(array $config, string $message): void
    {
        $transformationValidator = new TransformationValidator($config);

        $this->expectException(CheckConfigException::class);
        $this->expectExceptionMessage($message);
        $transformationValidator->validate();
    }

    public function validConfigDataProvider(): Generator
    {
        yield [
            [
                'rows' => [
                    [
                        'configuration' => [
                            'name' => 'test 2',
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '1',
                        ],
                    ],
                ],
            ],
        ];

        yield [
            [
                'rows' => [
                    [
                        'configuration' => [
                            'name' => 'test 1',
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '1',
                        ],
                    ],
                    [
                        'configuration' => [
                            'name' => 'test 2',
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '1',
                        ],
                    ],
                ],
            ],
        ];

        yield [
            [
                'rows' => [
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '1',
                        ],
                    ],
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'docker',
                            'type' => 'python',
                            'phase' => '1',
                        ],
                    ],
                ],
            ],
        ];

        yield [
            [
                'name' => 'test transformation bucket',
                'rows' => [
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '1',
                        ],
                    ],
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'snowflake',
                            'type' => 'simple2',
                            'phase' => '1',
                        ],
                    ],
                ],
            ],
        ];

        yield [
            [
                'name' => 'test transformation bucket',
                'rows' => [
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '1',
                        ],
                    ],
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '2',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function invalidConfigDataProvider(): Generator
    {
        yield [
            [
                'name' => 'test bucket',
                'rows' => [
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'snowflake',
                            'type' => 'simple',
                            'phase' => '1',
                        ],
                    ],
                    [
                        'name' => 'test 2',
                        'configuration' => [
                            'backend' => 'docker',
                            'type' => 'python',
                            'phase' => '2',
                        ],
                    ],
                ],
            ],
            'Cannot migrate transformations in the bucket "test bucket" with multiple backends and phases.',
        ];
    }
}
