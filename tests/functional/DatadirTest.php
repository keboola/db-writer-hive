<?php

declare(strict_types=1);

namespace Keboola\DbWriter\FunctionalTests;

use Dibi\Connection;
use Dibi\Row;
use Keboola\Csv\CsvFile;
use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\DatadirTestsProviderInterface;
use Keboola\DbWriter\Connection\HiveOdbcReflector;
use Keboola\DbWriter\Tests\Traits\ConnectionFactoryTrait;
use Keboola\DbWriter\Tests\Traits\DefaultConfigTrait;
use Keboola\DbWriter\Tests\Traits\SshKeysTrait;
use Symfony\Component\Filesystem\Filesystem;

class DatadirTest extends AbstractDatadirTestCase
{
    use DefaultConfigTrait;
    use ConnectionFactoryTrait;
    use SshKeysTrait;

    private const CREATE_STM_IGNORED_LINES = [
        'ROW FORMAT', 'WITH SERDEPROPERTIES', 'STORED AS INPUTFORMAT',
        'OUTPUTFORMAT', 'LOCATION', 'TBLPROPERTIES',
    ];

    // Older versions of Hive DB (1.X) doesn't work well with unicode chars
    // https://issues.apache.org/jira/browse/HIVE-15927
    private const TESTS_REQ_UNICODE_SUPPORT = [
        'unicode',
    ];

    // Error messages vary slightly between versions.
    // ... therefore are these tests tested only in Hive DB 2.3
    private const TESTS_REQ_2_3_VERSION = [
        'table-create-error',
    ];

    // MERGE Hive DB operation is required for incremental write with PK,
    // ... and it's supported since 2.2.0 Hive DB version
    private const TESTS_REQ_MERGE_SUPPORT = [
        'primary-key-incremental',
        'primary-key-incremental-table-exists',
    ];

    // Test error message if MERGE operation is not supported
    private const TESTS_NOT_COMP_WITH_MERGE_SUPPORT = [
        'primary-key-incremental-not-supported',
    ];

    protected Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->dropAllTables();
    }

    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $hiveVersion = getenv('HIVE_VERSION') ?: '';

        // Check UNICODE support
        if (in_array($this->dataName(), self::TESTS_REQ_UNICODE_SUPPORT, true) &&
            $hiveVersion &&
            version_compare($hiveVersion, '2.0.0', '<')
        ) {
            $this->markTestSkipped('Unicode support is required for test.');
        }

        // Check MERGE operation support
        $reflector = $this->db->getDriver()->getReflector();
        assert($reflector instanceof HiveOdbcReflector);
        $mergeSupported = $reflector->isMergeSupported();
        if (!$mergeSupported && in_array($this->dataName(), self::TESTS_REQ_MERGE_SUPPORT, true)) {
            $this->markTestSkipped('MERGE operation support is required for test.');
        }
        if ($mergeSupported && in_array($this->dataName(), self::TESTS_NOT_COMP_WITH_MERGE_SUPPORT, true)) {
            $this->markTestSkipped('MERGE operation support is not compatible with test.');
        }

        // Check required version 2.3
        if (in_array($this->dataName(), self::TESTS_REQ_2_3_VERSION, true) && (
                version_compare($hiveVersion, '2.3.0', '<') ||
                version_compare($hiveVersion, '2.4.0', '>=')
            )
        ) {
            $this->markTestSkipped('Test requires Hive DB 2.3.X');
        }

        $tempDatadir = $this->getTempDatadir($specification);

        // Replace environment variables in config.json
        $configPath = $tempDatadir->getTmpFolder() . '/config.json';
        if (file_exists($configPath)) {
            $config = file_get_contents($configPath);
            $config = preg_replace_callback('~\$\{([^{}]+)\}~', fn($m) => getenv($m[1]), $config);
            file_put_contents($configPath, $config);
        }

        // Setup initial db state
        $this->setupDb($tempDatadir->getTmpFolder());

        $process = $this->runScript($tempDatadir->getTmpFolder());

        // Dump database data & create statement after running the script
        $this->dumpAllTables($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
    }

    /**
     * @return DatadirTestsProviderInterface[]
     */
    protected function getDataProviders(): array
    {
        return [
            new DatadirTestsProvider($this->getTestFileDir()),
        ];
    }

    protected function dropAllTables(): void
    {
        // Drop all tables
        $tables = array_map(fn($item) => $item['name'], $this->db->getDriver()->getReflector()->getTables());
        foreach ($tables as $table) {
            $this->db->query('DROP TABLE %n', $table);
        }
    }

    protected function setupDb(string $tmpDir): void
    {
        $this->dropAllTables();
        $setupFile = $tmpDir . '/setup.sql';
        if (file_exists($setupFile)) {
            $this->db->loadFile($setupFile);
        }
    }

    protected function dumpAllTables(string $tmpDir): void
    {
        // Create output dir
        $dumpDir = $tmpDir . '/out/db-dump';
        $fs = new Filesystem();
        $fs->mkdir($dumpDir, 0777);

        // Create connection and get tables
        $connection = $this->createConnection();
        $tables = array_map(fn($item) => $item['name'], $connection->getDriver()->getReflector()->getTables());
        foreach ($tables as $table) {
            $this->dumpTable($connection, $table, $dumpDir);
        }
    }

    protected function dumpTable(Connection $connection, string $table, string $dumpDir): void
    {
        // Generate create statement
        $createStm = implode("\n", array_map(
            fn(Row $row) => $row['createtab_stmt'],
            $connection->query('SHOW CREATE TABLE %n', $table)->fetchAll()
        )) . "\n";

        // Match CLUSTERED BY (...) for "order by" table dump
        preg_match('~CLUSTERED\s+BY\s+\(\s*(.+)\s*\)~i', $createStm, $m);
        $clusteredBy = $m[1] ?? null;

        // Remove ignored lines (eg. DB version specific)
        foreach (self::CREATE_STM_IGNORED_LINES as $ignoredStr) {
            $pattern = sprintf('~(^|\n)%s([^\n]|(\n\s))*~', preg_quote($ignoredStr, '~'));
            $createStm = preg_replace($pattern, '', $createStm);
        }

        // Normalize
        $createStm = trim($createStm) . "\n";

        // Skip temporary tables created by us or by db engine
        // https://community.cloudera.com/t5/Support-Questions/Hive-Temporary-table-created-automatically/td-p/140209
        if (strpos($createStm, 'CREATE TEMPORARY TABLE') !== false) {
            return;
        }

        // Save create statement
        file_put_contents(sprintf('%s/%s.create.sql', $dumpDir, $table), $createStm);

        // Dump data
        $this->dumpTableData($connection, $table, $dumpDir, $clusteredBy);
    }

    protected function dumpTableData(
        Connection $connection,
        string $table,
        string $dumpDir,
        ?string $orderBy = null
    ): void {
        $csv = new CsvFile(sprintf('%s/%s.data.csv', $dumpDir, $table));

        // Write header
        $csv->writeRow(array_map(
            fn($col) => $col['name'],
            $connection->getDriver()->getReflector()->getColumns($table)
        ));

        // Write data
        $orderBySql = $orderBy ? 'ORDER BY ' . $orderBy : '';
        $data = $connection->query('SELECT * FROM %n %sql', $table, $orderBySql);
        foreach ($data as $row) {
            assert($row instanceof Row);
            $csv->writeRow($row->toArray());
        }
    }
}
