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
        // Pass table name to odbc_columns() to avoid a full catalog scan.
        // Without a table filter, the Hive Metastore loads metadata (including SerDe classes)
        // for ALL tables in the database. If any unrelated table references a missing SerDe JAR,
        // the entire call fails with MetaException — even though the target table is fine.
        //
        // Escape ODBC wildcard characters: "_" matches any single character, "%" matches
        // zero or more characters. Without escaping, "my_table" would also match "myXtable".
        $escapedTable = strtr($table, ['_' => '\\_', '%' => '\\%']);
        $columns = $this->fetchColumnsFromOdbc(
            $this->driver->getResource(),
            '',
            '',
            $escapedTable,
            $table
        );

        // Fallback: if the filtered query returned no columns (e.g. for Hive temporary tables
        // which may not appear in ODBC catalog queries with a table filter), retry without filter.
        if (empty($columns)) {
            $columns = $this->fetchColumnsFromOdbc(
                $this->driver->getResource(),
                null,
                null,
                null,
                $table
            );
        }

        return $columns;
    }

    /**
     * @param resource $resource
     */
    private function fetchColumnsFromOdbc(
        $resource,
        ?string $catalog,
        ?string $schema,
        ?string $tablePattern,
        string $tableExact
    ): array {
        if ($tablePattern !== null) {
            $res = odbc_columns($resource, $catalog, $schema, $tablePattern);
        } else {
            // Call without optional args so PHP uses the C-level NULL defaults.
            // PHP 7.4's odbc_columns() uses 's' format (not 's!'), so null cannot be
            // passed explicitly with declare(strict_types=1) — it throws TypeError.
            $res = odbc_columns($resource);
        }

        $columns = [];
        while ($row = odbc_fetch_array($res)) {
            if ($row['TABLE_NAME'] === $tableExact) {
                $columns[] = [
                    'name' => $row['COLUMN_NAME'],
                    'table' => $tableExact,
                    'nativetype' => $row['TYPE_NAME'],
                    'size' => $row['COLUMN_SIZE'] ?? null,
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
