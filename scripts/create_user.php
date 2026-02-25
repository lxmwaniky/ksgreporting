<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Database;

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

function prompt(string $message): string
{
    echo $message;
    return trim(fgets(STDIN));
}

echo "  KSG Reports - User Creation Tool\n\n";

$name = prompt("Full Name: ");
if (empty($name)) {
    die("Error: Name cannot be empty.\n");
}

$email = prompt("Email Address: ");
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Error: Please provide a valid email address.\n");
}

echo "\nAvailable Campuses:\n";
$campusOptions = [];
$index = 1;
foreach (CAMPUSES as $key => $label) {
    echo "  $index. $label\n";
    $campusOptions[$index] = $key;
    $index++;
}

$campusChoice = (int) prompt("\nSelect Campus (1-" . count(CAMPUSES) . "): ");
if (!isset($campusOptions[$campusChoice])) {
    die("Error: Invalid campus selection.\n");
}
$campus = $campusOptions[$campusChoice];

echo "\nUser Roles:\n";
echo "  1. Staff (Regular user)\n";
echo "  2. HoD (Head of Department)\n";
echo "  3. Admin (System Administrator)\n";

$roleChoice = (int) prompt("\nSelect Role (1-3): ");
$roles = [1 => 'staff', 2 => 'hod', 3 => 'admin'];
if (!isset($roles[$roleChoice])) {
    die("Error: Invalid role selection.\n");
}
$role = $roles[$roleChoice];

echo "\nPassword Requirements:\n";
echo "  - Minimum 8 characters\n";
echo "  - At least one uppercase letter\n";
echo "  - At least one number\n\n";

$password = prompt("Password: ");
if (strlen($password) < 8) {
    die("Error: Password must be at least 8 characters.\n");
}

if (!preg_match('/[A-Z]/', $password)) {
    die("Error: Password must contain at least one uppercase letter.\n");
}

if (!preg_match('/[0-9]/', $password)) {
    die("Error: Password must contain at least one number.\n");
}

$confirmPassword = prompt("Confirm Password: ");
if ($password !== $confirmPassword) {
    die("Error: Passwords do not match.\n");
}

echo "\nUser Details\n";
echo "Name:   $name\n";
echo "Email:  $email\n";
echo "Campus: " . CAMPUSES[$campus] . "\n";
echo "Role:   " . ucfirst($role) . "\n\n";

$confirm = prompt("Create this user? (yes/no): ");
if (strtolower($confirm) !== 'yes') {
    die("User creation cancelled.\n");
}

try {
    $db = Database::getInstance()->getPdo();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        die("Error: A user with this email already exists.\n");
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO users (name, email, password_hash, campus, role, is_active, created_at)
        VALUES (:name, :email, :password_hash, :campus, :role, 1, NOW())
    ");

    $stmt->execute([
        ':name'          => trim($name),
        ':email'         => trim($email),
        ':password_hash' => $passwordHash,
        ':campus'        => $campus,
        ':role'          => $role,
    ]);

    $userId = $db->lastInsertId();

    echo "\n✓ User created successfully!\n";
    echo "User ID: $userId\n";
    echo "Login URL: " . ($_ENV['APP_URL'] ?? 'http://localhost/ksg_reporting/public') . "/login.php\n";
    echo "Email:    $email\n";
    echo "Password: (as entered above)\n\n";

} catch (PDOException $e) {
    echo "\n✗ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);