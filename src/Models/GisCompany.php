<?php

namespace App\Models;

use App\Interfaces\CsvRecordInterface;

final class GisCompany implements CsvRecordInterface
{
    public function __construct(
        public string $name, 
        public string $region,
        public string $district,
        public string $city,
        public string $email,
        public string $phone,
    ) {}

    /**
     * Фабричный метод для создания из массива CSV
     */
    public static function fromCsvRow(array $record): self
    {
        return new self(
            $record['Название'] ?? '',
            $record['Регион'] ?? '',
            $record['Район'] ?? '',
            $record['Город'] ?? '',
            $record['Email'] ?? '',
            $record['Телефон'] ?? '',
        );
    }
}

