<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Writer;

use Exception;
use Generator;
use Keboola\DbWriter\Connection\HiveConnectionFactory;
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
        $rowsPerInsert = intval((3000 / $columnsCount) - 1);

        // Insert
        $iterator = new NoRewindIterator($csv);
        $iterator->next(); // skip header
        while ($iterator->current()) {
            $csvRows = new LimitIterator($iterator, 0, $rowsPerInsert);
            $sqlRows = implode(', ', iterator_to_array($this->mapCsvRows($csvHeader, $csvRows, $columns)));
            $this->db->query('INSERT INTO %n (%n) VALUES %sql', $table, $columnsDbNames, $sqlRows);
        }
    }

    public function drop(string $tableName): void
    {
        $this->db->query('DROP TABLE IF EXISTS %n', $tableName);
    }

    public function create(array $table): void
    {
        // Hive DB doesn't support PK, FK, NOT NULL, default ...
        // See: https://issues.apache.org/jira/browse/HIVE-6905
        $columns = array_filter($table['items'], fn(array $item) => strtolower($item['type']) !== 'ignore');
        $columnsDefs = array_map(fn($column) => $this->createColumnDef($column), $columns);
        $this->db->query(
            "CREATE TABLE %n (%sql) ROW FORMAT DELIMITED FIELDS TERMINATED BY ',' ESCAPED BY '\\\\'",
            $table['dbName'],
            implode(', ', $columnsDefs),
        );
    }

    public function upsert(array $table, string $targetTable): void
    {
        // TODO: Implement upsert() method.
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
        // Used only in tests
        throw new Exception('Not implemented');
    }

    public function getTableInfo(string $tableName): array
    {
        return array_map(
            fn($col) => ['Field' => $col['name'], 'Type' => $col['nativetype']],
            $this->db->getDriver()->getReflector()->getColumns($tableName)
        );
    }

    public static function getAllowedTypes(): array
    {
        return self::$allowedTypes;
    }

    public function validateTable(array $tableConfig): void
    {
        // TODO: Implement validateTable() method.
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
        // Hive DB doesn't support PK, FK, NOT NULL, default ...
        // See: https://issues.apache.org/jira/browse/HIVE-6905
        $hasSize = !empty($column['size']) && in_array($column['type'], self::$typesWithSize);
        return $hasSize ?
            $this->db->translate('%n (%sql)', $column['dbName'], $column['type']) :
            $this->db->translate('%n');
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
