<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests;

use Keboola\DbWriter\Connection\HiveConnectionFactory;
use Keboola\DbWriter\Tests\Traits\ConnectionFactoryTrait;
use Keboola\DbWriter\Tests\Traits\CreateApplicationTrait;
use Keboola\DbWriter\Tests\Traits\DefaultConfigTrait;
use Keboola\DbWriter\Tests\Traits\SshKeysTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class GetTablesInfoTest extends TestCase
{
    use CreateApplicationTrait;
    use SshKeysTrait;
    use DefaultConfigTrait;
    use ConnectionFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->createConnection();
        $connection->query('CREATE TEMPORARY TABLE product (product_name string, price double, comment string)');
        $connection->query(
            'CREATE TEMPORARY TABLE special_types (' .
            'id int, bin binary, ' .
            '`map` Map<int, string>, ' .
            '`array` Array<string>, ' .
            '`union` Uniontype<int, string>, ' .
            '`struct` Struct<age: int, name: string>)'
        );
    }

    protected function tearDown(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }

    public function testSuccessfullyRun(): void
    {
        $config = $this->getDefaultConfig();
        $config['action'] = 'getTablesInfo';
        $result = json_decode($this->createApplication($config)->run(), true);
        $this->assertEquals([
            'status' => 'success',
            'tables' => [
                'product' => [
                    'columns' => [
                        [
                            'COLUMN_NAME' => 'product_name',
                            'DATA_TYPE' => 'STRING',

                        ],
                        [
                            'COLUMN_NAME' => 'price',
                            'DATA_TYPE' => 'DOUBLE',

                        ],
                        [
                            'COLUMN_NAME' => 'comment',
                            'DATA_TYPE' => 'STRING',

                        ],
                    ],
                ],
                'special_types' => [
                    'columns' => [
                        [
                            'COLUMN_NAME' => 'id',
                            'DATA_TYPE' => 'INT',
                        ],
                        [
                            'COLUMN_NAME' => 'bin',
                            'DATA_TYPE' => 'BINARY',

                        ],
                        [
                            'COLUMN_NAME' => 'map',
                            'DATA_TYPE' => 'STRING',

                        ],
                        [
                            'COLUMN_NAME' => 'array',
                            'DATA_TYPE' => 'STRING',
                        ],
                        [
                            'COLUMN_NAME' => 'union',
                            'DATA_TYPE' => 'STRING',

                        ],
                        [
                            'COLUMN_NAME' => 'struct',
                            'DATA_TYPE' => 'STRING',
                        ],
                    ],
                ],
            ],
        ], $result);
    }
}
