<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Connection;

use Dibi\Connection;
use Keboola\DbWriter\Exception\UserException;

class HiveConnectionFactory
{
    private const DEFAULT_PORT = 10000;

    public static function createDns(string $host, int $port, string $database): string
    {
        return sprintf(
            'Driver=%s;Host=%s;Port=%s;Schema=%s;AuthMech=3;UseNativeQuery=1',
            'MapR Hive ODBC Connector 64-bit',
            $host,
            $port,
            $database,
        );
    }

    public static function createDnsFromParams(array $params): string
    {
        // check params
        foreach (['host', 'database', 'user', '#password'] as $r) {
            if (!array_key_exists($r, $params)) {
                throw new UserException(sprintf('Parameter %s is missing.', $r));
            }
        }

        return self::createDns(
            $params['host'],
            isset($params['port']) ? (int) $params['port'] : self::DEFAULT_PORT,
            $params['database'],
        );
    }

    public function createConnection(array $params): Connection
    {
        return new Connection([
            'driver' => HiveOdbcDriver::class,
            'dsn' => self::createDnsFromParams($params),
            'username' => $params['user'],
            'password' => $params['#password'],
            'database' => $params['database'],
        ]);
    }
}
