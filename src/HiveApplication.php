<?php

declare(strict_types=1);

namespace Keboola\DbWriter;

class HiveApplication extends Application
{
    public function __construct(array $config, Logger $logger, string $dataDir = '/data/')
    {
        $config['parameters']['data_dir'] = $dataDir;
        $config['parameters']['writer_class'] = 'Hive';
        parent::__construct($config, $logger);
    }
}
