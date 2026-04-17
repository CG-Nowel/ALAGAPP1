<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\CsrfMiddleware;
use App\Middleware\SessionMiddleware;

class Application
{
    private static ?Application $instance = null;
    private Router $router;
    private Database $db;
    private array $config;

    private function __construct()
    {
        $this->loadEnvironment();
        $this->config = require dirname(__DIR__, 2) . '/config/app.php';

        date_default_timezone_set($this->config['timezone']);

        $this->db = Database::getInstance();
        $this->router = new Router();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnvironment(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    public function run(): void
    {
        // Initialize session
        (new SessionMiddleware())->handle();

        // Initialize CSRF
        (new CsrfMiddleware())->init();

        // Load routes
        $routeFile = dirname(__DIR__, 2) . '/routes/web.php';
        if (file_exists($routeFile)) {
            $router = $this->router;
            require $routeFile;
        }

        // Dispatch
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove base path if needed
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && $basePath !== '\\') {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        $this->router->dispatch($method, $uri);
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getDb(): Database
    {
        return $this->db;
    }

    public function config(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }
}
