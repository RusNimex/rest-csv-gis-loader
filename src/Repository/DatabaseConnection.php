<?php

namespace App\Repository;

use PDO;
use RuntimeException;

/**
 * Соединяемся с базой
 */
class DatabaseConnection
{
    /**
     * Экземпляр класса
     */
    private static ?PDO $instance = null;
    
    /**
     * Геттер класса
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $configPath = ROOT_PATH . '/config/db.php';
            if (!file_exists($configPath)) {
                throw new RuntimeException("Файл конфигурации не найден: " . $configPath);
            }
            $config = require $configPath;
            
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['dbname'],
                $config['charset']
            );
            
            self::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        }
        
        return self::$instance;
    }
    
    /**
     * Закрытие соединения
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}

