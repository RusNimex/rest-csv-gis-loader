<?php

namespace App\Http;

trait ResponseTrait
{
    /**
     * Отправка ответа
     */
    public static function sendResponse(array $data, int $code = 200)
    {
        http_response_code($code);
        echo json_encode(['data' => $data]);
        exit();
    }

    /**
     * Отправка ошибки
     */
    public static function sendError(string $message, int $code = 400)
    {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit();
    }
}

