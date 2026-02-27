<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

use KSG\Database;

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

function showMenu(): void
{
    echo "\n\nCampus Directors Management\n";
    echo "1. List all directors\n";
    echo "2. Add/Update director\n";
    echo "3. Deactivate director\n";
    echo "4. Exit\n\n";
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

    echo "\nSelect Campus (1-" . count(CAMPUSES) . "): ";
    $campusChoice = (int) trim(fgets(STDIN));
    
    if (!isset($campusOptions[$campusChoice])) {
        echo "Invalid campus selection.\n";
        return;
    }
    $campus = $campusOptions[$campusChoice];

    echo "Director Name: ";
    $name = trim(fgets(STDIN));
    
    if (empty($name)) {
        echo "Name cannot be empty.\n";
        return;
    }

    echo "Director Email: ";
    $email = trim(fgets(STDIN));
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
                    updated_at = CURRENT_TIMESTAMP
                WHERE campus = :campus
            ");
            $stmt->execute([
                ':name'   => $name,
                ':email'  => $email,
                ':campus' => $campus,
            ]);
            echo "\nDirector updated successfully\n";
        } else {
            $stmt = $db->prepare("
                INSERT INTO campus_directors (campus, director_name, director_email, is_active, created_at)
                VALUES (:campus, :name, :email, 1, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                ':campus' => $campus,
                ':name'   => $name,
                ':email'  => $email,
            ]);
            echo "\nDirector added successfully\n";
        }
    } catch (PDOException $e) {
        echo "\nDatabase Error: " . $e->getMessage() . "\n";
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

    echo "\nSelect Campus (1-" . count(CAMPUSES) . "): ";
    $campusChoice = (int) trim(fgets(STDIN));
    
    if (!isset($campusOptions[$campusChoice])) {
        echo "Invalid campus selection.\n";
        return;
    }
    $campus = $campusOptions[$campusChoice];

    echo "Are you sure you want to deactivate this director? (yes/no): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'yes') {
        echo "Cancelled.\n";
        return;
    }

    try {
        $stmt = $db->prepare("
            UPDATE campus_directors 
            SET is_active = 0, updated_at = CURRENT_TIMESTAMP
            WHERE campus = :campus
        ");
        $stmt->execute([':campus' => $campus]);

        if ($stmt->rowCount() > 0) {
            echo "\nDirector deactivated successfully\n";
        } else {
            echo "\nNo director found for this campus\n";
        }
    } catch (PDOException $e) {
        echo "\nDatabase Error: " . $e->getMessage() . "\n";
    }
}

try {
    $db = Database::getInstance()->getPdo();

    while (true) {
        showMenu();
        echo "Select option (1-4): ";
        $choice = trim(fgets(STDIN));

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
                echo "\nGoodbye\n\n";
                exit(0);
            default:
                echo "\nInvalid option\n";
        }

        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
}