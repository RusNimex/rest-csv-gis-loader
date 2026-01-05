<?php

namespace App\Interfaces;

use App\Models\GisCompany;

/**
 * Репозиторий для загрузки CSV-данных в базу
 */
interface RepositoryInterface
{
    /**
     * Вставляем данные в таблицу
     *
     * @param GisCompany[] $rows
     */
    public function insert(array $rows): void;

    /**
     * Статистика импорта
     *
     * @return array
     */
    public function getSummary(): array;
}

