<?php

declare(strict_types=1);

namespace Keboola\DbWriter\FunctionalTests;

use Dibi\Connection;
use Dibi\Row;
use Keboola\Csv\CsvFile;
use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DbWriter\Tests\Traits\ConnectionFactoryTrait;
use Keboola\DbWriter\Tests\Traits\DefaultConfigTrait;
use Keboola\DbWriter\Tests\Traits\SshKeysTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DatadirTest extends DatadirTestCase
{
    use DefaultConfigTrait;
    use ConnectionFactoryTrait;
    use SshKeysTrait;

    private const CREATE_STM_IGNORED_LINES = [
        'ROW FORMAT', 'WITH SERDEPROPERTIES', 'STORED AS INPUTFORMAT',
        'OUTPUTFORMAT', 'LOCATION', 'TBLPROPERTIES',
     ];

    protected function tearDown(): void
    {
        parent::tearDown();

        // Drop all tables
        $connection = $this->createConnection();
        $tables = array_map(fn($item) => $item['name'], $connection->getDriver()->getReflector()->getTables());
        foreach ($tables as $table) {
            $connection->query('DROP TABLE %n', $table);
        }
    }

    protected function runScript(string $datadirPath): Process
    {
        try {
            return parent::runScript($datadirPath);
        } finally {
            // Dump database data & metadata after running the script
            $this->dumpAllTables($datadirPath);
        }
    }

    protected function dumpAllTables(string $datadirPath): void
    {
        // Create output dir
        $dumpDir = $datadirPath . '/out/db-dump';
        $fs = new Filesystem();
        $fs->mkdir($dumpDir, 0777);

        // Create connection and get tables
        $connection = $this->createConnection();
        $tables = array_map(fn($item) => $item['name'], $connection->getDriver()->getReflector()->getTables());
        foreach ($tables as $table) {
            $this->dumpCreateStatement($connection, $table, $dumpDir);
            $this->dumpTableData($connection, $table, $dumpDir);
        }
    }

    protected function dumpCreateStatement(Connection $connection, string $table, string $dumpDir): void
    {
        // Generate create statement
        $createStm = implode("\n", array_map(
            fn(Row $row) => $row['createtab_stmt'],
            $connection->query('SHOW CREATE TABLE %n', $table)->fetchAll()
        )) . "\n";

        // Remove ignored lines (eg. DB version specific)
        foreach (self::CREATE_STM_IGNORED_LINES as $ignoredStr) {
            $pattern = sprintf('~(^|\n)%s([^\n]|(\n\s))*~', preg_quote($ignoredStr, '~'));
            $createStm = preg_replace($pattern, '', $createStm);
        }

        // Normalize
        $createStm = trim($createStm) . "\n";

        // Save
        file_put_contents(sprintf('%s/%s.create.sql', $dumpDir, $table), $createStm);
    }

    protected function dumpTableData(Connection $connection, string $table, string $dumpDir): void
    {
        $csv = new CsvFile(sprintf('%s/%s.data.csv', $dumpDir, $table));

        // Write header
        $csv->writeRow(array_map(
            fn($col) => $col['name'],
            $connection->getDriver()->getReflector()->getColumns($table)
        ));

        // Write data
        $data = $connection->query('SELECT * FROM %n', $table);
        foreach ($data as $row) {
            assert($row instanceof Row);
            $csv->writeRow($row->toArray());
        }
    }
}
