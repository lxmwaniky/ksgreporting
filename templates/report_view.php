<?php
if (!isset($report)) {
    die('Report data not available');
}

$campusName = CAMPUSES[$report['campus']] ?? $report['campus'];
$deptName   = DEPARTMENTS[$report['department']] ?? $report['department'];
$reportCode = $report['report_code'] ?? 'N/A';
?>

<div class="report-page-wrap">

    <div class="report-toolbar no-print">
        <a href="/index.php" class="btn btn-outline">&#8592; Back to Reports</a>
        <button onclick="window.print()" class="btn btn-primary">Print / Save PDF</button>
    </div>

    <div class="report-document">

        <!-- Document Header -->
        <div class="doc-header">
            <img src="/assets/img/ksg-logo.png" alt="KSG Logo" class="doc-logo">
            <div class="doc-header-title">
                <h1>KENYA SCHOOL OF GOVERNMENT</h1>
                <h2>Weekly Status Report</h2>
                <p class="doc-ref">Ref: <strong><?= htmlspecialchars($reportCode) ?></strong></p>
            </div>
        </div>

        <div class="doc-divider"></div>

        <!-- Report Meta -->
        <table class="doc-meta-table">
            <tr>
                <th>Campus</th>
                <td><?= htmlspecialchars($campusName) ?></td>
                <th>Department</th>
                <td><?= htmlspecialchars($deptName) ?></td>
            </tr>
            <tr>
                <th>Head of Department</th>
                <td><?= htmlspecialchars($report['hod_name']) ?></td>
                <th>Report Date</th>
                <td><?= date('d M Y', strtotime($report['report_date'])) ?></td>
            </tr>
            <tr>
                <th>Reporting Period</th>
                <td colspan="3">
                    <?= date('d M Y', strtotime($report['reporting_week_start'])) ?>
                    &mdash;
                    <?= date('d M Y', strtotime($report['reporting_week_end'])) ?>
                </td>
            </tr>
        </table>

        <!-- Activities -->
        <div class="doc-section-heading">ACTIVITIES FOR THE WEEK</div>

        <table class="doc-activities-table">
            <thead>
                <tr>
                    <th style="width:45px; text-align:center;">No.</th>
                    <th>Activity Description</th>
                    <th style="width:130px;">Status</th>
                    <th style="width:180px;">Action Required</th>
                    <th style="width:180px;">Remarks / Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($report['activities'])): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:1.5rem; color:#666;">
                            No activities recorded.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($report['activities'] as $index => $activity): ?>
                        <tr>
                            <td style="text-align:center;"><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($activity['activity'] ?? '') ?></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($activity['status']) ?>">
                                    <?= htmlspecialchars(STATUSES[$activity['status']] ?? $activity['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($activity['action_needed'] ?? '') ?></td>
                            <td><?= htmlspecialchars($activity['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Authorisation -->
        <div class="doc-section-heading">AUTHORISATION</div>

        <div class="doc-signatures">
            <div class="doc-sig-block">
                <p class="doc-sig-role">Prepared By</p>
                <table class="doc-sig-table">
                    <tr><th>Name</th><td><?= htmlspecialchars($report['prepared_by_name']) ?></td></tr>
                    <tr><th>Designation</th><td><?= htmlspecialchars($report['prepared_by_designation']) ?></td></tr>
                    <tr><th>Date</th><td><?= !empty($report['prepared_date']) ? date('d M Y', strtotime($report['prepared_date'])) : '&mdash;' ?></td></tr>
                    <tr><th>Signature</th><td class="sig-space">&nbsp;</td></tr>
                </table>
            </div>

            <div class="doc-sig-block">
                <p class="doc-sig-role">Reviewed By</p>
                <table class="doc-sig-table">
                    <tr><th>Name</th><td><?= htmlspecialchars($report['reviewed_by_name'] ?? '') ?></td></tr>
                    <tr><th>Designation</th><td><?= htmlspecialchars($report['reviewed_by_designation'] ?? '') ?></td></tr>
                    <tr><th>Date</th><td><?= !empty($report['reviewed_date']) ? date('d M Y', strtotime($report['reviewed_date'])) : '&mdash;' ?></td></tr>
                    <tr><th>Signature</th><td class="sig-space">&nbsp;</td></tr>
                </table>
            </div>
        </div>

        <!-- Document Footer -->
        <div class="doc-footer">
            <span>Kenya School of Government &mdash; Empowering the Public Service</span>
            <span>Ref: <?= htmlspecialchars($reportCode) ?> &nbsp;|&nbsp; Generated: <?= date('d M Y, H:i') ?></span>
        </div>

    </div>
</div>