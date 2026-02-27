<?php
$role = $currentUser['role'];
?>
<div class="container">
    <div class="page-header">
        <h1>Weekly Status Reports</h1>
        <?php if (in_array($role, ['staff', 'hod', 'director', 'admin'])): ?>
            <a href="/submit.php" class="btn btn-primary">+ New Report</a>
        <?php endif; ?>
    </div>

    <!-- Filter bar — only shown to director and admin who can actually filter -->
    <?php if (in_array($role, ['director', 'admin'])): ?>
    <div class="filter-bar">
        <form method="GET" action="/index.php" class="filters-form">
            <?php if ($role === 'admin'): ?>
            <div class="filter-group">
                <label for="filter_campus">Campus</label>
                <select name="campus" id="filter_campus" class="filter-control">
                    <option value="">All Campuses</option>
                    <?php foreach (CAMPUSES as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($filters['campus'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <label for="filter_department">Department</label>
                <select name="department" id="filter_department" class="filter-control">
                    <option value="">All Departments</option>
                    <?php foreach (DEPARTMENTS as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($filters['department'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter_week">Week From</label>
                <input type="date" name="week_start" id="filter_week"
                       value="<?= htmlspecialchars($filters['week_start'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="filter-control">
            </div>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="/index.php" class="btn btn-outline">Clear</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- Scope indicator for staff and hod -->
    <?php if ($role === 'staff'): ?>
        <div class="scope-notice">Showing your submitted reports.</div>
    <?php elseif ($role === 'hod'): ?>
        <div class="scope-notice">
            Showing all reports for
            <strong><?= htmlspecialchars(CAMPUSES[$currentUser['campus']] ?? $currentUser['campus'], ENT_QUOTES, 'UTF-8') ?></strong>
            &mdash;
            <strong><?= htmlspecialchars(DEPARTMENTS[$currentUser['department'] ?? ''] ?? ($currentUser['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.
        </div>
    <?php elseif ($role === 'director'): ?>
        <div class="scope-notice">
            Showing all reports for
            <strong><?= htmlspecialchars(CAMPUSES[$currentUser['campus']] ?? $currentUser['campus'], ENT_QUOTES, 'UTF-8') ?></strong>.
        </div>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <p>No reports found matching your criteria.</p>
            <a href="/submit.php" class="btn btn-primary">Create First Report</a>
        </div>
    <?php else: ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Report Date</th>
                    <?php if (in_array($role, ['admin'])): ?>
                        <th>Campus</th>
                    <?php endif; ?>
                    <?php if (in_array($role, ['admin', 'director'])): ?>
                        <th>Department</th>
                    <?php endif; ?>
                    <th>HoD / HoS</th>
                    <th>Reporting Week</th>
                    <th>Prepared By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= htmlspecialchars($report['report_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($report['report_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <?php if (in_array($role, ['admin'])): ?>
                            <td><?= htmlspecialchars(CAMPUSES[$report['campus']] ?? $report['campus'], ENT_QUOTES, 'UTF-8') ?></td>
                        <?php endif; ?>
                        <?php if (in_array($role, ['admin', 'director'])): ?>
                            <td><?= htmlspecialchars(DEPARTMENTS[$report['department']] ?? $report['department'], ENT_QUOTES, 'UTF-8') ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($report['hod_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars(date('d M', strtotime($report['reporting_week_start'])), ENT_QUOTES, 'UTF-8') ?>
                            &ndash;
                            <?= htmlspecialchars(date('d M Y', strtotime($report['reporting_week_end'])), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td><?= htmlspecialchars($report['prepared_by_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="/view.php?id=<?= (int) $report['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($filters, fn($k) => $k !== 'created_by', ARRAY_FILTER_USE_KEY)) ?>">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters, fn($k) => $k !== 'created_by', ARRAY_FILTER_USE_KEY)) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($filters, fn($k) => $k !== 'created_by', ARRAY_FILTER_USE_KEY)) ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>