<?php

namespace App\Controllers;

use App\Http\ResponseTrait;
use App\Models\GisCompany;
use App\Services\CsvImportService;
use App\Repository\CompanyRepository;

/**
 * Контроллер загрузки файлов
 */
class UploadController
{
    use ResponseTrait;

    /**
     * @var array|mixed Файлы из $_FILES['files']
     */
    protected array $files;

    /**
     * @var array|string[] Допустимые типы файлов
     */
    protected array $allowedTypes = ['text/csv'];

    /**
     * @var int|float Максимальный размер файла в байтах
     */
    protected int|float $maxSize = 5 * 1024 * 1024;

    /**
     * @var array Ошибки
     */
    protected array $errors = [];

    /**
     * Необходимый минимум для работы с CSV файлами
     */
    public function __construct()
    {
        $this->files = $_FILES['files'] ?? [];
    }

    /**
     * Обрабатываем загрузку файла
     * 
     * Основная логика в сервисе {@see CsvImportService}
     */
    public function upload(): void
    {
        if (!$this->validate()) {
            self::sendError($this->errors);
        }

        try {
            $service = new CsvImportService(
                $this->files,
                CompanyRepository::class,
                GisCompany::class,
            );

            $service->import();

            if ($service->hasErrors()) {
                self::sendError($service->getErrors());
            }

            self::sendResponse([
                'message' => 'Файлы успешно загружены',
                'summary' => $service->getSummary(),
            ]);
        } catch (\Exception $e) {
            self::sendError($e->getMessage());
        }
    }

    /**
     * Валидация файлов
     *
     * @return bool
     */
    protected function validate(): bool
    {
        if (empty($this->files)) {
            $this->errors[] = "Необходимо выбрать один или множество файлов";
            return false;
        }

        foreach ($this->files['name'] as $key => $fileName) {
            if ($this->files['error'][$key] !== UPLOAD_ERR_OK) {
                $this->errors[] = "Файл `{$fileName}` загружен с ошибкой `{$this->files['error'][$key]}`";
                continue;
            }

            if (!in_array($this->files['type'][$key], $this->allowedTypes)) {
                $this->errors[] = "Файл `{$fileName}` должен быть в CSV формате";
                continue;
            }

            if ($this->files['size'][$key] > $this->maxSize) {
                $this->errors[] = "Файл `{$fileName}` не должен превышать {$this->maxSize} Мб";
            }
        }

        return empty($this->errors);
    }
}

