<?php

namespace App\Services;

use \League\Csv\Reader;
use App\Models\GisCompany;
use App\Interfaces\RepositoryInterface;
use App\Interfaces\CsvRecordInterface;
use App\Interfaces\CsvImportServiceInterface;
use App\Repository\CompanyRepository;

/**
 * Сервис для импорта CSV файла
 */
class CsvImportService implements CsvImportServiceInterface
{
    /**
     * Ошибки
     */
    private array $errors = [];

    /**
     * CSV reader class
     */
    protected Reader $reader;

    /** 
     * Размер чанка
     */
    private int $chunkSize = 100;

    /**
     * Репозиторий
     */
    private RepositoryInterface $repository;

    /**
     * @var class-string<CsvRecordInterface>
     */
    private string $contract;

    /**
     * Сводка
     */
    private array $summary = [
        'total' => 0,
        'imported' => 0,
        'fileName' => ''
    ];

    /**
     * Наполним ридер данными о файле
     * 
     * @param array $file
     */
    public function __construct(
        protected array $file, 
        protected string $repositoryClassName,
        protected string $contractClassName,
    ) {
        $this->reader = Reader::createFromPath($file['file']['tmp_name']);
        $this->reader->setHeaderOffset(0);
        $this->reader->setDelimiter(';');
        $this->repository = new $repositoryClassName();
        $this->contract = $contractClassName;
        $this->summary['total'] = count($this->reader);
        $this->summary['fileName'] = $file['file']['name'];
    }

    /**
     * Импортируем файл
     */
    public function load(): void
    {
        try {
            $chunks = $this->reader->chunkBy($this->chunkSize);
            foreach ($chunks as $chunk) {
                $records = [];
                foreach ($chunk as $row) {
                    $records[] = $this->contract::fromCsvRow($row);
                }
                $this->summary['imported'] += $this->repository->insert($records);
            };
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    /**
     * Возвращает ошибки
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Проверяет, есть ли ошибки
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Возвращает сводку
     */
    public function getSummary(): array
    {
        return $this->summary;
    }
}

