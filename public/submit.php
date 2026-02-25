<?php
// public/submit.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Report;

Auth::startSession();
Auth::requireLogin();

$error   = $_SESSION['error']     ?? null;
$formData = $_SESSION['form_data'] ?? [];

unset($_SESSION['error'], $_SESSION['form_data']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid security token. Please refresh and try again.');
    }

    $data = [
        'campus'                   => $_POST['campus']                   ?? '',
        'department'               => $_POST['department']               ?? '',
        'hod_name'                 => $_POST['hod_name']                 ?? '',
        'week_start'               => $_POST['week_start']               ?? '',
        'week_end'                 => $_POST['week_end']                 ?? '',
        'report_date'              => $_POST['report_date']              ?? '',
        'prepared_by_name'         => $_POST['prepared_by_name']         ?? '',
        'prepared_by_designation'  => $_POST['prepared_by_designation']  ?? '',
        'prepared_date'            => $_POST['prepared_date']            ?? '',
        'reviewed_by_name'         => $_POST['reviewed_by_name']         ?? '',
        'reviewed_by_designation'  => $_POST['reviewed_by_designation']  ?? '',
        'reviewed_date'            => $_POST['reviewed_date']            ?? '',
        'activities'               => $_POST['activities']               ?? [],
    ];

    try {
        $reportModel = new Report();
        $reportId    = $reportModel->create($data);

        $_SESSION['success'] = 'Report submitted successfully!';
        header('Location: /view.php?id=' . $reportId);
        exit;

    } catch (\InvalidArgumentException $e) {
        $_SESSION['error'] = $e->getMessage();
        $_SESSION['form_data'] = $data;
        header('Location: /submit.php');
        exit;
        
    } catch (\RuntimeException $e) {
        $_SESSION['error'] = 'A system error occurred. Please try again.';
        $_SESSION['form_data'] = $data;
        error_log('Report submission error: ' . $e->getMessage());
        header('Location: /submit.php');
        exit;
    }
}

$_POST = array_merge($_POST, $formData);

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/report_form.php';
require_once __DIR__ . '/../templates/footer.php';
