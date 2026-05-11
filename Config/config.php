<?php

function quickhire_load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function quickhire_env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

quickhire_load_env(__DIR__ . '/../.env');

return [
  'db' => [
    'host' => quickhire_env('DB_HOST', 'localhost'),
    'name' => quickhire_env('DB_NAME', 'quick_hire'),
    'user' => quickhire_env('DB_USER', 'root'),
    'pass' => quickhire_env('DB_PASS', ''),
    'charset' => quickhire_env('DB_CHARSET', 'utf8mb4')
  ],
  'mail' => [
    'enabled' => filter_var(quickhire_env('MAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'host' => quickhire_env('MAIL_HOST', 'smtp.gmail.com'),
    'port' => (int) quickhire_env('MAIL_PORT', 587),
    'encryption' => quickhire_env('MAIL_ENCRYPTION', 'tls'),
    'username' => quickhire_env('MAIL_USERNAME', ''),
    'password' => quickhire_env('MAIL_PASSWORD', ''),
    'from_email' => quickhire_env('MAIL_FROM_EMAIL', quickhire_env('MAIL_USERNAME', '')),
    'from_name' => quickhire_env('MAIL_FROM_NAME', 'QuickHire')
  ],
  'recaptcha' => [
    'site_key'   => quickhire_env('RECAPTCHA_SITE_KEY', ''),
    'secret_key' => quickhire_env('RECAPTCHA_SECRET_KEY', ''),
  ]
];
