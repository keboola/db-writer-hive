<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Connection;

use Keboola\DbWriter\Configuration\Nodes\HiveSslNode;
use Keboola\DbWriter\Exception\UserException;
use Keboola\Temp\Temp;
use LogicException;
use SplFileInfo;
use Symfony\Component\Process\Process;

class HiveCertManager
{
    private ?array $sslConfig;

    private Temp $temp;

    private ?string $pathToPemCaBundle = null;

    public function __construct(?array $sslConfig = null)
    {
        $this->sslConfig = $sslConfig;
        $this->temp = new Temp();
    }

    public function __destruct()
    {
        // Temp class automatically cleans up files on destruction
    }

    public function getDsnParameters(): array
    {
        $parameters = [];

        if ($this->sslConfig && !empty($this->sslConfig['enabled'])) {
            $parameters['SSL'] = 1;

            // AllowSelfSignedServerCert - inverse of verifyServerCert (default is true)
            $verifyServerCert = $this->sslConfig['verifyServerCert'] ?? true;
            $parameters['AllowSelfSignedServerCert'] = $verifyServerCert ? 0 : 1;

            // CAIssuedCertNamesMismatch - corresponds to ignoreCertificateCn (default is false)
            $ignoreCertificateCn = $this->sslConfig['ignoreCertificateCn'] ?? false;
            $parameters['CAIssuedCertNamesMismatch'] = $ignoreCertificateCn ? 1 : 0;

            $pemFilePath = $this->getPathToPemCaBundle();
            if ($pemFilePath) {
                $parameters['TrustedCerts'] = $pemFilePath;
            }
        }

        return $parameters;
    }

    protected function getPathToPemCaBundle(): ?string
    {
        if (!$this->sslConfig || empty($this->sslConfig['ca'])) {
            return null;
        }

        if (!$this->pathToPemCaBundle) {
            $this->pathToPemCaBundle = $this->generateCaPemBundle();
        }

        return $this->pathToPemCaBundle;
    }

    protected function generateCaPemBundle(): ?string
    {
        if (!$this->sslConfig || empty($this->sslConfig['ca'])) {
            return null;
        }

        if (empty($this->sslConfig['ca'])) {
            throw new UserException('CA certificate bundle is empty.');
        }

        $pemFile = $this->temp->createFile('ca-bundle.pem');
        $caFileType = $this->sslConfig['caFileType'] ?? HiveSslNode::CA_FILE_TYPE_PEM;

        switch ($caFileType) {
            case HiveSslNode::CA_FILE_TYPE_PEM:
                file_put_contents($pemFile->getPathname(), $this->sslConfig['ca']);
                return $pemFile->getPathname();

            case HiveSslNode::CA_FILE_TYPE_JKS:
                $jksFile = $this->temp->createFile('ca-bundle.jks');
                file_put_contents($jksFile->getPathname(), $this->sslConfig['ca']);
                $this->convertCaJksToPem($jksFile, $pemFile);
                return $pemFile->getPathname();

            default:
                throw new LogicException(sprintf(
                    'Unexpected "caFileType" = "%s".',
                    $caFileType,
                ));
        }
    }

    protected function convertCaJksToPem(SplFileInfo $jksFile, SplFileInfo $pemFile): void
    {
        // Convert JKS -> PEM, output is written to STDOUT
        $convertProcess = new Process([
            'bash',
            '-c',
            sprintf(
                'set -o pipefail; set -o errexit; '.
                'echo "" | keytool -list -storetype JKS -keystore "%s" -rfc',
                $jksFile->getPathname(),
            ),
        ]);

        if ($convertProcess->run() !== 0) {
            throw new UserException(sprintf(
                'Cannot convert CA certificate bundle from JKS to PEM format: %s %s',
                $convertProcess->getOutput(),
                $convertProcess->getErrorOutput(),
            ));
        }

        // Save PEM
        file_put_contents($pemFile->getPathname(), $convertProcess->getOutput());
        if ($pemFile->getSize() === 0) {
            throw new UserException('Cannot convert CA certificate bundle from JKS to PEM format.');
        }

        // Cleanup PEM
        $cleanupProcess = Process::fromShellCommandline(sprintf(
            'sed -ne "/-BEGIN CERTIFICATE-/,/-END CERTIFICATE-/p" -i "%s"',
            $pemFile->getPathname(),
        ));
        $cleanupProcess->mustRun();
    }
}
