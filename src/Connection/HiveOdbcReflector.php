<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Connection;

use Dibi;
use Dibi\Drivers\OdbcReflector;

class HiveOdbcReflector extends OdbcReflector
{
    private Dibi\Driver $driver;

    public function __construct(Dibi\Driver $driver)
    {
        parent::__construct($driver);
        $this->driver = $driver;
    }

    public function getColumns(string $table): array
    {
        $res = odbc_columns($this->driver->getResource());
        $columns = [];
        while ($row = odbc_fetch_array($res)) {
            if ($row['TABLE_NAME'] === $table) {
                $columns[] = [
                    'name' => $row['COLUMN_NAME'],
                    'table' => $table,
                    'nativetype' => $row['TYPE_NAME'],
                    'size' => $row['COLUMN_SIZE'] ?? null, // <<< modified, COLUMN_SIZE can be undefined
                    'nullable' => (bool) $row['NULLABLE'],
                    'default' => $row['COLUMN_DEF'],
                ];
            }
        }
        odbc_free_result($res);
        return $columns;
    }
}
