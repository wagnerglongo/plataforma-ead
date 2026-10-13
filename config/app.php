<?php

declare(strict_types=1);

return [
    'app' => [
        'name'     => env('APP_NAME', 'Plataforma EAD'),
        'env'      => env('APP_ENV', 'production'),
        'debug'    => (bool) env('APP_DEBUG', false),
        'url'      => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
        'key'      => app_key(),
        'demo'     => (bool) env('DEMO_MODE', false),
        'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
    ],
    'db' => [
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => (int) env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'ead'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => (string) env('DB_PASSWORD', ''),
    ],
    'mail' => [
        'driver'     => env('MAIL_DRIVER', 'log'), // log | smtp
        'host'       => env('MAIL_HOST', 'smtp.hostinger.com'),
        'port'       => (int) env('MAIL_PORT', 465),
        'encryption' => env('MAIL_ENCRYPTION', 'ssl'), // ssl | tls | none
        'username'   => env('MAIL_USERNAME', ''),
        'password'   => (string) env('MAIL_PASSWORD', ''),
        'from'       => env('MAIL_FROM', 'nao-responda@example.com'),
        'from_name'  => env('MAIL_FROM_NAME', 'Plataforma EAD'),
    ],
    'session' => [
        'name'     => 'ead_session',
        'lifetime' => (int) env('SESSION_LIFETIME', 120), // minutos de inatividade
    ],
    'uploads' => [
        'max_image_mb'    => 4,
        'max_material_mb' => (int) env('UPLOAD_MAX_MATERIAL_MB', 25),
        'max_video_mb'    => (int) env('UPLOAD_MAX_VIDEO_MB', 512),
    ],
    'lgpd' => [
        'terms_version'   => '2026.1',
        'privacy_version' => '2026.1',
    ],
];
