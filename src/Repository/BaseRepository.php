<?php

namespace App\Repository;

use PDO;

/**
 * Базовый класс для репозиториев
 */
class BaseRepository
{
    /**
     * Объект PDO
     */
    protected PDO $pdo;
    
    /**
     * Сообщения об ошибках
     */
    protected array $errors = [];

    /** @var array кэш регионов */
    protected array $region = [];

    /** @var array кэш районов */
    protected array $district = [];

    /** @var array кэш городов */
    protected array $city = [];

    /** @var array кэш категорий */
    protected array $category = [];

    /** @var array кэш подкатегорий */
    protected array $subcategory = [];

    /** @var array кэш компаний */
    protected array $company = [];

    /** @var array соберем битые категории/подкатегории */
    protected array $sanitized = [];

    /** @var array для массовой вставки Компания-Гео одним запросом */
    protected array $companyGeos = [];

    /** @var array для массовой вставки Компания-Категории/Подкатегории одним запросом */
    protected array $companyCategories = [];

    /** @var int кол-во компаний для статистики */
    protected int $companyCount = 0;

    /** @var array кэш geo записей для батч-вставки */
    protected array $geoCache = [];

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->pdo = DatabaseConnection::getInstance();
    }
}

