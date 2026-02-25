<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Report;

Auth::startSession();
Auth::requireLogin();

$reportModel = new Report();

$filters = [
    'campus'     => $_GET['campus']     ?? '',
    'department' => $_GET['department'] ?? '',
    'week_start' => $_GET['week_start'] ?? '',
];

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$reports = $reportModel->list($filters, $perPage, $offset);
$total   = $reportModel->count($filters);
$pages   = (int) ceil($total / $perPage);

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/report_list.php';
require_once __DIR__ . '/../templates/footer.php';
