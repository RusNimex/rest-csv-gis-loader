<?php

namespace App\Migrations;

use App\Repository\DatabaseConnection;
use PDO;
use Exception;

/**
 * Миграция базы данных
 */
class MigrationRunner
{
    /**
     * Объект PDO подключенный к базе
     */
    protected PDO $pdo;

    /**
     * Подключаемся к базе данных
     */
    public function __construct()
    {
        $this->pdo = DatabaseConnection::getInstance();
    }

    /**
     * Выполним миграцию
     */
    public function run(string $file): void
    {
        $sql = file_get_contents($file);
        $this->pdo->exec($sql);
        echo "Миграция {$file} выполнена успешно";
        echo PHP_EOL;
    }

}

