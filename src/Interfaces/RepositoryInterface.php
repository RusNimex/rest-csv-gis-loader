<?php

namespace App\Interfaces;

use App\Models\GisCompany;

interface RepositoryInterface
{
    /**
     * Вставляем данные в таблицу
     *
     * @param GisCompany[] $rows
     */
    public function insert(array $rows): int;
}

