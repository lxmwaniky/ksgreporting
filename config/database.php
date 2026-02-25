<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '=') !== false && !str_starts_with(trim($line), '#')) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

return [
    'host'     => $_ENV['DB_HOST']     ?? 'localhost',
    'port'     => $_ENV['DB_PORT']     ?? '5432',
    'dbname'   => $_ENV['DB_NAME']     ?? 'ksg_reports',
    'username' => $_ENV['DB_USER']     ?? 'postgres',
    'password' => $_ENV['DB_PASS']     ?? '',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];