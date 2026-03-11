<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/constants.php';

use KSG\Auth;
use KSG\Report;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$currentUser = Auth::user();
$role        = $currentUser['role'];
$db          = Database::getInstance()->getPdo();
$reportModel = new Report();

$weekStart = $_GET['week_start'] ?? '';
$weekEnd   = $_GET['week_end']   ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

if ($role === 'staff') {
    $filters = ['created_by' => $currentUser['id']];
    if ($weekStart) $filters['week_start'] = $weekStart;
    if ($weekEnd)   $filters['week_end']   = $weekEnd;
    $reports    = $reportModel->list($filters, $perPage, $offset);
    $total      = $reportModel->count($filters);
    $totalPages = (int)ceil($total / $perPage);

} elseif ($role === 'hod') {
    // HoD sees collapsible blocks: their own reports + staff in their dept
    $hodPerPage = 5;
    $backLink   = 'index.php';
    $extraParams = [];

    $stmt = $db->prepare("SELECT id, name, email, designation FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $currentUser['id']]);
    $hods = $stmt->fetchAll(); // just themselves

    $stmt = $db->prepare("
        SELECT id, name, email, designation
        FROM users
        WHERE campus = :campus AND department = :department AND role = 'staff' AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([':campus' => $currentUser['campus'], ':department' => $currentUser['department']]);
    $staffList = $stmt->fetchAll();

} elseif (in_array($role, ['deputy_director', 'director'])) {
    $filterDept = $_GET['department'] ?? '';
    $stmt = $db->prepare("
        SELECT r.department,
               COUNT(r.id)               AS total_reports,
               MAX(r.created_at)         AS last_submitted,
               COUNT(DISTINCT r.created_by) AS staff_count
        FROM reports r
        WHERE r.campus = :campus
          " . ($filterDept ? "AND r.department = :department" : "") . "
          " . ($weekStart  ? "AND r.reporting_week_start >= :week_start" : "") . "
          " . ($weekEnd    ? "AND r.reporting_week_end <= :week_end" : "") . "
        GROUP BY r.department
        ORDER BY r.department ASC
    ");
    $params = [':campus' => $currentUser['campus']];
    if ($filterDept) $params[':department'] = $filterDept;
    if ($weekStart)  $params[':week_start'] = $weekStart;
    if ($weekEnd)    $params[':week_end']   = $weekEnd;
    $stmt->execute($params);
    $departmentSummary = $stmt->fetchAll();

} elseif ($role === 'admin') {
    $filterCampus = $_GET['campus']     ?? '';
    $filterDept   = $_GET['department'] ?? '';
    $stmt = $db->prepare("
        SELECT r.campus, r.department,
               COUNT(r.id)              AS total_reports,
               MAX(r.created_at)        AS last_submitted,
               COUNT(DISTINCT r.created_by) AS staff_count
        FROM reports r
        WHERE 1=1
          " . ($filterCampus ? "AND r.campus = :campus" : "") . "
          " . ($filterDept   ? "AND r.department = :department" : "") . "
          " . ($weekStart    ? "AND r.reporting_week_start >= :week_start" : "") . "
          " . ($weekEnd      ? "AND r.reporting_week_end <= :week_end" : "") . "
        GROUP BY r.campus, r.department
        ORDER BY r.campus, r.department ASC
    ");
    $params = [];
    if ($filterCampus) $params[':campus']     = $filterCampus;
    if ($filterDept)   $params[':department'] = $filterDept;
    if ($weekStart)    $params[':week_start'] = $weekStart;
    if ($weekEnd)      $params[':week_end']   = $weekEnd;
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $campusSummary = [];
    foreach ($rows as $row) {
        $campusSummary[$row['campus']][] = $row;
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-header-content">
            <h1>Weekly Status Reports</h1>
            <?php if (in_array($role, ['staff', 'hod', 'deputy_director', 'director', 'admin'])): ?>
                <a href="./submit.php" class="btn btn-primary">+ New Report</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="./index.php" class="filters-form">
            <?php if ($role === 'admin'): ?>
            <div class="filter-group">
                <label>Campus</label>
                <select name="campus" class="filter-control">
                    <option value="">All Campuses</option>
                    <?php foreach (CAMPUSES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($filterCampus ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (in_array($role, ['admin', 'deputy_director', 'director'])): ?>
            <div class="filter-group">
                <label>Department</label>
                <select name="department" class="filter-control">
                    <option value="">All Departments</option>
                    <?php foreach (DEPARTMENTS as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($filterDept ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <label>Week From</label>
                <input type="date" name="week_start" class="filter-control"
                       value="<?= htmlspecialchars($weekStart) ?>">
            </div>
            <div class="filter-group">
                <label>Week To</label>
                <input type="date" name="week_end" class="filter-control"
                       value="<?= htmlspecialchars($weekEnd) ?>">
            </div>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="./index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <?php if ($role === 'staff'): ?>

        <div class="scope-notice">Showing your submitted reports.</div>
        <?php if (empty($reports)): ?>
            <div class="empty-state"><p>No reports found.</p></div>
        <?php else: ?>
            <?php include __DIR__ . '/../../templates/report_list.php'; ?>
        <?php endif; ?>

    <?php elseif ($role === 'hod'): ?>

        <div class="scope-notice">
            <strong><?= htmlspecialchars(CAMPUSES[$currentUser['campus']] ?? $currentUser['campus']) ?></strong>
            &mdash;
            <strong><?= htmlspecialchars(DEPARTMENTS[$currentUser['department']] ?? $currentUser['department']) ?></strong>
        </div>

        <?php
        $perPage = $hodPerPage;
        include __DIR__ . '/../../templates/report_blocks.php';
        ?>

    <?php elseif (in_array($role, ['deputy_director', 'director'])): ?>

        <div class="scope-notice">
            <strong><?= htmlspecialchars(CAMPUSES[$currentUser['campus']] ?? $currentUser['campus']) ?></strong>
            &mdash; all departments
        </div>

        <?php if (empty($departmentSummary)): ?>
            <div class="empty-state"><p>No reports found.</p></div>
        <?php else: ?>
            <div class="dept-summary-grid">
                <?php foreach ($departmentSummary as $dept): ?>
                <div class="dept-summary-card">
                    <div class="dept-summary-header">
                        <h3><?= htmlspecialchars(DEPARTMENTS[$dept['department']] ?? $dept['department']) ?></h3>
                    </div>
                    <div class="dept-summary-stats">
                        <div class="stat">
                            <span class="stat-value"><?= (int)$dept['total_reports'] ?></span>
                            <span class="stat-label">Reports</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?= (int)$dept['staff_count'] ?></span>
                            <span class="stat-label">Contributors</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?= date('d M', strtotime($dept['last_submitted'])) ?></span>
                            <span class="stat-label">Last Submitted</span>
                        </div>
                    </div>
                    <a href="./department_reports.php?department=<?= urlencode($dept['department']) ?>&campus=<?= urlencode($currentUser['campus']) ?><?= $weekStart ? '&week_start='.urlencode($weekStart) : '' ?><?= $weekEnd ? '&week_end='.urlencode($weekEnd) : '' ?>"
                       class="btn btn-primary btn-block">View Reports</a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($role === 'admin'): ?>

        <div class="scope-notice">Showing all campuses.</div>

        <?php if (empty($campusSummary)): ?>
            <div class="empty-state"><p>No reports found.</p></div>
        <?php else: ?>
            <?php foreach ($campusSummary as $c => $depts): ?>
            <div class="campus-block">
                <h2 class="campus-heading"><?= htmlspecialchars(CAMPUSES[$c] ?? $c) ?></h2>
                <div class="dept-summary-grid">
                    <?php foreach ($depts as $dept): ?>
                    <div class="dept-summary-card">
                        <div class="dept-summary-header">
                            <h3><?= htmlspecialchars(DEPARTMENTS[$dept['department']] ?? $dept['department']) ?></h3>
                        </div>
                        <div class="dept-summary-stats">
                            <div class="stat">
                                <span class="stat-value"><?= (int)$dept['total_reports'] ?></span>
                                <span class="stat-label">Reports</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value"><?= (int)$dept['staff_count'] ?></span>
                                <span class="stat-label">Contributors</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value"><?= date('d M', strtotime($dept['last_submitted'])) ?></span>
                                <span class="stat-label">Last Submitted</span>
                            </div>
                        </div>
                        <a href="./department_reports.php?department=<?= urlencode($dept['department']) ?>&campus=<?= urlencode($c) ?><?= $weekStart ? '&week_start='.urlencode($weekStart) : '' ?><?= $weekEnd ? '&week_end='.urlencode($weekEnd) : '' ?>"
                           class="btn btn-primary btn-block">View Reports</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($role === 'staff' && isset($totalPages) && $totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&week_start=<?= urlencode($weekStart) ?>&week_end=<?= urlencode($weekEnd) ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&week_start=<?= urlencode($weekStart) ?>&week_end=<?= urlencode($weekEnd) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&week_start=<?= urlencode($weekStart) ?>&week_end=<?= urlencode($weekEnd) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>