<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Writer;

use Generator;
use Keboola\DbWriter\Connection\HiveConnectionFactory;
use Keboola\DbWriter\Connection\HiveOdbcReflector;
use Keboola\DbWriter\Exception\UserException;
use NoRewindIterator;
use LimitIterator;
use Dibi;
use Dibi\Connection;
use Keboola\Csv\CsvFile;
use Keboola\DbWriter\Logger;
use Keboola\DbWriter\Writer;

class Hive extends Writer
{
    private static array $allowedTypes = [
        'bigint', 'boolean', 'char', 'decimal',
        'double', 'float', 'int', 'real',
        'smallint', 'string', 'timestamp', 'date',
        'tinyint', 'varchar', 'binary',
    ];

    private static array $typesWithSize = [
        'char',
        'decimal',
        'varchar',
    ];

    /** @var Connection */
    protected $db;

    private HiveConnectionFactory $connectionFactory;

    public function __construct(array $dbParams, Logger $logger)
    {
        $this->connectionFactory = new HiveConnectionFactory();
        parent::__construct($dbParams, $logger);
    }


    public static function getAllowedTypes(): array
    {
        return self::$allowedTypes;
    }

    public function createConnection(array $params): Connection
    {
        return $this->connectionFactory->createConnection($params);
    }

    public function testConnection(): void
    {
        // testConnection: Used in Keboola\DbWriter\Application, missing in WriterInterface
        $this->db->query('SELECT 1');
    }

    public function write(CsvFile $csv, array $table): void
    {
        // CSV metadata
        $csvHeader = $csv->getHeader();
        $columnsDef = array_filter($table['items'], fn(array $item) => strtolower($item['type']) !== 'ignore');

        // Map column db name => column index in CSV row
        $csvColumnIndexMap = [];
        foreach ($csvHeader as $csvColumnIndex => $csvName) {
            foreach ($columnsDef as $column) {
                if ($column['name'] === $csvName) {
                    $csvColumnIndexMap[$column['dbName']] = $csvColumnIndex;
                }
            }
        }

        // Get table metadata
        $tableColumns = array_map(
            fn($item) => $item['name'],
            $this->db->getDriver()->getReflector()->getColumns($table['dbName'])
        );

        // Calculate bulk size
        $columnsCount = count($columnsDef);
        $rowsPerInsert = max(intval((3000 / $columnsCount) - 1), 1);

        // All columns must be provided in the right order - as defined in the table, it is Hive DB limitation.
        // The standard SQL syntax that allows the user to insert values into only some columns is not yet supported.
        // See: https://cwiki.apache.org/confluence/display/Hive/LanguageManual+DML#LanguageManualDML-Syntax.3
        $iterator = new NoRewindIterator($csv);
        $iterator->next(); // skip header
        while ($iterator->current()) {
            $csvRows = new LimitIterator($iterator, 0, $rowsPerInsert);
            $insertSql = $this->db->translate('INSERT INTO %n VALUES', $table['dbName']);
            $valuesSql = implode(', ', iterator_to_array(
                $this->mapCsvRows($tableColumns, $csvColumnIndexMap, $csvRows)
            ));
            // Parts of SQL query are escaped separately
            $this->db->nativeQuery($insertSql . ' ' . $valuesSql);
        }
    }

    public function drop(string $tableName): void
    {
        $this->db->query('DROP TABLE IF EXISTS %n', $tableName);
    }

    public function create(array $table): void
    {
        $requiredMergeOperation = !empty($table['incremental']) && !empty($table['primaryKey']);
        if ($requiredMergeOperation) {
            $reflector = $this->db->getDriver()->getReflector();
            assert($reflector instanceof HiveOdbcReflector);
            if (!$reflector->isMergeSupported()) {
                throw new UserException(
                    'MERGE operation is not supported in current Hive DB version. ' .
                    'Support is required for incremental write if is used primaryKey option.'
                );
            }
        }

        // For incremental write (MERGE operation) is required to set CLUSTERED BY and transactional = true
        // See: https://sanjivblogs.blogspot.com/2014/12/transactions-are-available-in-hive-014.html
        $tableSpec = [];
        if ($requiredMergeOperation && empty($table['temporary'])) {
            $tableSpec[] = $this->db->translate(
                "CLUSTERED BY (%n) INTO 1 BUCKETS STORED AS orc TBLPROPERTIES('transactional'='true')",
                $table['primaryKey'],
            );
        } else {
            $tableSpec[] = 'STORED AS orc';
        }

        // Columns are ordered according CSV header, see Application::reorderColumns
        $columns = array_filter($table['items'], fn(array $item) => strtolower($item['type']) !== 'ignore');
        $columnsDefs = array_map(fn($column) => $this->createColumnDef($column), $columns);
        $this->db->query(
            'CREATE %sql TABLE %n (%sql) %sql',
            !empty($table['temporary']) ? 'TEMPORARY' : '',
            $table['dbName'],
            implode(', ', $columnsDefs),
            implode(' ', $tableSpec),
        );
    }

    public function upsert(array $table, string $targetTable): void
    {
        $startTime = microtime(true);
        $this->logger->info('Begin UPSERT');
        $sourceTable = $table['dbName'];

        $columnsDef = array_filter($table['items'], fn($item) => strtolower($item['type']) !== 'ignore');
        $columns = array_map(fn($item) => $item['dbName'], $columnsDef);

        // Hive DB doesn't support UPDATE with JOIN, but has special MERGE operation
        if (!empty($table['primaryKey'])) {
            $this->upsertDoMerge($sourceTable, $targetTable, $table['primaryKey'], $columns);
        } else {
            $this->upsertDoInsert($sourceTable, $targetTable, $columns);
        }

        $endTime = microtime(true);
        $this->logger->info(sprintf('Finished UPSERT after %s seconds', intval($endTime - $startTime)));
    }

    protected function upsertDoMerge(string $sourceTable, string $targetTable, array $primaryKey, array $columns): void
    {
        $joinClause = implode(' AND ', array_map(
            fn($col) => $this->db->translate('trg.%n=src.%n', $col, $col),
            $primaryKey
        ));
        $insertCols = implode(', ', array_map(
            fn($col) => $this->db->translate('src.%n', $col),
            $columns
        ));
        $updateCols = implode(', ', array_map(
            fn($col) => $this->db->translate('%n=src.%n', $col, $col),
            array_diff($columns, $primaryKey) // primary key cannot be updated in Hive DB
        ));

        // Update existing data and insert new
        $query =
            'MERGE INTO %n trg USING %n src ON %sql ' .
            'WHEN MATCHED THEN UPDATE SET %sql ' .
            'WHEN NOT MATCHED THEN INSERT VALUES (%sql)';
        $this->db->query($query, $targetTable, $sourceTable, $joinClause, $updateCols, $insertCols);
    }

    protected function upsertDoInsert(string $sourceTable, string $targetTable, array $columns): void
    {
        // All columns must be provided in the right order - as defined in the table, Hive DB limitation
        // ... so for column that exists in target table, but not exists in source table (and configuration)
        // ... we must select null value
        $columnsMapping = implode(', ', array_map(
            fn($col) => in_array($col['name'], $columns, true) ?
                $this->db->translate('%n', $col['name']) : 'null',
            $this->db->getDriver()->getReflector()->getColumns($targetTable),
        ));

        $this->db->query('INSERT INTO %n SELECT %sql FROM %n', $targetTable, $columnsMapping, $sourceTable);
    }

    public function tableExists(string $tableName): bool
    {
        try {
            $this->db->query('SELECT * FROM %n LIMIT 1', $tableName);
            return true;
        } catch (Dibi\Exception $e) {
            return false;
        }
    }

    public function generateTmpName(string $tableName): string
    {
        return $this->prefixTableName('tmp_', $tableName);
    }

    public function showTables(string $dbName): array
    {
        return array_map(
            fn($table) => $table['name'],
            $this->db->getDriver()->getReflector()->getTables()
        );
    }

    public function getTableInfo(string $tableName): array
    {
        return [
            'columns' => array_map(
                fn($col) => ['COLUMN_NAME' => $col['name'], 'DATA_TYPE' => $col['nativetype']],
                $this->db->getDriver()->getReflector()->getColumns($tableName)
            ),
        ];
    }

    public function validateTable(array $tableConfig): void
    {
        $dbColumns = $this->getTableInfo($tableConfig['dbName'])['columns'];

        foreach ($tableConfig['items'] as $column) {
            $exists = false;
            $targetDataType = null;
            foreach ($dbColumns as $dbColumn) {
                $exists = ($dbColumn['COLUMN_NAME'] === $column['dbName']);
                if ($exists) {
                    $targetDataType = $dbColumn['DATA_TYPE'];
                    break;
                }
            }

            if (!$exists) {
                throw new UserException(sprintf(
                    'Column "%s" not found in destination table "%s"',
                    $column['dbName'],
                    $tableConfig['dbName']
                ));
            }

            if (strtolower($targetDataType) !== strtolower($column['type'])) {
                throw new UserException(sprintf(
                    'Data type mismatch. Column "%s" is of type "%s" in writer, but is "%s" in destination table "%s"',
                    $column['dbName'],
                    $column['type'],
                    $targetDataType,
                    $tableConfig['dbName']
                ));
            }
        }
    }

    private function prefixTableName(string $prefix, string $tableName): string
    {
        $tableNameArr = explode('.', $tableName);
        if (count($tableNameArr) > 1) {
            return $tableNameArr[0] . '.' . $prefix . $tableNameArr[1];
        }

        return $prefix . $tableName;
    }

    private function createColumnDef(array $column): string
    {
        // Check if type is allowed
        if (!in_array(strtolower($column['type']), self::getAllowedTypes(), true)) {
            throw new UserException(sprintf(
                'Column "%s" is of type "%s" in writer configuration, but this type is not supported.',
                $column['dbName'],
                $column['type'],
            ));
        }

        // Hive DB doesn't support PK, FK, NOT NULL, default ...
        // See: https://issues.apache.org/jira/browse/HIVE-6905
        $hasSize = !empty($column['size']) && in_array($column['type'], self::$typesWithSize);
        return $hasSize ?
            $this->db->translate('%n %sql(%sql)', $column['dbName'], $column['type'], $column['size']) :
            $this->db->translate('%n %sql', $column['dbName'], $column['type']);
    }

    private function mapCsvRows(array &$tableColumns, array &$csvColumnIndexMap, iterable $csvRows): Generator
    {
        foreach ($csvRows as $row) {
            yield $this->db->translate(
                '%l',
                iterator_to_array($this->mapCsvRow($tableColumns, $csvColumnIndexMap, $row))
            );
        };
    }

    private function mapCsvRow(array &$tableColumns, array &$csvColumnIndexMap, array $row): Generator
    {
        // All columns must be provided in the right order - as defined in the table, Hive DB limitation
        foreach ($tableColumns as $colDbName) {
            $csvColumnIndex = $csvColumnIndexMap[$colDbName] ?? null;
            yield $csvColumnIndex === null ? null : $row[$csvColumnIndex];
        }
    }
}
