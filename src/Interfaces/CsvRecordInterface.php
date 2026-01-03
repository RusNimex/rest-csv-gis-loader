<?php

namespace App\Interfaces;

interface CsvRecordInterface
{
    public static function fromCsvRow(array $row): self;
}

