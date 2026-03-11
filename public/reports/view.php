<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/constants.php';

use KSG\Auth;
use KSG\Report;

Auth::startSession();
Auth::requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid report ID';
    header('Location: /index.php');
    exit;
}

$reportId    = (int) $_GET['id'];
$reportModel = new Report();
$report      = $reportModel->findById($reportId);

if (!$report) {
    $_SESSION['error'] = 'Report not found';
    header('Location: /index.php');
    exit;
}

$bodyClass = 'hide-footer';

require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/report_view.php';
require_once __DIR__ . '/../../templates/footer.php';