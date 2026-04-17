<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;

class AuthMiddleware
{
    public function handle(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjax()) {
                Response::error('Unauthorized. Please log in.', 401);
            }
            header('Location: /login');
            exit;
        }

        // Check if user is active
        $db = \App\Core\Database::getInstance();
        $user = $db->fetchOne('SELECT status, force_password_change FROM users WHERE id = ?', [$_SESSION['user_id']]);

        if (!$user || $user['status'] !== 'active') {
            session_destroy();
            if ($this->isAjax()) {
                Response::error('Account is no longer active.', 403);
            }
            header('Location: /login?error=account_inactive');
            exit;
        }

        // Check forced password change
        if ($user['force_password_change'] && !$this->isPasswordChangeRoute()) {
            if ($this->isAjax()) {
                Response::error('Password change required.', 403);
            }
            header('Location: /change-password');
            exit;
        }

        return true;
    }

    private function isPasswordChangeRoute(): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return in_array($uri, ['/change-password', '/logout']);
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
