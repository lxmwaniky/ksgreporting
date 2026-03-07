<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();


if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$campus = trim($_GET['campus'] ?? '');
if (empty($campus)) {
    echo json_encode(['name' => null]);
    exit;
}

$db   = Database::getInstance()->getPdo();
$stmt = $db->prepare("
    SELECT name FROM users
    WHERE campus = :campus AND role = 'deputy_director' AND is_active = 1
    LIMIT 1
");
$stmt->execute([':campus' => $campus]);
$row = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode(['name' => $row ? $row['name'] : null]);