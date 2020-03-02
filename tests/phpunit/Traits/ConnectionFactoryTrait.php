<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests\Traits;

use Dibi\Connection;
use Keboola\DbWriter\Connection\HiveConnectionFactory;

trait ConnectionFactoryTrait
{
    abstract protected function getDefaultConfig(): array;

    protected function createConnection(): Connection
    {
        $connectionFactory = new HiveConnectionFactory();
        return $connectionFactory->createConnection($this->getDefaultConfig()['parameters']['db']);
    }
}
