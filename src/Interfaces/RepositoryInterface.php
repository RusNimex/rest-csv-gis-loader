<?php

namespace App\Interfaces;

interface RepositoryInterface
{
    /**
     * Вставляем данные в таблицу
     *
     * @param GisCompany[] $rows
     */
    public function insert(array $rows): int;
}

