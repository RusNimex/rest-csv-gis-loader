<?php

require_once 'src/bootstrap.php';

use App\Http\Router;

header('Content-Type: application/json');

require_once 'vendor/autoload.php';

Router::get('/', function() {
    Router::sendResponse(['message' => 'OK']);
});
Router::post('/upload', 'App\Controllers\UploadController::upload');
Router::end();

