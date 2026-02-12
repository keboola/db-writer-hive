<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Connection;

use Dibi\Connection;
use Keboola\DbWriter\Exception\UserException;

class HiveConnectionFactory
{
    private const DEFAULT_PORT = 10000;

    private ?HiveCertManager $certManager = null;

    public static function createDns(
        string $host,
        int $port,
        string $database,
        array $sslParams = [],
        array $httpTransportParams = []
    ): string {
        $dsn = sprintf(
            'Driver=%s;Host=%s;Port=%s;Schema=%s;AuthMech=3;UseNativeQuery=1',
            'Cloudera ODBC Driver for Apache Hive 64-bit',
            $host,
            $port,
            $database,
        );

        // Add SSL parameters
        foreach ($sslParams as $key => $value) {
            $dsn .= sprintf(';%s=%s', $key, $value);
        }

        // Add HTTP transport parameters
        foreach ($httpTransportParams as $key => $value) {
            $dsn .= sprintf(';%s=%s', $key, $value);
        }

        return $dsn;
    }

    public function createConnection(array $params): Connection
    {
        // check params
        foreach (['host', 'database', 'user', '#password'] as $r) {
            if (!array_key_exists($r, $params)) {
                throw new UserException(sprintf('Parameter %s is missing.', $r));
            }
        }

        // Create certificate manager for SSL
        $sslConfig = $params['ssl'] ?? null;
        $this->certManager = new HiveCertManager($sslConfig);
        $sslParams = $this->certManager->getDsnParameters();

        // Build transport parameters
        $httpTransportParams = $this->buildTransportParams(
            $params['httpPath'] ?? null,
            $params['thriftTransport'] ?? null,
        );

        $dsn = self::createDns(
            $params['host'],
            isset($params['port']) ? (int) $params['port'] : self::DEFAULT_PORT,
            $params['database'],
            $sslParams,
            $httpTransportParams,
        );

        return new Connection([
            'driver' => HiveOdbcDriver::class,
            'dsn' => $dsn,
            'username' => $params['user'],
            'password' => $params['#password'],
            'database' => $params['database'],
        ]);
    }

    /**
     * @param string|int|null $thriftTransport
     */
    private function buildTransportParams(?string $httpPath, $thriftTransport): array
    {
        $params = [];

        if ($thriftTransport !== null) {
            $params['ThriftTransport'] = $thriftTransport;
        }

        if ($httpPath !== null && $httpPath !== '') {
            // Ensure httpPath starts with /
            if ($httpPath[0] !== '/') {
                $httpPath = '/' . $httpPath;
            }

            $params['HTTPPath'] = $httpPath;
        }

        return $params;
    }
}
