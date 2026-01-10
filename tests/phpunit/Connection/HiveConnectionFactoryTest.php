<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Connection;

use Keboola\DbWriter\Connection\HiveCertManager;
use Keboola\DbWriter\Connection\HiveConnectionFactory;
use PHPUnit\Framework\TestCase;

class HiveConnectionFactoryTest extends TestCase
{
    public function testCreateDnsWithoutSsl(): void
    {
        $dsn = HiveConnectionFactory::createDns('localhost', 10000, 'default');

        $this->assertStringContainsString('Driver=Cloudera ODBC Driver for Apache Hive 64-bit', $dsn);
        $this->assertStringContainsString('Host=localhost', $dsn);
        $this->assertStringContainsString('Port=10000', $dsn);
        $this->assertStringContainsString('Schema=default', $dsn);
        $this->assertStringContainsString('AuthMech=3', $dsn);
        $this->assertStringContainsString('UseNativeQuery=1', $dsn);
        $this->assertStringNotContainsString('SSL=1', $dsn);
    }

    public function testCreateDnsWithSslParameters(): void
    {
        $sslParams = [
            'SSL' => 1,
            'AllowSelfSignedServerCert' => 0,
            'CAIssuedCertNamesMismatch' => 0,
        ];

        $dsn = HiveConnectionFactory::createDns('localhost', 10000, 'default', $sslParams);

        $this->assertStringContainsString('SSL=1', $dsn);
        $this->assertStringContainsString('AllowSelfSignedServerCert=0', $dsn);
        $this->assertStringContainsString('CAIssuedCertNamesMismatch=0', $dsn);
    }

    public function testCreateDnsWithSslAndTrustedCerts(): void
    {
        $sslParams = [
            'SSL' => 1,
            'TrustedCerts' => '/path/to/ca.pem',
            'AllowSelfSignedServerCert' => 0,
            'CAIssuedCertNamesMismatch' => 0,
        ];

        $dsn = HiveConnectionFactory::createDns('localhost', 10000, 'default', $sslParams);

        $this->assertStringContainsString('SSL=1', $dsn);
        $this->assertStringContainsString('TrustedCerts=/path/to/ca.pem', $dsn);
        $this->assertStringContainsString('AllowSelfSignedServerCert=0', $dsn);
        $this->assertStringContainsString('CAIssuedCertNamesMismatch=0', $dsn);
    }
}
