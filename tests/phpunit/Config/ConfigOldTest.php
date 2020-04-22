<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Config;

use Keboola\DbWriter\Configuration\HiveConfigDefinition;
use Keboola\DbWriter\Configuration\Validator;
use Keboola\DbWriter\Exception\UserException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigOldTest extends TestCase
{
    /**
     * @dataProvider validConfigProvider
     */
    public function testValidConfig(array $config): void
    {
        $this->checkConfig($config, new HiveConfigDefinition());
        $this->expectNotToPerformAssertions(); // Assert no error
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testInvalidConfig(string $expectedMsg, array $config): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage($expectedMsg);
        $this->checkConfig($config, new HiveConfigDefinition());
    }

    public function validConfigProvider(): array
    {
        return [
            'minimal' => [
                [
                    'data_dir' => '...',     # added by HiveApplication
                    'writer_class' => '...', # added by HiveApplication
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                    ],
                    'tables' => [],
                ],
            ],
            'full' => [
                [
                    'data_dir' => '...',     # added by HiveApplication
                    'writer_class' => '...', # added by HiveApplication
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                    ],
                    'tables' => [
                        [
                            'tableId' => 'Table 1',
                            'dbName' => 'table_1',
                            'incremental' => true,
                            'export' => true,
                            'primaryKey' => ['id'],
                            'items' => [
                                [
                                    'name' => 'Col1 Name',
                                    'dbName' => 'Col1 Db Name',
                                    'type' => 'varchar',
                                    'size' => '20',
                                    'nullable' => true,
                                    'default' => '',
                                ],
                                [
                                    'name' => 'Col2 Name',
                                    'dbName' => 'Col2 Db Name',
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                        [
                            'tableId' => 'Table 2',
                            'dbName' => 'table_2',
                            'items' => [
                                [
                                    'name' => 'Col1 Name',
                                    'dbName' => 'Col1 Db Name',
                                    'type' => 'varchar',
                                    'size' => '20',
                                    'nullable' => true,
                                    'default' => '',
                                ],
                                [
                                    'name' => 'Col2 Name',
                                    'dbName' => 'Col2 Db Name',
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function invalidConfigProvider(): array
    {
        return [
            'empty' => [
                'The child node "data_dir" at path "parameters" must be configured.',
                [],
            ],
            'empty-items' =>  [
                'The path "parameters.tables.0.items" should have at least 1 element(s) defined.',
                [
                    'data_dir' => '...',     # added by HiveApplication
                    'writer_class' => '...', # added by HiveApplication
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                    ],
                    'tables' => [
                        [
                            'tableId' => 'Table 1',
                            'dbName' => 'table_1',
                            'items' => [

                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function checkConfig(array $config, ConfigurationInterface $definition): void
    {
        Validator::getValidator($definition)($config);
    }
}
