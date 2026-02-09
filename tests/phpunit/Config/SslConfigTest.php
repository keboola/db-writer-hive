<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Config;

use Keboola\DbWriter\Configuration\HiveConfigDefinition;
use Keboola\DbWriter\Configuration\Validator;
use Keboola\DbWriter\Exception\UserException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SslConfigTest extends TestCase
{
    /**
     * @dataProvider validSslConfigProvider
     */
    public function testValidSslConfig(array $config): void
    {
        $this->checkConfig($config, new HiveConfigDefinition());
        $this->expectNotToPerformAssertions(); // Assert no error
    }

    /**
     * @dataProvider invalidSslConfigProvider
     */
    public function testInvalidSslConfig(string $expectedMsg, array $config): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage($expectedMsg);
        $this->checkConfig($config, new HiveConfigDefinition());
    }

    public function validSslConfigProvider(): array
    {
        return [
            'ssl-disabled' => [
                [
                    'data_dir' => '...',
                    'writer_class' => '...',
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                        'ssl' => [
                            'enabled' => false,
                        ],
                    ],
                    'tables' => [
                        [
                            'tableId' => 'Table 1',
                            'dbName' => 'table_1',
                            'items' => [
                                [
                                    'name' => 'Col1 Name',
                                    'dbName' => 'Col1 Db Name',
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'ssl-enabled-with-pem-cert' => [
                [
                    'data_dir' => '...',
                    'writer_class' => '...',
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                        'ssl' => [
                            'enabled' => true,
                            'ca' => '-----BEGIN CERTIFICATE-----...-----END CERTIFICATE-----',
                            'caFileType' => 'pem',
                            'verifyServerCert' => true,
                            'ignoreCertificateCn' => false,
                        ],
                    ],
                    'tables' => [
                        [
                            'tableId' => 'Table 1',
                            'dbName' => 'table_1',
                            'items' => [
                                [
                                    'name' => 'Col1 Name',
                                    'dbName' => 'Col1 Db Name',
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'ssl-enabled-with-jks-cert' => [
                [
                    'data_dir' => '...',
                    'writer_class' => '...',
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                        'ssl' => [
                            'enabled' => true,
                            'ca' => base64_encode('fake-jks-content'),
                            'caFileType' => 'jks',
                            'verifyServerCert' => false,
                            'ignoreCertificateCn' => true,
                        ],
                    ],
                    'tables' => [
                        [
                            'tableId' => 'Table 1',
                            'dbName' => 'table_1',
                            'items' => [
                                [
                                    'name' => 'Col1 Name',
                                    'dbName' => 'Col1 Db Name',
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'ssl-enabled-without-cert' => [
                [
                    'data_dir' => '...',
                    'writer_class' => '...',
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                        'ssl' => [
                            'enabled' => true,
                            'verifyServerCert' => false,
                        ],
                    ],
                    'tables' => [
                        [
                            'tableId' => 'Table 1',
                            'dbName' => 'table_1',
                            'items' => [
                                [
                                    'name' => 'Col1 Name',
                                    'dbName' => 'Col1 Db Name',
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function invalidSslConfigProvider(): array
    {
        return [
            'invalid-ca-file-type' => [
                'The value "invalid" is not allowed for path "parameters.db.ssl.caFileType". ' .
                'Permissible values: "pem", "jks"',
                [
                    'data_dir' => '...',
                    'writer_class' => '...',
                    'db' => [
                        'host' => 'host',
                        'port' => '1234',
                        'database' => 'db',
                        'user' => 'admin',
                        '#password' => 'password',
                        'ssl' => [
                            'enabled' => true,
                            'ca' => '-----BEGIN CERTIFICATE-----...-----END CERTIFICATE-----',
                            'caFileType' => 'invalid',
                        ],
                    ],
                    'tables' => [
                        [
                            'tableId' => 'Table 1',
                            'dbName' => 'table_1',
                            'items' => [
                                [
                                    'name' => 'Col1 Name',
                                    'dbName' => 'Col1 Db Name',
                                    'type' => 'integer',
                                ],
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
