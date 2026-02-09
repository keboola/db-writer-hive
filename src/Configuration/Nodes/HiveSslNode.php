<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Configuration\Nodes;

use Keboola\DbWriter\Exception\UserException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class HiveSslNode extends ArrayNodeDefinition
{
    public const CA_FILE_TYPE_PEM = 'pem';
    public const CA_FILE_TYPE_JKS = 'jks';

    public function __construct(string $name, ?ArrayNodeDefinition $parent = null)
    {
        parent::__construct($name, $parent);
        $this->init($this->children());
    }

    public function init(NodeBuilder $nodeBuilder): void
    {
        $this->addEnabledNode($nodeBuilder);
        $this->addCaNode($nodeBuilder);
        $this->addCaFileTypeNode($nodeBuilder);
        $this->addVerifyServerCertNode($nodeBuilder);
        $this->addIgnoreCertificateCn($nodeBuilder);
        $this->beforeNormalization()->always(function (array $v): array {
            // CA can be encrypted, because JKS format may contain private keys.
            if (isset($v['#ca'])) {
                $v['ca'] = $v['#ca'];
                unset($v['#ca']);
            }

            return $v;
        });
        $this->validate()->always(function (array $v): array {
            $caFileType = $v['caFileType'] ?? self::CA_FILE_TYPE_PEM;
            $ca = $v['ca'] ?? $v['#ca'] ?? null;

            // Load internal certificate, value starts with "internal:"
            if ($ca && strpos($ca, 'internal:') === 0) {
                // Parse and check filename
                $dir = (string) getenv('BUNDLED_FILES_PATH');
                $certFileName = preg_replace('~^internal:~', '', $ca);
                if (!preg_match('~^[a-zA-Z0-9.\-_+]+$~', $certFileName)) {
                    throw new InvalidConfigurationException(sprintf(
                        'The "ca" parameter is invalid. The filename "%s" contains illegal characters.',
                        $certFileName,
                    ));
                }

                // Load file content
                $certFilePath = $dir . '/' . $certFileName;
                $certFileContent = @file_get_contents($certFilePath);
                if (!$certFileContent) {
                    throw new InvalidConfigurationException(sprintf(
                        'Certificate "%s" not found.',
                        $certFilePath,
                    ));
                }

                $v['ca'] = $certFileContent;
                unset($v['#ca']);
            } elseif ($caFileType === HiveSslNode::CA_FILE_TYPE_JKS && isset($v['ca'])) {
                // Base64 decode (JKS is binary file)
                $v['ca'] = self::base64Decode(
                    $v['ca'],
                    'db.ssl.ca',
                );
            }

            return $v;
        });
    }

    protected function addCaFileTypeNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->enumNode('caFileType')
            ->values([self::CA_FILE_TYPE_PEM, self::CA_FILE_TYPE_JKS])
            ->defaultValue(self::CA_FILE_TYPE_PEM);
    }

    protected function addVerifyServerCertNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('verifyServerCert')->defaultTrue();
    }

    protected function addEnabledNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('enabled')->defaultFalse();
    }

    protected function addCaNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->scalarNode('ca');
    }

    protected function addIgnoreCertificateCn(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('ignoreCertificateCn')->defaultFalse();
    }

    private static function base64Decode(string $content, string $parameterName): string
    {
        // Base64 decode
        $content = @base64_decode($content);
        if (!$content) {
            throw new UserException(sprintf('Cannot base64 decode "%s" parameter.', $parameterName));
        }

        return $content;
    }
}
