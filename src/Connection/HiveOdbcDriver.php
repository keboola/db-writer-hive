<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Connection;

use Dibi\Drivers\OdbcDriver;
use Dibi\Reflector;

class HiveOdbcDriver extends OdbcDriver
{

    public function __construct(array $config)
    {
        parent::__construct($config);

        // Don't prefix columns in result with table name, ... eg. 'price', NOT 'product.price'
        $this->query('set hive.resultset.use.unique.column.names=false');
    }

    public function escapeIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    public function getReflector(): Reflector
    {
        return new HiveOdbcReflector($this);
    }
}
