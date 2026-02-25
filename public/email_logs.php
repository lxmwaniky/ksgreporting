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

$db = Database::getInstance()->getPdo();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['pending', 'sent', 'failed'])) {
    $where[] = 'status = :status';
    $params[':status'] = $statusFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT e.*, r.campus, r.department, r.report_date
    FROM email_logs e
    JOIN reports r ON e.report_id = r.id
    $whereClause
    ORDER BY e.created_at DESC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$logs = $stmt->fetchAll();

$countStmt = $db->prepare("SELECT COUNT(*) FROM email_logs e $whereClause");
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $perPage);

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <h1>Email Notification Logs</h1>

    <div class="filter-bar">
        <form method="GET" action="/email_logs.php" class="filters-form">
            <div class="filter-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="filter-control">
                    <option value="">All Statuses</option>
                    <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="/email_logs.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <p>No email logs found.</p>
        </div>
    <?php else: ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Report</th>
                    <th>Recipient</th>
                    <th>Status</th>
                    <th>Sent At</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d M Y H:i', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="/view.php?id=<?= (int)$log['report_id'] ?>">
                                <?= htmlspecialchars(CAMPUSES[$log['campus']] ?? $log['campus'], ENT_QUOTES, 'UTF-8') ?> - 
                                <?= htmlspecialchars(DEPARTMENTS[$log['department']] ?? $log['department'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($log['recipient_name'], ENT_QUOTES, 'UTF-8') ?><br>
                            <small><?= htmlspecialchars($log['recipient_email'], ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td>
                            <?php
                            $statusClass = [
                                'sent' => 'badge-completed',
                                'failed' => 'badge-delayed',
                                'pending' => 'badge-pending'
                            ][$log['status']] ?? 'badge-pending';
                            ?>
                            <span class="badge <?= $statusClass ?>">
                                <?= htmlspecialchars(ucfirst($log['status']), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?= $log['sent_at'] ? htmlspecialchars(date('d M Y H:i', strtotime($log['sent_at'])), ENT_QUOTES, 'UTF-8') : '—' ?>
                        </td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                                <small style="color: #c0392b;"><?= htmlspecialchars($log['error_message'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>">« Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
