<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Connection;

use Keboola\DbWriter\Connection\HiveCertManager;
use Keboola\DbWriter\Connection\HiveConnectionFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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

    public function testCreateDnsWithHttpTransport(): void
    {
        $httpTransportParams = [
            'TransportMode' => 'http',
            'HTTPPath' => '/gateway/analytics-adhoc-query/hive',
        ];

        $dsn = HiveConnectionFactory::createDns('localhost', 8443, 'default', [], $httpTransportParams);

        $this->assertStringContainsString('Host=localhost', $dsn);
        $this->assertStringContainsString('Port=8443', $dsn);
        $this->assertStringContainsString('TransportMode=http', $dsn);
        $this->assertStringContainsString('HTTPPath=/gateway/analytics-adhoc-query/hive', $dsn);
    }

    public function testCreateDnsWithSslAndHttpTransport(): void
    {
        $sslParams = [
            'SSL' => 1,
            'AllowSelfSignedServerCert' => 0,
        ];
        $httpTransportParams = [
            'TransportMode' => 'http',
            'HTTPPath' => '/gateway/pipelines-adhoc-query/hive',
        ];

        $dsn = HiveConnectionFactory::createDns(
            'hive.example.com',
            8443,
            'default',
            $sslParams,
            $httpTransportParams,
        );

        $this->assertStringContainsString('Host=hive.example.com', $dsn);
        $this->assertStringContainsString('Port=8443', $dsn);
        $this->assertStringContainsString('SSL=1', $dsn);
        $this->assertStringContainsString('TransportMode=http', $dsn);
        $this->assertStringContainsString('HTTPPath=/gateway/pipelines-adhoc-query/hive', $dsn);
    }

    public function testBuildHttpTransportParamsAddsSlash(): void
    {
        $factory = new HiveConnectionFactory();
        $reflection = new ReflectionClass($factory);
        $method = $reflection->getMethod('buildHttpTransportParams');
        $method->setAccessible(true);

        $result = $method->invoke($factory, 'gateway/test/hive');

        $this->assertSame('http', $result['TransportMode']);
        $this->assertSame('/gateway/test/hive', $result['HTTPPath']);
    }

    public function testBuildHttpTransportParamsEmptyReturnsEmpty(): void
    {
        $factory = new HiveConnectionFactory();
        $reflection = new ReflectionClass($factory);
        $method = $reflection->getMethod('buildHttpTransportParams');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke($factory, null));
        $this->assertSame([], $method->invoke($factory, ''));
    }
}
