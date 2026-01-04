<?php
/**
 * Точка входа в приложение
 */

require_once 'src/bootstrap.php';
require_once 'vendor/autoload.php';
header('Content-Type: application/json');

use App\Http\Router;

// Регистрируем маршруты
Router::get('/healthz', function() {
    Router::sendResponse(['message' => 'OK']);
});
Router::post('/upload', 'App\Controllers\UploadController::upload');
Router::end();
