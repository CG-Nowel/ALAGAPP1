<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(string ...$roles)
    {
        $this->allowedRoles = $roles;
    }

    public function handle(): bool
    {
        $userType = $_SESSION['user_type'] ?? null;

        if (!$userType || !in_array($userType, $this->allowedRoles)) {
            if ($this->isAjax()) {
                Response::error('Access denied. Insufficient permissions.', 403);
            }
            http_response_code(403);
            header('Location: /dashboard');
            exit;
        }

        return true;
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// Convenience subclasses for route definitions
class AdminMiddleware extends RoleMiddleware
{
    public function __construct()
    {
        parent::__construct('ADMIN');
    }
}

class DoctorMiddleware extends RoleMiddleware
{
    public function __construct()
    {
        parent::__construct('DOCTOR', 'DOCTOR_OWNER');
    }
}

class ParentMiddleware extends RoleMiddleware
{
    public function __construct()
    {
        parent::__construct('PARENT');
    }
}
