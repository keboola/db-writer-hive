<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Connection;

use Keboola\DbWriter\Connection\HiveCertManager;
use PHPUnit\Framework\TestCase;

class HiveCertManagerTest extends TestCase
{
    public function testGetDsnParametersWithoutSsl(): void
    {
        $certManager = new HiveCertManager(null);
        $params = $certManager->getDsnParameters();

        $this->assertEmpty($params);
    }

    public function testGetDsnParametersWithSslDisabled(): void
    {
        $certManager = new HiveCertManager(['enabled' => false]);
        $params = $certManager->getDsnParameters();

        $this->assertEmpty($params);
    }

    public function testGetDsnParametersWithSslEnabled(): void
    {
        $sslConfig = [
            'enabled' => true,
            'verifyServerCert' => true,
            'ignoreCertificateCn' => false,
        ];

        $certManager = new HiveCertManager($sslConfig);
        $params = $certManager->getDsnParameters();

        $this->assertArrayHasKey('SSL', $params);
        $this->assertEquals(1, $params['SSL']);
        $this->assertArrayHasKey('AllowSelfSignedServerCert', $params);
        $this->assertEquals(0, $params['AllowSelfSignedServerCert']);
        $this->assertArrayHasKey('CAIssuedCertNamesMismatch', $params);
        $this->assertEquals(0, $params['CAIssuedCertNamesMismatch']);
    }

    public function testGetDsnParametersWithSslEnabledAndDisabledVerification(): void
    {
        $sslConfig = [
            'enabled' => true,
            'verifyServerCert' => false,
            'ignoreCertificateCn' => true,
        ];

        $certManager = new HiveCertManager($sslConfig);
        $params = $certManager->getDsnParameters();

        $this->assertEquals(1, $params['SSL']);
        $this->assertEquals(1, $params['AllowSelfSignedServerCert']);
        $this->assertEquals(1, $params['CAIssuedCertNamesMismatch']);
    }

    public function testGetDsnParametersWithPemCertificate(): void
    {
        $sslConfig = [
            'enabled' => true,
            'ca' => '-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAKL0UG+mRKGzMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNV
BAYTAkFVMRMwEQYDVQQIDApTb21lLVN0YXRlMSEwHwYDVQQKDBhJbnRlcm5ldCBX
aWRnaXRzIFB0eSBMdGQwHhcNMTcwODIzMjMyMjE4WhcNMjcwODIxMjMyMjE4WjBF
-----END CERTIFICATE-----',
            'caFileType' => 'pem',
            'verifyServerCert' => true,
            'ignoreCertificateCn' => false,
        ];

        $certManager = new HiveCertManager($sslConfig);
        $params = $certManager->getDsnParameters();

        $this->assertArrayHasKey('TrustedCerts', $params);
        $this->assertFileExists($params['TrustedCerts']);
        $this->assertStringContainsString('ca-bundle.pem', $params['TrustedCerts']);

        // Verify content
        $this->assertEquals($sslConfig['ca'], file_get_contents($params['TrustedCerts']));

        // Cleanup
        unset($certManager);
    }

    public function testCertificateCleanupOnDestruct(): void
    {
        $sslConfig = [
            'enabled' => true,
            'ca' => '-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAKL0UG+mRKGzMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNV
BAYTAkFVMRMwEQYDVQQIDApTb21lLVN0YXRlMSEwHwYDVQQKDBhJbnRlcm5ldCBX
aWRnaXRzIFB0eSBMdGQwHhcNMTcwODIzMjMyMjE4WhcNMjcwODIxMjMyMjE4WjBF
-----END CERTIFICATE-----',
            'caFileType' => 'pem',
        ];

        $certManager = new HiveCertManager($sslConfig);
        $params = $certManager->getDsnParameters();
        $certPath = $params['TrustedCerts'];

        $this->assertFileExists($certPath);

        // Cleanup (trigger destructor)
        unset($certManager);

        // File should be removed
        $this->assertFileNotExists($certPath);
    }
}
