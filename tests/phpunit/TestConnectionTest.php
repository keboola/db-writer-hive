<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests;

use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriter\Tests\Traits\CreateApplicationTrait;
use Keboola\DbWriter\Tests\Traits\SshKeysTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TestConnectionTest extends TestCase
{
    use CreateApplicationTrait;
    use SshKeysTrait;

    protected function tearDown(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }

    /**
     * @dataProvider validConfigProvider
     */
    public function testSuccessfullyConnection(array $config): void
    {
        $config['action'] = 'testConnection';
        $result = json_decode($this->createApplication($config)->run(), true);
        $this->assertEquals(['status' => 'success'], $result);
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testFailedConnection(string $expectedExceptionMessage, array $config): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $config['action'] = 'testConnection';
        $this->createApplication($config)->run();
    }

    public function validConfigProvider(): array
    {
        return [
            'valid-config' => [
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'valid-config-ssh' => [
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('SSH_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                            'ssh' => [
                                'enabled' => true,
                                'sshHost' => getenv('SSH_HOST'),
                                'sshPort' => (int) getenv('SSH_PORT'),
                                'user' => getenv('SSH_USER'),
                                'keys' => [
                                    'public' => $this->getPublicKey(),
                                    '#private'=> $this->getPrivateKey(),
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
            'invalid-host' => [
                'Error connecting to DB: [MapR][DriverSupport] (1110) ' .
                'Unexpected response received from server. ' .
                'Please ensure the server host and port specified for the connection are correct ' .
                'and confirm if SSL should be enabled for the connection.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'invalid-host.local',
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-port' => [
                'Error connecting to DB: [MapR][DriverSupport] (1110) ' .
                'Unexpected response received from server. ' .
                'Please ensure the server host and port specified for the connection are correct ' .
                'and confirm if SSL should be enabled for the connection.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => 12345,
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-database' => [
                'Error connecting to DB: [MapR][Hardy] (101) ' .
                'Incorrect database notfound specified. Please check your configuration.' ,
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => 'notFound',
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-user' => [
                'Error connecting to DB: [MapR][ThriftExtension] (2) ' .
                'Error occured during authentication.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => 'invalidUser',
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-password' => [
                'Error connecting to DB: [MapR][ThriftExtension] (2) ' .
                'Error occured during authentication.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => 'invalidPassword',
                        ],
                    ],
                ],
            ],
        ];
    }
}
