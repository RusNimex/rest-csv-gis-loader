<?php

namespace App\Interfaces;

interface CsvImportServiceInterface
{
    public function load(): void;
    public function hasErrors(): bool;
    public function getErrors(): array;
    public function getSummary(): array;
}

