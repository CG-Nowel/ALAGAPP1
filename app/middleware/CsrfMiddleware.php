<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;

class CsrfMiddleware
{
    /**
     * Initialize CSRF token in session.
     */
    public function init(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Validate CSRF token on state-changing requests.
     */
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Only validate on state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }

        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            if ($this->isAjax()) {
                Response::error('Invalid CSRF token. Please refresh the page.', 403);
            }
            $_SESSION['flash_error'] = 'Session expired. Please try again.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        return true;
    }

    /**
     * Get the current CSRF token.
     */
    public static function token(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Generate a hidden input field with the CSRF token.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
