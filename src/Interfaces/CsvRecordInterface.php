<?php

namespace App\Interfaces;

/**
 * Контракт для CSV записи
 */
interface CsvRecordInterface
{
    /**
     * Создание DTO-контракт из CSV строки
     */
    public static function fromCsvRow(array $row): self;
}

