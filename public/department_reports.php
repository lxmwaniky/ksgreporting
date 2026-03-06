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

if (!array_key_exists($department, DEPARTMENTS) || !array_key_exists($campus, CAMPUSES)) {
    header('Location: ./index.php');
    exit;
}

if ($role !== 'admin' && $campus !== $currentUser['campus']) {
    header('Location: ./index.php');
    exit;
}

$db          = Database::getInstance()->getPdo();
$perPage     = 5;
$deptName    = DEPARTMENTS[$department];
$campusName  = CAMPUSES[$campus];
$pageTitle   = $deptName . ' — Reports';
$backLink    = 'department_reports.php';
$extraParams = ['department' => $department, 'campus' => $campus];

// HoDs in this department
$stmt = $db->prepare("
    SELECT id, name, email, designation
    FROM users
    WHERE campus = :campus AND department = :department AND role = 'hod' AND is_active = 1
    ORDER BY name ASC
");
$stmt->execute([':campus' => $campus, ':department' => $department]);
$hods = $stmt->fetchAll();

// Staff in this department
$stmt = $db->prepare("
    SELECT id, name, email, designation
    FROM users
    WHERE campus = :campus AND department = :department AND role = 'staff' AND is_active = 1
    ORDER BY name ASC
");
$stmt->execute([':campus' => $campus, ':department' => $department]);
$staffList = $stmt->fetchAll();

// Admin submissions
$adminDateFrom = $_GET['admin_df'] ?? '';
$adminDateTo   = $_GET['admin_dt'] ?? '';
$adminPage     = max(1, (int)($_GET['admin_page'] ?? 1));
$adminOffset   = ($adminPage - 1) * $perPage;
$adminWhere    = ['r.campus = :campus', 'r.department = :department', "u.role = 'admin'"];
$adminParams   = [':campus' => $campus, ':department' => $department];
if ($adminDateFrom) { $adminWhere[] = 'r.reporting_week_start >= :adf'; $adminParams[':adf'] = $adminDateFrom; }
if ($adminDateTo)   { $adminWhere[] = 'r.reporting_week_end   <= :adt'; $adminParams[':adt'] = $adminDateTo; }
$wStr = implode(' AND ', $adminWhere);

$stmt = $db->prepare("SELECT r.*, u.name AS creator_name FROM reports r LEFT JOIN users u ON r.created_by = u.id WHERE $wStr ORDER BY r.reporting_week_start DESC LIMIT :lim OFFSET :off");
foreach ($adminParams as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $adminOffset, PDO::PARAM_INT);
$stmt->execute();
$adminReports = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM reports r LEFT JOIN users u ON r.created_by = u.id WHERE $wStr");
foreach ($adminParams as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$adminTotal  = (int)$stmt->fetchColumn();
$adminPages  = (int)ceil($adminTotal / $perPage);
$adminIsOpen = ($_GET['_open'] ?? '') === 'admin' || $adminDateFrom || $adminDateTo || (int)($_GET['admin_page'] ?? 0) > 1;

// Orphaned reports — created_by IS NULL
$stmt = $db->prepare("
    SELECT r.* FROM reports r
    WHERE r.campus = :campus AND r.department = :department AND r.created_by IS NULL
    ORDER BY r.reporting_week_start DESC
");
$stmt->execute([':campus' => $campus, ':department' => $department]);
$orphanedReports = $stmt->fetchAll();
$orphanedIsOpen  = ($_GET['_open'] ?? '') === 'orphaned';

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-header-content">
            <h1><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?></h1>
            <a href="./index.php" class="btn btn-secondary">&#8592; Back to Reports</a>
        </div>
    </div>

    <div class="scope-notice">
        <?= htmlspecialchars($campusName, ENT_QUOTES, 'UTF-8') ?> &mdash;
        <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <?php include __DIR__ . '/../templates/report_blocks.php'; ?>

    <?php if ($adminTotal > 0 || $adminDateFrom || $adminDateTo): ?>
    <div class="hod-report-block" style="margin-top:1rem;" id="hod-block-admin-section">
        <div class="hod-report-header" onclick="toggleBlock('admin-section')" style="background:#f0ede6;">
            <div>
                <strong>Admin Submissions</strong>
                <span class="task-cluster-count" style="margin-left:.5rem;">
                    <?= $adminTotal ?> report<?= $adminTotal !== 1 ? 's' : '' ?>
                </span>
            </div>
            <span class="task-cluster-toggle" id="icon-hod-admin-section">
                <?= $adminIsOpen ? 'Hide ▴' : 'Show ▾' ?>
            </span>
        </div>
        <div id="body-hod-admin-section"
             style="<?= $adminIsOpen ? '' : 'display:none;' ?> padding:1rem; background:#fff; border:1px solid var(--border); border-top:none; border-radius:0 0 var(--radius) var(--radius);">
            <form method="GET" action="department_reports.php" class="filters-form" style="margin-bottom:1rem;">
                <input type="hidden" name="department" value="<?= htmlspecialchars($department) ?>">
                <input type="hidden" name="campus"     value="<?= htmlspecialchars($campus) ?>">
                <input type="hidden" name="_open"      value="admin">
                <div class="filter-group">
                    <label>Week From</label>
                    <input type="date" name="admin_df" class="filter-control"
                           value="<?= htmlspecialchars($adminDateFrom) ?>">
                </div>
                <div class="filter-group">
                    <label>Week To</label>
                    <input type="date" name="admin_dt" class="filter-control"
                           value="<?= htmlspecialchars($adminDateTo) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="department_reports.php?department=<?= urlencode($department) ?>&campus=<?= urlencode($campus) ?>&_open=admin"
                   class="btn btn-secondary btn-sm">Clear</a>
            </form>
            <?php if (empty($adminReports)): ?>
                <p class="empty-state-inline">No reports found.</p>
            <?php else: ?>
                <table class="list-table">
                    <thead>
                        <tr><th>Ref</th><th>Submitted By</th><th>Reporting Week</th><th>Report Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminReports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['report_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['prepared_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= date('d M', strtotime($r['reporting_week_start'])) ?> &ndash; <?= date('d M Y', strtotime($r['reporting_week_end'])) ?></td>
                            <td><?= date('d M Y', strtotime($r['report_date'])) ?></td>
                            <td><a href="./view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($adminPages > 1): ?>
                <div class="pagination" style="margin-top:.75rem;">
                    <?php for ($p = 1; $p <= $adminPages; $p++):
                        $qs = http_build_query(array_merge($_GET, ['department' => $department, 'campus' => $campus, 'admin_page' => $p, '_open' => 'admin']));
                    ?>
                        <?php if ($p === $adminPage): ?>
                            <span class="active"><?= $p ?></span>
                        <?php else: ?>
                            <a href="department_reports.php?<?= $qs ?>#hod-block-admin-section"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($orphanedReports)): ?>
    <div class="hod-report-block" style="margin-top:1rem;" id="hod-block-orphaned">
        <div class="hod-report-header" onclick="toggleBlock('orphaned')"
             style="background:#f5eded; border-left-color:#c0392b;">
            <div>
                <strong>Unknown / Unlinked Submissions</strong>
                <span class="task-cluster-count" style="margin-left:.5rem;">
                    <?= count($orphanedReports) ?> report<?= count($orphanedReports) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <span class="task-cluster-toggle" id="icon-hod-orphaned">
                <?= $orphanedIsOpen ? 'Hide ▴' : 'Show ▾' ?>
            </span>
        </div>
        <div id="body-hod-orphaned"
             style="<?= $orphanedIsOpen ? '' : 'display:none;' ?> padding:1rem; background:#fff; border:1px solid var(--border); border-top:none; border-radius:0 0 var(--radius) var(--radius);">
            <p class="text-muted" style="margin-bottom:.75rem; font-size:.82rem;">
                These reports have no linked user account. They were submitted without a login session and cannot be attributed to a specific person.
            </p>
            <table class="list-table">
                <thead>
                    <tr><th>Ref</th><th>Prepared By</th><th>Reporting Week</th><th>Report Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($orphanedReports as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['report_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['prepared_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= date('d M', strtotime($r['reporting_week_start'])) ?>
                            &ndash;
                            <?= date('d M Y', strtotime($r['reporting_week_end'])) ?>
                        </td>
                        <td><?= date('d M Y', strtotime($r['report_date'])) ?></td>
                        <td><a href="./view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>