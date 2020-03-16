<?php

declare(strict_types=1);

namespace Keboola\DbWriter;

use Keboola\DbWriter\Configuration\HiveActionConfigDefinition;
use Keboola\DbWriter\Configuration\HiveConfigDefinition;
use Keboola\DbWriter\Configuration\HiveConfigRowDefinition;

class HiveApplication extends Application
{
    public function __construct(array $config, Logger $logger, string $dataDir = '/data/')
    {
        $config['parameters']['data_dir'] = $dataDir;
        $config['parameters']['writer_class'] = 'Hive';

        $action = $config['action'] ?? 'run';

        if ($this->isConfigRowConfiguration($config)) {
            $configDefinition = $action === 'run' ?
                new HiveConfigRowDefinition() :
                new HiveActionConfigDefinition();
        } else {
            $configDefinition = new HiveConfigDefinition();
        }

        parent::__construct($config, $logger, $configDefinition);
    }

    private function isConfigRowConfiguration(array $config): bool
    {
        return !isset($config['parameters']['tables']);
    }
}
