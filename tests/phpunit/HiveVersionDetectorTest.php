<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests;

use Keboola\DbWriter\Connection\HiveVersionDetector;
use PHPUnit\Framework\TestCase;

class HiveVersionDetectorTest extends TestCase
{
    /**
     * @dataProvider provideInputs
     */
    public function testDetectVersion(string $expectedOutput, string $input): void
    {
        $this->assertSame($expectedOutput, HiveVersionDetector::detectVersion($input));
    }

    public function provideInputs(): array
    {
        return [
            'cloudera-hive-0.10.0' => [
                '0.10.0',
                // phpcs:ignore
                'system:sun.java.command=org.apache.hadoop.util.RunJar /opt/cloudera/parcels/CDH-4.2.2-1.cdh4.2.2.p0.10/bin/../lib/hive/lib/hive-cli-**0.10.0**-cdh**4.2.2**.jar org.apache.hadoop.hive.cli.CliDriver',
            ],
            '1.1.1' => [
                '1.1.1',
                // phpcs:ignore
                "system:sun.java.command=org.apache.hadoop.util.RunJar /opt/hive/lib/hive-service-1.1.1.jar org.apache.hive.service.server.HiveServer2 --hiveconf hive.server2.enable.doAs=false'",
            ],
            '2.3.6' => [
                '2.3.6',
                // phpcs:ignore
                'system:sun.java.command=org.apache.hadoop.util.RunJar /opt/hive/lib/hive-service-2.3.6.jar org.apache.hive.service.server.HiveServer2 --hiveconf hive.server2.enable.doAs=false',
            ],
        ];
    }
}
