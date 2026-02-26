<?php
require_once __DIR__ . '/config/database.php';

echo "Current directory: " . __DIR__ . "\n";
echo "ENV file exists: " . (file_exists(__DIR__ . '/.env') ? 'YES' : 'NO') . "\n";
echo "ENV file readable: " . (is_readable(__DIR__ . '/.env') ? 'YES' : 'NO') . "\n\n";

echo "MAIL_ENABLED value: " . ($_ENV['MAIL_ENABLED'] ?? 'NOT SET') . "\n";
echo "MAIL_HOST value: " . ($_ENV['MAIL_HOST'] ?? 'NOT SET') . "\n";
echo "DB_USER value: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n\n";

echo "Raw .env content:\n";
echo "================\n";
if (file_exists(__DIR__ . '/.env')) {
    echo file_get_contents(__DIR__ . '/.env');
} else {
    echo ".env file not found!\n";
}
