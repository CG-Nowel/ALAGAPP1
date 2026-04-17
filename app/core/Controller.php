<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\Response;

abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Render a view with data.
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data);

        $viewFile = dirname(__DIR__, 2) . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: $view");
        }

        // Start output buffering for layout support
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // If the view set a layout, render it
        if (isset($layout)) {
            $layoutFile = dirname(__DIR__, 2) . '/views/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Get the currently authenticated user.
     */
    protected function currentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, user_type, status, profile_picture FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
    }

    /**
     * Get current user ID.
     */
    protected function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Get current user type.
     */
    protected function userType(): ?string
    {
        return $_SESSION['user_type'] ?? null;
    }

    /**
     * Validate request input.
     */
    protected function validate(array $rules): array
    {
        $errors = [];
        $data = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $_POST[$field] ?? $_GET[$field] ?? null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($fieldRules as $rule) {
                $ruleParts = explode(':', $rule, 2);
                $ruleName = $ruleParts[0];
                $ruleParam = $ruleParts[1] ?? null;

                switch ($ruleName) {
                    case 'required':
                        if (empty($value) && $value !== '0') {
                            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                        }
                        break;
                    case 'email':
                        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = 'Invalid email address.';
                        }
                        break;
                    case 'min':
                        if ($value && strlen($value) < (int)$ruleParam) {
                            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . " must be at least $ruleParam characters.";
                        }
                        break;
                    case 'max':
                        if ($value && strlen($value) > (int)$ruleParam) {
                            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . " must be at most $ruleParam characters.";
                        }
                        break;
                    case 'numeric':
                        if ($value && !is_numeric($value)) {
                            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number.';
                        }
                        break;
                    case 'date':
                        if ($value && !strtotime($value)) {
                            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be a valid date.';
                        }
                        break;
                    case 'in':
                        $allowed = explode(',', $ruleParam);
                        if ($value && !in_array($value, $allowed)) {
                            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is invalid.';
                        }
                        break;
                }
            }

            if ($value !== null) {
                $data[$field] = is_string($value) ? trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) : $value;
            }
        }

        if (!empty($errors)) {
            if ($this->isAjax()) {
                Response::validationError($errors);
                exit;
            }
            $_SESSION['validation_errors'] = $errors;
            $_SESSION['old_input'] = $_POST;
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * Check if request is AJAX.
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get sanitized input value.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_string($value)) {
            return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }
        return $value;
    }

    /**
     * Log an activity.
     */
    protected function logActivity(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
    {
        $this->db->insert('activity_logs', [
            'user_id' => $this->userId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
}
