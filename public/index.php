<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Report;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::user();
$reportModel = new Report();

$filterCampus = $_GET['campus'] ?? '';
$filterDept = $_GET['department'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$filters = [];
if ($filterCampus) $filters['campus'] = $filterCampus;
if ($filterDept) $filters['department'] = $filterDept;

if ($currentUser['role'] === 'hod') {
    $filters['campus'] = $currentUser['campus'];
    $filters['department'] = $currentUser['department'] ?? '';
} elseif ($currentUser['role'] === 'director') {
    $filters['campus'] = $currentUser['campus'];
}

$reports = $reportModel->list($filters, $page, $perPage);
$total = $reportModel->count($filters);
$totalPages = (int) ceil($total / $perPage);

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/report_list.php';
require_once __DIR__ . '/../templates/footer.php';