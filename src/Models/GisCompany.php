<?php

namespace App\Models;

use App\Interfaces\CsvRecordInterface;

/**
 * Модель компании для GIS
 */
final class GisCompany implements CsvRecordInterface
{
    public function __construct(
        public string $name, 
        public string $region,
        public string $district,
        public string $city,
        public string $email,
        public string $phone,
        public string $category,
        public string $subcategory,
    ) {}

    /**
     * Фабричный метод для создания из массива CSV
     */
    public static function fromCsvRow(array $row): self
    {
        return new self(
            $row['Название'] ?? '',
            $row['Регион'] ?? '',
            $row['Район'] ?? '',
            $row['Город'] ?? '',
            $row['Email'] ?? '',
            $row['Телефон'] ?? '',
            $row['Рубрика'] ?? '',
            $row['Подрубрика'] ?? '',
        );
    }
}

