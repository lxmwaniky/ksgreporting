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

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$filters = [];

switch ($currentUser['role']) {

    case 'staff':
        $filters['created_by'] = $currentUser['id'];
        break;

    case 'hod':
        $filters['campus']     = $currentUser['campus'];
        $filters['department'] = $currentUser['department'] ?? '';
        if (!empty($_GET['week_start'])) {
            $filters['week_start'] = $_GET['week_start'];
        }
        break;

    case 'director':
        $filters['campus'] = $currentUser['campus'];
        if (!empty($_GET['department'])) {
            $filters['department'] = $_GET['department'];
        }
        if (!empty($_GET['week_start'])) {
            $filters['week_start'] = $_GET['week_start'];
        }
        break;

    case 'admin':
        if (!empty($_GET['campus']))     $filters['campus']     = $_GET['campus'];
        if (!empty($_GET['department'])) $filters['department'] = $_GET['department'];
        if (!empty($_GET['week_start'])) $filters['week_start'] = $_GET['week_start'];
        break;
}

$reports    = $reportModel->list($filters, $perPage, $offset);
$total      = $reportModel->count($filters);
$totalPages = (int) ceil($total / $perPage);

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/report_list.php';
require_once __DIR__ . '/../templates/footer.php';