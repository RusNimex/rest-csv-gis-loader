<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use App\Migrations\MigrationRunner;

/**
 * Файл-миграцию указываем чере второй аргумент в виде 
 * имени файла без расширения '.sql'. 
 * Например, `php src/Migrations/run.php 0001_create_users_table up`
 * это запустит миграцию `0001_create_users_table_up.sql`
 */
if (isset($argv[1])) {

    $direction = isset($argv[2]) 
        ? $argv[2] 
        : 'up';

    $file = isset($argv[1])
        ? MIGRATION_PATH . "/{$argv[1]}_{$direction}.sql"
        : MIGRATION_PATH . "/001_init_up.sql";

    if (file_exists($file)) {
        $runer = new MigrationRunner();
        $runer->run($file);
        exit();
    }

    exit("Migration file '{$file}' not found" . PHP_EOL);
}

echo 'Usage: Please enter command <init|up|down> [migration_file_name]';
echo PHP_EOL;
exit();
