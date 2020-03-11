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

    public function isMergeSupported(): bool
    {

        // Example output if MERGE operation IS NOT supported:
        // phpcs:ignore
        // ... Error: Error while compiling statement: FAILED: ParseException line 1:0 cannot recognize input near 'MERGE' '<EOF>' '<EOF>' (state=42000,code=40000)

        // Example output if MERGE operation IS supported:
        // phpcs:ignore
        // Error: Error while compiling statement: FAILED: ParseException line 1:5 mismatched input '<EOF>' expecting INTO near 'MERGE' in MERGE statement (state=42000,code=40000)

        try {
            $this->driver->query('MERGE');
        } catch (Dibi\Exception $e) {
            if (strpos($e->getMessage(), "near 'MERGE' in MERGE statemen") === false) {
                return false;
            }
        }

        return true;
    }

    public function getDbVersion(): ?string
    {
        $result = $this->driver->query('set system:sun.java.command');
        $setCommandOutput = $result ? $result->fetch(false)[0] ?? null : null;
        return HiveVersionDetector::detectVersion($setCommandOutput);
    }
}
