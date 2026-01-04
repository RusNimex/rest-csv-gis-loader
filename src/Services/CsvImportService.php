<?php

namespace App\Services;

use League\Csv\Reader;
use App\Interfaces\RepositoryInterface;
use App\Interfaces\CsvRecordInterface;
use App\Interfaces\CsvImportServiceInterface;

/**
 * Сервис для импорта CSV файла
 */
class CsvImportService implements CsvImportServiceInterface
{
    /**
     * @var array Ошибки
     */
    private array $errors = [];

    /**
     * @var Reader CSV reader class
     */
    protected Reader $reader;

    /**
     * @var int Размер чанка
     */
    private int $chunkSize = 100;

    /**
     * @var RepositoryInterface|mixed Репозиторий для работы с данными
     */
    private RepositoryInterface $repository;

    /**
     * @var class-string<CsvRecordInterface> Каждая строка представляется как DTO-модель
     */
    private string $contract;

    /**
     * @var array Сводка импорта файлов
     */
    private array $summary = [
        'total' => 0,
        'imported' => 0,
        'files' => []
    ];

    /**
     * Наполним обязательными параметрами
     *
     * @param array $files Массив файлов из $_FILES['files'], структура:
     *     - 'name': array<string> - имена файлов
     *     - 'type': array<string> - MIME-типы файлов
     *     - 'tmp_name': array<string> - временные пути файлов
     *     - 'error': array<int> - коды ошибок загрузки
     *     - 'size': array<int> - размеры файлов в байтах
     * @param string $repositoryClassName Имя класса репозитория для работы с данными
     * @param string $contractClassName Имя класса DTO-модели для обработки строк CSV
     */
    public function __construct(
        protected array  $files,
        protected string $repositoryClassName,
        protected string $contractClassName,
    ) {
        $this->repository = new $repositoryClassName();
        $this->contract = $contractClassName;
    }

    /**
     * {@inheritdoc}
     * @return void
     */
    public function import(): void
    {
        try {
            foreach ($this->files['name'] as $key => $fileName) {
                $this->reader = Reader::from($this->files['tmp_name'][$key]);
                $this->reader->setHeaderOffset(0);
                $this->reader->setDelimiter(';');
                $this->summary['total'] += count($this->reader);
                $this->summary['files'][] = $fileName;
                $this->load();
            }
        } catch (\Exception $e) { // UnavailableStream|InvalidArgument
            $this->errors[] = $e->getMessage();
            return;
        }
    }

    /**
     * Каждый файл загружается в отдельном потоке
     * Загружаем частями
     * Каждую запись оборачиваем в DTO-модель {@see CsvRecordInterface}
     *
     * @return void
     */
    protected function load(): void
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
     * {@inheritdoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritdoc}
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): array
    {
        return $this->summary;
    }
}

