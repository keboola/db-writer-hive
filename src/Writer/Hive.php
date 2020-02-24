<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Writer;

use Dibi\Connection;
use Keboola\Csv\CsvFile;
use Keboola\DbExtractor\Connection\HiveConnectionFactory;
use Keboola\DbWriter\Logger;
use Keboola\DbWriter\Writer;

class Hive extends Writer
{
    /** @var Connection */
    protected $db;

    private HiveConnectionFactory $connectionFactory;

    public function __construct(array $dbParams, Logger $logger)
    {
        $this->connectionFactory = new HiveConnectionFactory();
        parent::__construct($dbParams, $logger);
    }

    public function createConnection(array $params)
    {
        return $this->connectionFactory->createConnection($params);
    }

    public function write(CsvFile $csv, array $table): void
    {
        // TODO: Implement write() method.
    }

    public function drop(string $tableName): void
    {
        $this->db->query('DROP TABLE IF EXISTS %n', $tableName);
    }

    public function create(array $table): void
    {
        // TODO: Implement create() method.
    }

    public function upsert(array $table, string $targetTable): void
    {
        // TODO: Implement upsert() method.
    }

    public function tableExists(string $tableName): bool
    {
        // TODO: Implement tableExists() method.
    }

    public function generateTmpName(string $tableName): string
    {
        // TODO: Implement generateTmpName() method.
    }

    public function showTables(string $dbName): array
    {
        // TODO: Implement showTables() method.
    }

    public function getTableInfo(string $tableName): array
    {
        // TODO: Implement getTableInfo() method.
    }

    public static function getAllowedTypes(): array
    {
        // TODO: Implement getAllowedTypes() method.
    }

    public function validateTable(array $tableConfig): void
    {
        // TODO: Implement validateTable() method.
    }
}
