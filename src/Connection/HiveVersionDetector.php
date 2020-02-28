<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Connection;

use Dibi\Drivers\OdbcDriver;

class HiveVersionDetector
{
    public static function detectVersion(?string $setCommandOutput): ?string
    {
        // Hive DB not export db version by SQL, use this hack:
        // Example $setCommandOutput:
        // phpcs:disable
        // ... system:sun.java.command=org.apache.hadoop.util.RunJar /opt/cloudera/parcels/CDH-4.2.2-1.cdh4.2.2.p0.10/bin/../lib/hive/lib/hive-cli-**0.10.0**-cdh**4.2.2**.jar org.apache.hadoop.hive.cli.CliDriver
        // ... system:sun.java.command=org.apache.hadoop.util.RunJar /opt/hive/lib/hive-service-1.1.1.jar org.apache.hive.service.server.HiveServer2 --hiveconf hive.server2.enable.doAs=false
        // phpcs:enable
        if (!$setCommandOutput) {
            return null;
        }

        // Remove Cloudera Hive DB (CHD) version if present
        $setCommandOutput = preg_replace('~cdh[\-.*0-9]+~i', '', $setCommandOutput);

        // Match version from string
        preg_match('~RunJar.+(\d{1,2}.\d{1,2}.\d{1,2})~i', $setCommandOutput, $m);
        return $m[1] ?? null;
    }
}
