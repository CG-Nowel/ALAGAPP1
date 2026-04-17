<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\Response;

class Router
{
    private array $routes = [];
    private array $currentMiddleware = [];

    public function get(string $path, array|string $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array|string $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array|string $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array|string $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function group(array $options, callable $callback): void
    {
        $previousMiddleware = $this->currentMiddleware;

        if (isset($options['middleware'])) {
            $middleware = is_array($options['middleware']) ? $options['middleware'] : [$options['middleware']];
            $this->currentMiddleware = array_merge($this->currentMiddleware, $middleware);
        }

        $prefix = $options['prefix'] ?? '';

        $callback($this, $prefix);

        $this->currentMiddleware = $previousMiddleware;
    }

    public function middleware(array|string $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }
        $this->currentMiddleware = array_merge($this->currentMiddleware, $middleware);
        return $this;
    }

    private function addRoute(string $method, string $path, array|string $handler): self
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $this->currentMiddleware,
        ];
        return $this;
    }

    public function dispatch(string $method, string $uri): void
    {
        // Support _method override for PUT/DELETE from forms
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run middleware
                foreach ($route['middleware'] as $middlewareClass) {
                    $mw = new $middlewareClass();
                    if (!$mw->handle()) {
                        return;
                    }
                }

                // Execute handler
                $this->executeHandler($route['handler'], $params);
                return;
            }
        }

        // 404 Not Found
        if ($this->isAjax()) {
            Response::error('Route not found', 404);
        } else {
            http_response_code(404);
            $this->renderView('errors/404');
        }
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function executeHandler(array|string $handler, array $params): void
    {
        if (is_string($handler)) {
            // "Controller@method" format
            [$class, $method] = explode('@', $handler);
        } else {
            [$class, $method] = $handler;
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Controller class not found: $class");
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Method not found: $class::$method");
        }

        $controller->$method(...$params);
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function renderView(string $view): void
    {
        $file = dirname(__DIR__, 2) . '/views/' . $view . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo '<h1>404 - Page Not Found</h1>';
        }
    }
}
