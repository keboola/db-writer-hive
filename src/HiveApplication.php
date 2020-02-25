<?php

declare(strict_types=1);

namespace Keboola\DbWriter;

use Keboola\DbWriter\Configuration\HiveActionConfigRowDefinition;
use Keboola\DbWriter\Configuration\HiveConfigRowDefinition;

class HiveApplication extends Application
{
    public function __construct(array $config, Logger $logger, string $dataDir = '/data/')
    {
        $config['parameters']['data_dir'] = $dataDir;
        $config['parameters']['writer_class'] = 'Hive';

        $action = !is_null($config['action']) ?: 'run';
        $configDefinition = $action === 'run' ?
            new HiveConfigRowDefinition() :
            new HiveActionConfigRowDefinition();

        parent::__construct($config, $logger, $configDefinition);
    }
}
