<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Connection;

use Dibi\Drivers\OdbcDriver;

class HiveOdbcDriver extends OdbcDriver
{
    private bool $buggedVersion = false;

    public function __construct(array $config)
    {
        parent::__construct($config);

        // Don't prefix columns in result with table name, ... eg. 'price', NOT 'product.price'
        $this->query('set hive.resultset.use.unique.column.names=false');

        // Hive DB has bug in escaping in versions 1.X, fixed in 2.0
        // https://issues.apache.org/jira/browse/HIVE-11723
        // https://issues.apache.org/jira/browse/HIVE-17358
        $dbVersion = $this->getReflector()->getDbVersion();
        $this->buggedVersion = $dbVersion && version_compare($dbVersion, '2.0.0', '<');
    }

    public function escapeText(string $value): string
    {
        if ($this->buggedVersion) {
            return parent::escapeText($value);
        }

        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("'", "\'", $value);
        return "'" . $value . "'";
    }


    public function escapeBinary(string $value): string
    {
        return $this->escapeText($value);
    }

    public function escapeIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    public function getReflector(): HiveOdbcReflector
    {
        return new HiveOdbcReflector($this);
    }
}
