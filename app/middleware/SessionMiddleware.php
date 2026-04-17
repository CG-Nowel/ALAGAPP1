<?php

declare(strict_types=1);

namespace App\Middleware;

class SessionMiddleware
{
    public function handle(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');

            if ($config['session']['secure']) {
                ini_set('session.cookie_secure', '1');
            }

            session_name($config['session']['name']);
            session_set_cookie_params([
                'lifetime' => $config['session']['lifetime'] * 60,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $config['session']['secure'],
            ]);

            session_start();

            // Regenerate session ID periodically
            if (!isset($_SESSION['_last_regeneration'])) {
                $this->regenerate();
            } elseif (time() - $_SESSION['_last_regeneration'] > 300) {
                $this->regenerate();
            }
        }

        return true;
    }

    private function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_regeneration'] = time();
    }
}
