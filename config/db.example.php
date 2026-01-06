<?php
/**
 * Пример конфигурации базы данных
 * 
 * Для docker-compose используйте:
 *   'host' => 'db',  // имя сервиса из docker-compose.yml
 * 
 * Для внешней БД используйте IP или доменное имя
 */

return [
    // Для docker-compose
    'host' => 'host.docker.internal',
    
    // Для внешней БД (раскомментируйте и укажите свой хост)
    // 'host' => '127.0.0.1',
    
    'dbname' => 'csv',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
    ],
];

