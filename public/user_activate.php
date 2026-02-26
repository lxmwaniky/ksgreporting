<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::currentUser();
if (!in_array($currentUser['role'], ['admin', 'hod'])) {
    http_response_code(403);
    die('Access denied.');
}

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: /users.php');
    exit;
}

$db = Database::getInstance()->getPdo();

$stmt = $db->prepare("SELECT campus FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: /users.php');
    exit;
}

if ($currentUser['role'] === 'hod' && $user['campus'] !== $currentUser['campus']) {
    $_SESSION['error'] = 'Access denied. HoD can only deactivate users in their own campus';
    header('Location: /users.php');
    exit;
}

try {
    $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    
    $_SESSION['success'] = 'User deactivated successfully';
} catch (PDOException $e) {
    error_log('User deactivation error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred';
}

header('Location: /users.php');
exit;
?>
<?php
// ============================================================
// FILE: public/user_activate.php
// ============================================================
?>
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::currentUser();
if (!in_array($currentUser['role'], ['admin', 'hod'])) {
    http_response_code(403);
    die('Access denied.');
}

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: /users.php');
    exit;
}

$db = Database::getInstance()->getPdo();

$stmt = $db->prepare("SELECT campus FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: /users.php');
    exit;
}

if ($currentUser['role'] === 'hod' && $user['campus'] !== $currentUser['campus']) {
    $_SESSION['error'] = 'Access denied. HoD can only activate users in their own campus';
    header('Location: /users.php');
    exit;
}

try {
    $stmt = $db->prepare("UPDATE users SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    
    $_SESSION['success'] = 'User activated successfully';
} catch (PDOException $e) {
    error_log('User activation error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred';
}

header('Location: /users.php');
exit;