<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::user();
$role        = $currentUser['role'];

if (!in_array($role, ['deputy_director', 'director', 'admin'])) {
    header('Location: ./index.php');
    exit;
}

$department = $_GET['department'] ?? '';
$campus     = $_GET['campus']     ?? $currentUser['campus'];
$weekStart  = $_GET['week_start'] ?? '';
$weekEnd    = $_GET['week_end']   ?? '';

if (!array_key_exists($department, DEPARTMENTS) || !array_key_exists($campus, CAMPUSES)) {
    header('Location: ./index.php');
    exit;
}

if ($role !== 'admin' && $campus !== $currentUser['campus']) {
    header('Location: ./index.php');
    exit;
}

$db = Database::getInstance()->getPdo();

$dateCondition = '';
$dateParams    = [];
if ($weekStart) { $dateCondition .= " AND r.reporting_week_start >= :week_start"; $dateParams[':week_start'] = $weekStart; }
if ($weekEnd)   { $dateCondition .= " AND r.reporting_week_end <= :week_end";     $dateParams[':week_end']   = $weekEnd;   }

$baseParams = array_merge([':campus' => $campus, ':department' => $department], $dateParams);

// HoD / HoS / Team Leader reports
$stmt = $db->prepare("
    SELECT r.*, u.role AS creator_role, u.name AS creator_name_joined
    FROM reports r
    LEFT JOIN users u ON r.created_by = u.id
    WHERE r.campus = :campus
      AND r.department = :department
      AND u.role = 'hod'
      $dateCondition
    ORDER BY r.reporting_week_start DESC, r.created_at DESC
");
$stmt->execute($baseParams);
$hodReports = $stmt->fetchAll();

// Staff reports
$stmt = $db->prepare("
    SELECT r.*, u.role AS creator_role, u.name AS creator_name_joined
    FROM reports r
    LEFT JOIN users u ON r.created_by = u.id
    WHERE r.campus = :campus
      AND r.department = :department
      AND u.role = 'staff'
      $dateCondition
    ORDER BY u.name ASC, r.reporting_week_start DESC
");
$stmt->execute($baseParams);
$staffRows = $stmt->fetchAll();

$staffReports = [];
foreach ($staffRows as $row) {
    $staffReports[$row['creator_name_joined']][] = $row;
}

// Admin-submitted reports
$stmt = $db->prepare("
    SELECT r.*, u.role AS creator_role, u.name AS creator_name_joined
    FROM reports r
    LEFT JOIN users u ON r.created_by = u.id
    WHERE r.campus = :campus
      AND r.department = :department
      AND u.role = 'admin'
      $dateCondition
    ORDER BY r.reporting_week_start DESC, r.created_at DESC
");
$stmt->execute($baseParams);
$adminReports = $stmt->fetchAll();

// Orphaned reports — created_by is NULL or user account deleted
$stmt = $db->prepare("
    SELECT r.*, NULL AS creator_role, NULL AS creator_name_joined
    FROM reports r
    LEFT JOIN users u ON r.created_by = u.id
    WHERE r.campus = :campus
      AND r.department = :department
      AND (r.created_by IS NULL OR u.id IS NULL)
      $dateCondition
    ORDER BY r.reporting_week_start DESC, r.created_at DESC
");
$stmt->execute($baseParams);
$orphanedReports = $stmt->fetchAll();

$deptName   = DEPARTMENTS[$department] ?? $department;
$campusName = CAMPUSES[$campus] ?? $campus;
$pageTitle  = $deptName . ' — Reports';

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-header-content">
            <h1><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?></h1>
            <a href="./index.php<?= $weekStart || $weekEnd ? '?week_start='.urlencode($weekStart).'&week_end='.urlencode($weekEnd) : '' ?>"
               class="btn btn-secondary">&#8592; Back to Reports</a>
        </div>
    </div>

    <div class="scope-notice">
        <?= htmlspecialchars($campusName, ENT_QUOTES, 'UTF-8') ?>
        &mdash; <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($weekStart || $weekEnd): ?>
            &mdash; <?= htmlspecialchars($weekStart, ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($weekEnd, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </div>

    <?php
    $backLink = './index.php' . ($weekStart || $weekEnd ? '?week_start='.urlencode($weekStart).'&week_end='.urlencode($weekEnd) : '');

    function renderReportTable(array $reports, bool $showSubmitter = true): void { ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <?php if ($showSubmitter): ?><th>Submitted By</th><?php endif; ?>
                    <th>Reporting Week</th>
                    <th>Report Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['report_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <?php if ($showSubmitter): ?>
                    <td><?= htmlspecialchars($r['prepared_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <?php endif; ?>
                    <td>
                        <?= htmlspecialchars(date('d M', strtotime($r['reporting_week_start'])), ENT_QUOTES, 'UTF-8') ?>
                        &ndash;
                        <?= htmlspecialchars(date('d M Y', strtotime($r['reporting_week_end'])), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($r['report_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a href="./view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php } ?>

    <!-- HoD / HoS / Team Leader Reports -->
    <div class="dept-section">
        <h2 class="section-heading">HoD / HoS / Team Leader Reports</h2>
        <?php if (empty($hodReports)): ?>
            <p class="empty-state">No HoD reports found.</p>
        <?php else: ?>
            <?php renderReportTable($hodReports); ?>
        <?php endif; ?>
    </div>

    <!-- Staff Reports -->
    <div class="dept-section" style="margin-top:2rem;">
        <h2 class="section-heading">Staff Reports</h2>
        <?php if (empty($staffReports)): ?>
            <p class="empty-state">No staff reports found.</p>
        <?php else: ?>
            <?php foreach ($staffReports as $staffName => $reports): ?>
            <div class="staff-block">
                <h3 class="staff-heading"><?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?></h3>
                <?php renderReportTable($reports, false); ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Admin-submitted Reports -->
    <?php if (!empty($adminReports)): ?>
    <div class="dept-section" style="margin-top:2rem;">
        <h2 class="section-heading">Admin Submissions</h2>
        <?php renderReportTable($adminReports); ?>
    </div>
    <?php endif; ?>

    <!-- Orphaned Reports -->
    <?php if (!empty($orphanedReports)): ?>
    <div class="dept-section" style="margin-top:2rem;">
        <h2 class="section-heading">Unknown / Deleted User</h2>
        <?php renderReportTable($orphanedReports); ?>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>