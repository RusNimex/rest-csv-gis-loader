<?php

namespace App\Repository;

use PDO;

/**
 * Базовый класс для репозиториев
 */
class BaseREpository 
{
    /**
     * Объект PDO
     */
    protected PDO $pdo;
    
    /**
     * Сообщения об ошибках
     */
    protected array $errors = [];

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->pdo = DatabaseConnection::getInstance();
    }
}

