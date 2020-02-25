<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Traits;

use Keboola\DbWriter\HiveApplication;
use Keboola\DbWriter\Logger;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;

trait CreateApplicationTrait
{
    protected ?string $dataDir;

    public function createApplication(
        array $config,
        ?string $dataFolder = null,
        ?HandlerInterface $logHandler = null
    ): HiveApplication {
        $dataFolder = $dataFolder ?? $this->dataDir ?? '/data';
        $handler = new TestHandler();
        $logger = new Logger('wr-db-hive');

        if ($logHandler) {
            $logger->pushHandler($handler);
        }

        return new HiveApplication($config, $logger, $dataFolder);
    }
}
