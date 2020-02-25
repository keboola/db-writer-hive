<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests;

use Dibi\Connection;
use Keboola\DbWriter\Connection\HiveConnectionFactory;
use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriter\Tests\Traits\CreateApplicationTrait;
use Keboola\DbWriter\Tests\Traits\SshKeysTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class GetTablesInfoTest extends TestCase
{
    use CreateApplicationTrait;
    use SshKeysTrait;

    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $connectionFactory = new HiveConnectionFactory();
        $this->db = $connectionFactory->createConnection($this->getConfig()['parameters']['db']);
        $this->db->query('CREATE TEMPORARY TABLE product (product_name string, price double, comment string)');
        $this->db->query(
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
        $config = $this->getConfig();
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

    private function getConfig(): array
    {
        return [
            'parameters' => [
                'db' => [
                    'host' => getenv('HIVE_DB_HOST'),
                    'port' => (int) getenv('HIVE_DB_PORT'),
                    'database' => getenv('HIVE_DB_DATABASE'),
                    'user' => getenv('HIVE_DB_USER'),
                    '#password' => getenv('HIVE_DB_PASSWORD'),
                ],
            ],
        ];
    }
}
