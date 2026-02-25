<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Database;

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

function showMenu(): void
{
    echo "\n\n";
    echo "  Campus Directors Management\n";
    echo "1. List all directors\n";
    echo "2. Add/Update director\n";
    echo "3. Deactivate director\n";
    echo "4. Exit\n";
    echo "\n";
}

function listDirectors(PDO $db): void
{
    $stmt = $db->query("SELECT * FROM campus_directors ORDER BY campus");
    $directors = $stmt->fetchAll();

    echo "\nCurrent Campus Directors:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-15s %-25s %-30s %-8s\n", "Campus", "Name", "Email", "Status");
    echo str_repeat("-", 80) . "\n";

    foreach ($directors as $dir) {
        $campusName = CAMPUSES[$dir['campus']] ?? $dir['campus'];
        $status = $dir['is_active'] ? 'Active' : 'Inactive';
        printf(
            "%-15s %-25s %-30s %-8s\n",
            $campusName,
            $dir['director_name'],
            $dir['director_email'],
            $status
        );
    }
    echo str_repeat("-", 80) . "\n";
}

function addOrUpdateDirector(PDO $db): void
{
    echo "\nAvailable Campuses:\n";
    $campusOptions = [];
    $index = 1;
    foreach (CAMPUSES as $key => $label) {
        echo "  $index. $label\n";
        $campusOptions[$index] = $key;
        $index++;
    }

    $campusChoice = (int) readline("\nSelect Campus (1-" . count(CAMPUSES) . "): ");
    if (!isset($campusOptions[$campusChoice])) {
        echo "Invalid campus selection.\n";
        return;
    }
    $campus = $campusOptions[$campusChoice];

    $name = readline("Director Name: ");
    if (empty(trim($name))) {
        echo "Name cannot be empty.\n";
        return;
    }

    $email = readline("Director Email: ");
    if (empty(trim($email)) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Please provide a valid email address.\n";
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM campus_directors WHERE campus = :campus");
        $stmt->execute([':campus' => $campus]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE campus_directors 
                SET director_name = :name, 
                    director_email = :email, 
                    is_active = 1,
                    updated_at = NOW()
                WHERE campus = :campus
            ");
            $stmt->execute([
                ':name'   => trim($name),
                ':email'  => trim($email),
                ':campus' => $campus,
            ]);
            echo "\n✓ Director updated successfully!\n";
        } else {
            $stmt = $db->prepare("
                INSERT INTO campus_directors (campus, director_name, director_email, is_active, created_at)
                VALUES (:campus, :name, :email, 1, NOW())
            ");
            $stmt->execute([
                ':campus' => $campus,
                ':name'   => trim($name),
                ':email'  => trim($email),
            ]);
            echo "\n✓ Director added successfully!\n";
        }
    } catch (PDOException $e) {
        echo "\n✗ Database Error: " . $e->getMessage() . "\n";
    }
}

function deactivateDirector(PDO $db): void
{
    echo "\nAvailable Campuses:\n";
    $campusOptions = [];
    $index = 1;
    foreach (CAMPUSES as $key => $label) {
        echo "  $index. $label\n";
        $campusOptions[$index] = $key;
        $index++;
    }

    $campusChoice = (int) readline("\nSelect Campus (1-" . count(CAMPUSES) . "): ");
    if (!isset($campusOptions[$campusChoice])) {
        echo "Invalid campus selection.\n";
        return;
    }
    $campus = $campusOptions[$campusChoice];

    $confirm = readline("Are you sure you want to deactivate this director? (yes/no): ");
    if (strtolower($confirm) !== 'yes') {
        echo "Cancelled.\n";
        return;
    }

    try {
        $stmt = $db->prepare("
            UPDATE campus_directors 
            SET is_active = 0, updated_at = NOW()
            WHERE campus = :campus
        ");
        $stmt->execute([':campus' => $campus]);

        if ($stmt->rowCount() > 0) {
            echo "\n✓ Director deactivated successfully!\n";
        } else {
            echo "\n✗ No director found for this campus.\n";
        }
    } catch (PDOException $e) {
        echo "\n✗ Database Error: " . $e->getMessage() . "\n";
    }
}

try {
    $db = Database::getInstance()->getPdo();

    while (true) {
        showMenu();
        $choice = readline("Select option (1-4): ");

        switch ($choice) {
            case '1':
                listDirectors($db);
                break;
            case '2':
                addOrUpdateDirector($db);
                break;
            case '3':
                deactivateDirector($db);
                break;
            case '4':
                echo "\nGoodbye!\n\n";
                exit(0);
            default:
                echo "\nInvalid option. Please try again.\n";
        }

        readline("\nPress Enter to continue...");
    }
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
