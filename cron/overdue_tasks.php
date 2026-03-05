<?php

/**
 * cron/overdue_tasks.php
 *
 * Marks tasks as overdue and sends email notifications to both
 * the assignee and the assigner when a deadline has passed.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

use KSG\Task;
use KSG\Mailer;
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

$taskObj  = new Task();
$mailer   = new Mailer();
$now      = date('Y-m-d H:i:s');
$total    = 0;
$notified = 0;
$failed   = 0;

echo "[{$now}] Starting overdue task check...\n";

$candidates = $taskObj->getOverdueCandidates();
$total      = count($candidates);

echo "[{$now}] Found {$total} overdue task(s).\n";

foreach ($candidates as $task) {
    $taskObj->markOverdue((int)$task['id']);

    echo "  Task #{$task['id']} — \"{$task['title']}\" (deadline: {$task['deadline']}) marked overdue.\n";

    $sent = $mailer->sendOverdueNotification($task);

    if ($sent) {
        $notified++;
        echo "  ✓ Notifications sent for task #{$task['id']}.\n";
    } else {
        $failed++;
        echo "  ✗ Notification failed for task #{$task['id']} — check error log.\n";
    }
}

$end = date('Y-m-d H:i:s');
echo "[{$end}] Done. Processed: {$total} | Notified: {$notified} | Failed: {$failed}\n\n";