<?php

/**
 * Конфигурация базы данных
 * 
 * Настройки читаются из переменных окружения (.env файл).
 * Если переменные не заданы, используются значения по умолчанию.
 */
return [
    'host' => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'host.docker.internal',
    'dbname' => $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'csv',
    'username' => $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? 'root',
    'charset' => $_ENV['DB_CHARSET'] ?? $_SERVER['DB_CHARSET'] ?? 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => filter_var(
            $_ENV['DB_PERSISTENT'] ?? $_SERVER['DB_PERSISTENT'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
