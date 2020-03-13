<?php

declare(strict_types=1);

namespace Keboola\DbWriter;

use Keboola\DbWriter\Configuration\HiveActionConfigRowDefinition;
use Keboola\DbWriter\Configuration\HiveConfigRowDefinition;
use Keboola\DbWriter\Exception\ApplicationException;
use Keboola\DbWriter\Exception\UserException;

class HiveApplication extends Application
{
    public function __construct(array $config, Logger $logger, string $dataDir = '/data/')
    {
        $config['parameters']['data_dir'] = $dataDir;
        $config['parameters']['writer_class'] = 'Hive';

        $action = $config['action'] ?? 'run';
        $configDefinition = $action === 'run' ?
            new HiveConfigRowDefinition() :
            new HiveActionConfigRowDefinition();

        parent::__construct($config, $logger, $configDefinition);
    }

    private function runWriteTable(array $tableConfig): void
    {
        $csv = $this->getInputCsv($tableConfig['tableId']);
        $tableConfig['items'] = $this->reorderColumns($csv, $tableConfig['items']);

        if (empty($tableConfig['items']) || !$tableConfig['export']) {
            return;
        }

        // In parent class is error msg logged for run action, but not for testConnection
        // ... therefore, nothing logs here, but all errors are logged in run.php
        try {
            if ($tableConfig['incremental']) {
                $this->writeIncremental($csv, $tableConfig);
            } else {
                $this->writeFull($csv, $tableConfig);
            }
        } catch (\PDOException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new ApplicationException($e->getMessage(), 2, $e);
        }
    }
}
