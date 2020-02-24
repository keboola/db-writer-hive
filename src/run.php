<?php

declare(strict_types=1);

use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriter\Logger;
use Keboola\DbWriter\HiveApplication;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonDecode;

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger('wr-db-hive');
$jsonDecode = new JsonDecode([JsonDecode::ASSOCIATIVE => true]);
$jsonEncode = new JsonEncode();
$runAction = true;

try {
    $dataFolder = getenv('KBC_DATADIR') === false ? '/data/' : (string) getenv('KBC_DATADIR');
    if (file_exists($dataFolder . '/config.json')) {
        $config = $jsonDecode->decode(
            (string) file_get_contents($dataFolder . '/config.json'),
            JsonEncoder::FORMAT
        );
    } else {
        throw new UserException('Configuration file not found.');
    }

    $app = new HiveApplication($config, $logger, $dataFolder);
    $app->run();
    exit(0);
} catch (UserException $e) {
    $logger->error($e->getMessage());
    exit(1);
} catch (\Throwable $e) {
    $logger->critical(
        get_class($e) . ':' . $e->getMessage(),
        [
            'errFile' => $e->getFile(),
            'errLine' => $e->getLine(),
            'errCode' => $e->getCode(),
            'errTrace' => $e->getTraceAsString(),
            'errPrevious' => $e->getPrevious() ? get_class($e->getPrevious()) : '',
        ]
    );
    exit(2);
}
