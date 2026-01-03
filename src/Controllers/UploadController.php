<?php

namespace App\Controllers;

use App\Http\ResponseTrait;
use App\Models\GisCompany;
use App\Services\CsvImportService;
use App\Repository\CompanyRepository;

/**
 * Контроллер загрузки файла
 */
class UploadController
{
    use ResponseTrait;

    /**
     * Файлы
     */
    protected array $files;

    /**
     * Ошибки
     */
    protected array $errors = [];

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->files = $_FILES ?? [];
    }

    /**
     * Загрузка файла
     */
    public function upload()
    {
        if (!$this->validate()) {
            self::sendError(['errors' => $this->errors]);
        }

        $service = new CsvImportService(
            $_FILES,
            CompanyRepository::class,
            GisCompany::class,
        );

        $service->load();
        if ($service->hasErrors()) {
            self::sendError(['errors' => $service->getErrors()], 500);
        }

        self::sendResponse([
            'message' => 'File uploaded successfully',
            'summary' => $service->getSummary(),
        ]);
    }

    /**
     * Валидация файла
     */
    protected function validate()
    {
        if (!isset($this->files['file'])) {
            $this->errors[] = 'File not found';
            return false;
        }

        if ($this->files['file']['type'] !== 'text/csv') {
            $this->errors[] = 'File is not CSV format';
            return false;
        }

        if ($this->files['file']['size'] > 1024 * 1024 * 1024) {
            self::sendError('File size is too large');
        }

        return true;
    }
}

