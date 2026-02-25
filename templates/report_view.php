<div class="container">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="report-actions no-print">
        <a href="/index.php" class="btn btn-secondary">← Back to List</a>
        <button onclick="window.print()" class="btn btn-primary">Print Report</button>
    </div>

    <div class="report-header">
        <img src="/assets/img/ksg-logo.png" alt="KSG Logo" class="logo">
        <h1>KENYA SCHOOL OF GOVERNMENT</h1>
        <h2>WEEKLY STATUS REPORT</h2>
        <p class="form-ref">KSG/01/MSA/01</p>
    </div>

    <table class="info-table">
        <tr>
            <th>Campus:</th>
            <td><?= htmlspecialchars(CAMPUSES[$report['campus']] ?? $report['campus'], ENT_QUOTES, 'UTF-8') ?></td>
            <th>Department/Unit:</th>
            <td><?= htmlspecialchars(DEPARTMENTS[$report['department']] ?? $report['department'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>HoD/HoS/Team Leader:</th>
            <td><?= htmlspecialchars($report['hod_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <th>Date:</th>
            <td><?= htmlspecialchars(date('d M Y', strtotime($report['report_date'])), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Reporting Week:</th>
            <td colspan="3">
                <?= htmlspecialchars(date('d M Y', strtotime($report['reporting_week_start'])), ENT_QUOTES, 'UTF-8') ?> 
                to 
                <?= htmlspecialchars(date('d M Y', strtotime($report['reporting_week_end'])), ENT_QUOTES, 'UTF-8') ?>
            </td>
        </tr>
    </table>

    <h3>Activities / Issues</h3>
    <table class="activities-table view-mode">
        <thead>
            <tr>
                <th>No.</th>
                <th>Activity / Issue</th>
                <th>Status</th>
                <th>Action Needed</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report['activities'] as $activity): ?>
                <tr>
                    <td class="row-num"><?= (int)$activity['item_no'] ?></td>
                    <td><?= htmlspecialchars($activity['activity'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($activity['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(STATUSES[$activity['status']] ?? $activity['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($activity['action_needed'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($activity['notes'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signatures-section">
        <div class="signature-block">
            <h4>Prepared By</h4>
            <p><strong>Name:</strong> <?= htmlspecialchars($report['prepared_by_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Designation:</strong> <?= htmlspecialchars($report['prepared_by_designation'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Date:</strong> <?= htmlspecialchars(date('d M Y', strtotime($report['prepared_date'] ?? $report['report_date'])), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="signature-line"></div>
        </div>

        <?php if (!empty($report['reviewed_by_name'])): ?>
            <div class="signature-block">
                <h4>Reviewed By</h4>
                <p><strong>Name:</strong> <?= htmlspecialchars($report['reviewed_by_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Designation:</strong> <?= htmlspecialchars($report['reviewed_by_designation'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Date:</strong> <?= !empty($report['reviewed_date']) ? htmlspecialchars(date('d M Y', strtotime($report['reviewed_date'])), ENT_QUOTES, 'UTF-8') : '—' ?></p>
                <div class="signature-line"></div>
            </div>
        <?php endif; ?>
    </div>
</div>
