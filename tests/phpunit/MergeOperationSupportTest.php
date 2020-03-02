<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests;

use Keboola\DbWriter\Connection\HiveOdbcReflector;
use Keboola\DbWriter\Tests\Traits\ConnectionFactoryTrait;
use Keboola\DbWriter\Tests\Traits\DefaultConfigTrait;
use Keboola\DbWriter\Tests\Traits\SshKeysTrait;
use PHPUnit\Framework\TestCase;

class MergeOperationSupportTest extends TestCase
{
    use SshKeysTrait;
    use DefaultConfigTrait;
    use ConnectionFactoryTrait;

    public function testMergeOperationSupportDetection(): void
    {
        $connection = $this->createConnection();
        $reflector = $connection->getDriver()->getReflector();
        assert($reflector instanceof HiveOdbcReflector);

        $hiveVersion = getenv('HIVE_VERSION') ?: '';
        if (empty($hiveVersion)) {
            $this->fail('Missing HIVE_VERSION environment variable.');
        }

        if (version_compare($hiveVersion, '2.2.0', '>=')) {
            $this->assertTrue($reflector->isMergeSupported());
        } else {
            $this->assertFalse($reflector->isMergeSupported());
        }
    }
}
