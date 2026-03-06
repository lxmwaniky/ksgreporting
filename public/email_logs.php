<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();
Auth::requireLogin();

$user = Auth::currentUser();
if ($user['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$db      = Database::getInstance()->getPdo();
$perPage = 10;

// Active tab — defaults to 'all'
$activeTab = $_GET['tab'] ?? 'all';
if (!in_array($activeTab, ['all', 'sent', 'failed', 'pending'])) {
    $activeTab = 'all';
}

// Per-tab page numbers preserved in URL as tab_page e.g. ?tab=sent&page=2
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// ── Counts for each tab badge ─────────────────────────────────────────────────
$countStmt = $db->query("
    SELECT status, COUNT(*) AS cnt
    FROM email_logs
    GROUP BY status
");
$rawCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$counts = [
    'all'     => array_sum($rawCounts),
    'sent'    => (int)($rawCounts['sent']    ?? 0),
    'failed'  => (int)($rawCounts['failed']  ?? 0),
    'pending' => (int)($rawCounts['pending'] ?? 0),
];

// ── Fetch logs for active tab ─────────────────────────────────────────────────
$where  = [];
$params = [];

if ($activeTab !== 'all') {
    $where[]          = 'e.status = :status';
    $params[':status'] = $activeTab;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// LEFT JOIN on both reports and tasks since log entries may belong to either
$stmt = $db->prepare("
    SELECT e.*,
           r.campus      AS report_campus,
           r.department  AS report_department,
           t.title       AS task_title,
           t.campus      AS task_campus
    FROM email_logs e
    LEFT JOIN reports r ON e.report_id = r.id
    LEFT JOIN tasks   t ON e.task_id   = t.id
    $whereClause
    ORDER BY e.created_at DESC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$countQ = $db->prepare("SELECT COUNT(*) FROM email_logs e $whereClause");
foreach ($params as $k => $v) $countQ->bindValue($k, $v);
$countQ->execute();
$total = (int)$countQ->fetchColumn();
$pages = (int)ceil($total / $perPage);

// ── Helpers ───────────────────────────────────────────────────────────────────
function emailTypeLabel(string $type): string {
    return match($type) {
        'report'       => 'Report',
        'task_assigned'=> 'Task — Assigned',
        'task_overdue' => 'Task — Overdue',
        'welcome'      => 'Welcome',
        default        => ucfirst($type),
    };
}
function emailTypeBadgeStyle(string $type): string {
    return match($type) {
        'report'        => 'background:#e8f4fd;color:#1a6fa0;',
        'task_assigned' => 'background:#f0ede6;color:#5c4a1e;',
        'task_overdue'  => 'background:#fdecea;color:#c0392b;',
        'welcome'       => 'background:#eafaf1;color:#1e8449;',
        default         => 'background:#f0f0f0;color:#555;',
    };
}

$pageTitle = 'Email Logs';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-header-content">
            <h1>Email Notification Logs</h1>
        </div>
    </div>

    <!-- Status tabs -->
    <div class="email-log-tabs">
        <?php foreach (['all' => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Pending'] as $tab => $label): ?>
        <a href="email_logs.php?tab=<?= $tab ?>"
           class="email-log-tab <?= $activeTab === $tab ? 'email-log-tab--active' : '' ?>
                  <?= $tab === 'failed' && $counts['failed'] > 0 ? 'email-log-tab--alert' : '' ?>">
            <?= $label ?>
            <span class="email-log-tab-count"><?= $counts[$tab] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($logs)): ?>
        <div class="card">
            <p class="empty-state">No <?= $activeTab !== 'all' ? $activeTab : '' ?> email logs found.</p>
        </div>
    <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <table class="list-table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Recipient</th>
                        <th>Status</th>
                        <th>Sent At</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $emailType = $log['email_type'] ?? 'report';
                    ?>
                    <tr>
                        <td style="white-space:nowrap;">
                            <?= date('d M Y', strtotime($log['created_at'])) ?><br>
                            <small class="text-muted"><?= date('H:i', strtotime($log['created_at'])) ?></small>
                        </td>
                        <td>
                            <span style="font-size:.75rem;font-weight:600;padding:3px 8px;border-radius:20px;<?= emailTypeBadgeStyle($emailType) ?>">
                                <?= emailTypeLabel($emailType) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($log['report_id']) && !empty($log['report_campus'])): ?>
                                <a href="view.php?id=<?= (int)$log['report_id'] ?>">
                                    <?= htmlspecialchars(CAMPUSES[$log['report_campus']] ?? $log['report_campus'], ENT_QUOTES, 'UTF-8') ?>
                                    &mdash;
                                    <?= htmlspecialchars(DEPARTMENTS[$log['report_department']] ?? $log['report_department'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php elseif (!empty($log['task_id']) && !empty($log['task_title'])): ?>
                                <a href="task_view.php?id=<?= (int)$log['task_id'] ?>">
                                    <?= htmlspecialchars($log['task_title'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($log['recipient_name'],  ENT_QUOTES, 'UTF-8') ?><br>
                            <small class="text-muted"><?= htmlspecialchars($log['recipient_email'], ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td>
                            <?php
                            $badgeClass = match($log['status']) {
                                'sent'    => 'badge-completed',
                                'failed'  => 'badge-overdue',
                                'pending' => 'badge-pending',
                                default   => 'badge-pending',
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>">
                                <?= ucfirst(htmlspecialchars($log['status'], ENT_QUOTES, 'UTF-8')) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($log['sent_at']): ?>
                                <?= date('d M Y', strtotime($log['sent_at'])) ?><br>
                                <small class="text-muted"><?= date('H:i', strtotime($log['sent_at'])) ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:220px;">
                            <?php if (!empty($log['error_message'])): ?>
                                <small style="color:#c0392b;line-height:1.4;">
                                    <?= htmlspecialchars($log['error_message'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="pagination" style="margin-top:1rem;">
            <?php if ($page > 1): ?>
                <a href="email_logs.php?tab=<?= urlencode($activeTab) ?>&page=<?= $page - 1 ?>">&laquo; Previous</a>
            <?php endif; ?>

            <?php
            // Show first page, window around current, last page
            $window = range(max(1, $page - 2), min($pages, $page + 2));
            if (!in_array(1, $window)) {
                echo '<a href="email_logs.php?tab=' . urlencode($activeTab) . '&page=1">1</a>';
                if ($window[0] > 2) echo '<span style="padding:0 4px;">…</span>';
            }
            foreach ($window as $p):
            ?>
                <?php if ($p === $page): ?>
                    <span class="active"><?= $p ?></span>
                <?php else: ?>
                    <a href="email_logs.php?tab=<?= urlencode($activeTab) ?>&page=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php
            if (!in_array($pages, $window)) {
                if (end($window) < $pages - 1) echo '<span style="padding:0 4px;">…</span>';
                echo '<a href="email_logs.php?tab=' . urlencode($activeTab) . '&page=' . $pages . '">' . $pages . '</a>';
            }
            ?>

            <?php if ($page < $pages): ?>
                <a href="email_logs.php?tab=<?= urlencode($activeTab) ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <p style="text-align:center;font-size:.8rem;color:#aaa;margin-top:.5rem;">
            Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?> entries
        </p>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.email-log-tabs {
    display: flex;
    gap: .5rem;
    margin-bottom: 1.25rem;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0;
}
.email-log-tab {
    display: flex;
    align-items: center;
    gap: .45rem;
    padding: .55rem 1.1rem;
    border-radius: var(--radius) var(--radius) 0 0;
    font-size: .88rem;
    font-weight: 600;
    color: #777;
    text-decoration: none;
    border: 1px solid transparent;
    border-bottom: none;
    margin-bottom: -2px;
    background: transparent;
    transition: background .15s;
}
.email-log-tab:hover { background: #f5f0e8; color: var(--ksg-brown); }
.email-log-tab--active {
    background: #fff;
    color: var(--ksg-brown);
    border-color: var(--border);
    border-bottom-color: #fff;
}
.email-log-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 .4rem;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 700;
    background: #ede8de;
    color: var(--ksg-brown);
}
.email-log-tab--active .email-log-tab-count {
    background: var(--ksg-brown);
    color: #fff;
}
.email-log-tab--alert .email-log-tab-count {
    background: #c0392b;
    color: #fff;
}
</style>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>