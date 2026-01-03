<?php

namespace App\Http;

use Closure;
use ReflectionFunction;
use App\Controllers\UploadController;

/**
 * Класс маршрутизатора
 */
class Router 
{
    use ResponseTrait;

    // Создадим экземпляр класса
    protected static ?Router $instance = null;

    /**
     * Параметры и значения запроса
     */
    protected array $params;

    /**
     * Параметры, которые нужно исключить
     */
    protected array $excludedParams = [
        'XDEBUG_SESSION',
        'OTHER_KEY'
    ];
    
    /**
     * Насытим параметрами
     */
    public function setParams(array $params): void
    {
        $result = array_filter($params, function($param) {
            return !in_array($param, $this->excludedParams);
        }, ARRAY_FILTER_USE_KEY);

        $this->params = $result;
    }
    
    /**
     * Добавим обраотку GET - запросов
    */
    public static function get(string $route, callable|string $callback): void
    {
        if (!$_SERVER['REQUEST_METHOD'] === 'GET') {
            return;
        }
        
        $uri =  parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($route === $uri) {
            $router = self::create();
            $router->setParams($_GET ?? []);
            $router->execute($callback);
        }
            
        return;
    }
    
    /**
     * Создадим экземпляр класса
     */
    protected static function create(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
    
        $result = new self();
    
        return $result;
    }

    /**
     * Получим параметры и передадим их в контроллер
     */
    protected function execute(callable|string $callback): void
    {
        if ($callback instanceof Closure) {
            call_user_func_array($callback, $this->params);
            return;
        }

        if (strpos($callback, '::') == true) {
            [$cotrollerName, $actionName] = explode('::', $callback);
            if (!class_exists($cotrollerName)) {
                return;
            }
            if (!method_exists($cotrollerName, $actionName)) {
                return;
            }
            $controller = new $cotrollerName();
            $controller->$actionName($this->params);
        }
        
        return;
    }
    
    /**
     * Добавим обраотку POST - запросов
     */
    public static function post(string $route, callable|string $callback): void
    {
        if (!$_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }
        
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($uri === $route) {
            $router = self::create();
            $router->setParams($_POST ?? []);
            $router->execute($callback);
        }

        return;
    }

    /**
     * Отправим ошибку, если маршрут не найден
     */
    public static function end(): void
    {
        self::sendError('Route not found', 404);
    }
}

