<div class="container">
    <h1>Weekly Status Reports</h1>

    <div class="filter-bar">
        <form method="GET" action="/index.php" class="filters-form">
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
            <a href="/index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <p>No reports found matching your criteria.</p>
            <a href="/submit.php" class="btn btn-primary">Create First Report</a>
        </div>
    <?php else: ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Report Date</th>
                    <th>Campus</th>
                    <th>Department</th>
                    <th>HoD/HoS</th>
                    <th>Reporting Week</th>
                    <th>Submitted By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($report['report_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(CAMPUSES[$report['campus']] ?? $report['campus'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(DEPARTMENTS[$report['department']] ?? $report['department'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($report['hod_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars(date('d M', strtotime($report['reporting_week_start'])), ENT_QUOTES, 'UTF-8') ?> - 
                            <?= htmlspecialchars(date('d M Y', strtotime($report['reporting_week_end'])), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td><?= htmlspecialchars($report['prepared_by_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="/view.php?id=<?= (int)$report['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>">« Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
