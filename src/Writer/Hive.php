<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Writer;

use Generator;
use Keboola\DbWriter\Connection\HiveConnectionFactory;
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
        'smallint', 'string', 'timestamp',
        'tinyint', 'varchar',
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
        // Prepare data
        $csvHeader = $csv->getHeader();
        $columns = array_filter($table['items'], fn(array $item) => strtolower($item['type']) !== 'ignore');
        $columnsCount = count($columns);
        $columnsDbNames = array_map(fn($item) => $item['dbName'], $columns);
        $rowsPerInsert = max(intval((3000 / $columnsCount) - 1), 1);

        // Insert
        $iterator = new NoRewindIterator($csv);
        $iterator->next(); // skip header
        while ($iterator->current()) {
            $csvRows = new LimitIterator($iterator, 0, $rowsPerInsert);
            $sqlRows = implode(', ', iterator_to_array($this->mapCsvRows($csvHeader, $csvRows, $columns)));
            $this->db->query('INSERT INTO %n (%n) VALUES %sql', $table['dbName'], $columnsDbNames, $sqlRows);
        }
    }

    public function drop(string $tableName): void
    {
        $this->db->query('DROP TABLE IF EXISTS %n', $tableName);
    }

    public function create(array $table): void
    {
        // For incremental write (MERGE operation) is required to set CLUSTERED BY and transactional = true
        // See: https://sanjivblogs.blogspot.com/2014/12/transactions-are-available-in-hive-014.html
        $tableSpec = [];
        if (!empty($table['primaryKey'])) {
            $tableSpec[] = $this->db->translate(
                "CLUSTERED BY (%n) INTO 1 BUCKETS STORED AS orc TBLPROPERTIES('transactional'='true')",
                $table['primaryKey'],
            );
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

        $columns = array_filter($table['items'], fn($item) => strtolower($item['type']) !== 'ignore');
        $columnsDbNames = array_map(fn($item) => $item['dbName'], $columns);

        // Hive DB doesn't support UPDATE with JOIN, but has special MERGE operation
        if (!empty($table['primaryKey'])) {
            $joinClause = implode(' AND ', array_map(
                fn($col) => $this->db->translate('trg.%n=src.%n', $col, $col),
                $table['primaryKey']
            ));
            $insertCols = implode(', ', array_map(
                fn($col) => $this->db->translate('src.%n', $col),
                $columnsDbNames
            ));
            $updateCols = implode(', ', array_map(
                fn($col) => $this->db->translate('%n=src.%n', $col, $col),
                array_diff($columnsDbNames, $table['primaryKey'])
            ));

            // Update existing data and insert new
            $query =
                'MERGE INTO %n trg USING %n src ON %sql ' .
                'WHEN MATCHED THEN UPDATE SET %sql ' .
                'WHEN NOT MATCHED THEN INSERT VALUES (%sql)';
            $this->db->query($query, $targetTable, $sourceTable, $joinClause, $updateCols, $insertCols);
        } else {
            // Insert new data
            $this->db->query('INSERT INTO %n (%n) SELECT * FROM %n', $targetTable, $columnsDbNames, $sourceTable);
        }

        $endTime = microtime(true);
        $this->logger->info(sprintf('Finished UPSERT after %s seconds', intval($endTime - $startTime)));
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

    private function mapCsvRows(array &$csvHeader, iterable $csvRows, array $columns): Generator
    {
        foreach ($csvRows as $rowValues) {
            $row = array_combine($csvHeader, $rowValues);
            assert(is_array($row));
            yield $this->db->translate('%l', iterator_to_array($this->mapCsvRow($row, $columns)));
        };
    }

    private function mapCsvRow(iterable $data, array $columns): Generator
    {
        foreach ($data as $key => $value) {
            foreach ($columns as $column) {
                if ($column['name'] === $key) {
                    yield $value;
                }
            }
        }
    }
}
