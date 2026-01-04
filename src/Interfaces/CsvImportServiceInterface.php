<?php

namespace App\Interfaces;

/**
 * Сервис для импорта CSV файлов
 */
interface CsvImportServiceInterface
{
    /**
     * Импорт CSV файлов в базу данных
     *
     * @return void
     */
    public function import(): void;

    /**
     * Проверка наличия ошибок
     *
     * @return bool
     */
    public function hasErrors(): bool;

    /**
     * Получение ошибок
     *
     * @return array
     */
    public function getErrors(): array;

    /**
     * Получение сводных данных
     *
     * @return array
     */
    public function getSummary(): array;
}

