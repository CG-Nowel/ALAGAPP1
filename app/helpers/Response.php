<?php

declare(strict_types=1);

namespace App\Helpers;

class Response
{
    /**
     * Send a successful JSON response.
     */
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send an error JSON response.
     */
    public static function error(string $message = 'An error occurred', int $code = 400, mixed $data = null): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send a validation error response.
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Send a paginated response.
     */
    public static function paginated(array $data, array $pagination, string $message = 'Success'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination,
        ], 200);
    }

    /**
     * Send a raw JSON response.
     */
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
