<?php

declare(strict_types=1);

return [
    'host' => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
    'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@pedicare.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'PediCare Clinic',
    ],
];
