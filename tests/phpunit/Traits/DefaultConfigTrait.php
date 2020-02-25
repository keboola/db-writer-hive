<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Traits;

trait DefaultConfigTrait
{
    protected function getDefaultConfig(): array
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

    protected function getSshConfig(): array
    {
        return [
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
        ];
    }
}
