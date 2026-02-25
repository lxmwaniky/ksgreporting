<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Report;

Auth::startSession();
Auth::requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    die('Invalid report ID.');
}

$reportModel = new Report();
$report      = $reportModel->findById($id);

if (!$report) {
    http_response_code(404);
    die('Report not found.');
}

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/report_view.php';
require_once __DIR__ . '/../templates/footer.php';
