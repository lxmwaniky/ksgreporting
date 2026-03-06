<?php
/**
 * Reusable collapsible HoD + Staff report blocks.
 *
 * Required variables before include:
 *   array  $hods        - HoD user rows
 *   array  $staffList   - Staff user rows for the department
 *   PDO    $db
 *   int    $perPage     - records per page (default 5)
 *   string $backLink    - page filename e.g. 'department_reports.php'
 *   array  $extraParams - GET params to carry forward e.g. ['department'=>'ict','campus'=>'mombasa']
 */

if (!function_exists('drFetchReports')) {
    function drFetchReports(\PDO $db, int $userId, string $dateFrom, string $dateTo, int $offset, int $perPage): array {
        $where  = ['r.created_by = :uid'];
        $params = [':uid' => $userId];
        if ($dateFrom) { $where[] = 'r.reporting_week_start >= :df'; $params[':df'] = $dateFrom; }
        if ($dateTo)   { $where[] = 'r.reporting_week_end   <= :dt'; $params[':dt'] = $dateTo; }
        $stmt = $db->prepare(
            "SELECT r.* FROM reports r WHERE " . implode(' AND ', $where) .
            " ORDER BY r.reporting_week_start DESC, r.created_at DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    function drCountReports(\PDO $db, int $userId, string $dateFrom, string $dateTo): int {
        $where  = ['r.created_by = :uid'];
        $params = [':uid' => $userId];
        if ($dateFrom) { $where[] = 'r.reporting_week_start >= :df'; $params[':df'] = $dateFrom; }
        if ($dateTo)   { $where[] = 'r.reporting_week_end   <= :dt'; $params[':dt'] = $dateTo; }
        $stmt = $db->prepare("SELECT COUNT(*) FROM reports r WHERE " . implode(' AND ', $where));
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // Renders a smart pagination bar with ellipsis and anchor scrolling.
    function drPagination(int $curPage, int $totalPages, string $backLink, string $anchorId, array $qs): string {
        if ($totalPages <= 1) return '';

        $html = '<div class="pagination" style="margin-top:.75rem;">';

        if ($curPage > 1) {
            $prev = http_build_query(array_merge($qs, array_filter([$qs['_page_key'] ?? null => $curPage - 1])));
            // Build prev properly below
        }

        $pageKey = $qs['_page_key'] ?? '_page';
        unset($qs['_page_key']);

        $makeLink = function(int $p) use ($backLink, $anchorId, $pageKey, $qs): string {
            return $backLink . '?' . http_build_query(array_merge($qs, [$pageKey => $p])) . '#' . $anchorId;
        };

        if ($curPage > 1) {
            $html .= '<a href="' . $makeLink($curPage - 1) . '">&laquo;</a>';
        }

        $window = range(max(1, $curPage - 2), min($totalPages, $curPage + 2));

        if (!in_array(1, $window)) {
            $html .= '<a href="' . $makeLink(1) . '">1</a>';
            if ($window[0] > 2) $html .= '<span style="padding:0 4px;">…</span>';
        }

        foreach ($window as $p) {
            if ($p === $curPage) {
                $html .= '<span class="active">' . $p . '</span>';
            } else {
                $html .= '<a href="' . $makeLink($p) . '">' . $p . '</a>';
            }
        }

        if (!in_array($totalPages, $window)) {
            if (end($window) < $totalPages - 1) $html .= '<span style="padding:0 4px;">…</span>';
            $html .= '<a href="' . $makeLink($totalPages) . '">' . $totalPages . '</a>';
        }

        if ($curPage < $totalPages) {
            $html .= '<a href="' . $makeLink($curPage + 1) . '">&raquo;</a>';
        }

        $html .= '</div>';
        return $html;
    }
}

$perPage   = $perPage ?? 5;
$openBlock = $_GET['_open'] ?? '';

if (empty($hods) && empty($staffList)): ?>
    <div class="empty-state"><p>No users found for this department.</p></div>
<?php endif; ?>

<?php foreach ($hods as $hod):
    $hid      = (int)$hod['id'];
    $pfx      = 'h' . $hid;
    $dateFrom = $_GET[$pfx . '_df']   ?? '';
    $dateTo   = $_GET[$pfx . '_dt']   ?? '';
    $page     = max(1, (int)($_GET[$pfx . '_page'] ?? 1));
    $offset   = ($page - 1) * $perPage;
    $total    = drCountReports($db, $hid, $dateFrom, $dateTo);
    $pages    = (int)ceil($total / $perPage);
    $reports  = drFetchReports($db, $hid, $dateFrom, $dateTo, $offset, $perPage);
    // Also keep HoD block open if any of its staff members have an active page or open state
    $staffOpen = false;
    foreach ($staffList as $_s) {
        $_spfx = 's' . (int)$_s['id'];
        if ($openBlock === $_spfx || (int)($_GET[$_spfx.'_page'] ?? 0) > 1) {
            $staffOpen = true;
            break;
        }
    }
    $isOpen = ($openBlock === $pfx || $dateFrom !== '' || $dateTo !== '' || (int)($_GET[$pfx.'_page'] ?? 0) > 1 || $staffOpen);
?>

<div class="hod-report-block" id="hod-block-<?= $hid ?>">

    <div class="hod-report-header" onclick="toggleBlock('<?= $hid ?>')">
        <div>
            <strong><?= htmlspecialchars($hod['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if (!empty($hod['designation'])): ?>
                <span class="text-muted"> &mdash; <?= htmlspecialchars($hod['designation'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <span class="task-cluster-count" style="margin-left:.5rem;">
                <?= $total ?> report<?= $total !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;">
            <?php if (!empty($staffList)): ?>
            <button type="button" class="btn btn-sm btn-secondary"
                    onclick="event.stopPropagation(); toggleStaffPanel(<?= $hid ?>)">
                Staff Reports
            </button>
            <?php endif; ?>
            <span class="task-cluster-toggle" id="icon-hod-<?= $hid ?>">
                <?= $isOpen ? 'Hide ▴' : 'Show ▾' ?>
            </span>
        </div>
    </div>

    <div id="body-hod-<?= $hid ?>"
         style="<?= $isOpen ? '' : 'display:none;' ?> padding:1rem; background:#fff; border:1px solid var(--border); border-top:none; border-radius:0 0 var(--radius) var(--radius);">

        <form method="GET" action="<?= htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8') ?>" class="filters-form" style="margin-bottom:1rem;">
            <?php foreach ($extraParams as $ek => $ev): ?>
                <input type="hidden" name="<?= htmlspecialchars($ek) ?>" value="<?= htmlspecialchars((string)$ev) ?>">
            <?php endforeach; ?>
            <?php foreach ($hods as $oh):
                $ohid = (int)$oh['id'];
                if ($ohid === $hid) continue;
                $opfx = 'h' . $ohid;
                foreach (['_df','_dt','_page'] as $suf):
                    if (!empty($_GET[$opfx.$suf])): ?>
                        <input type="hidden" name="<?= $opfx.$suf ?>" value="<?= htmlspecialchars($_GET[$opfx.$suf]) ?>">
            <?php      endif;
                endforeach;
            endforeach; ?>
            <input type="hidden" name="_open" value="<?= $pfx ?>">
            <div class="filter-group">
                <label>Week From</label>
                <input type="date" name="<?= $pfx ?>_df" class="filter-control"
                       value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="filter-group">
                <label>Week To</label>
                <input type="date" name="<?= $pfx ?>_dt" class="filter-control"
                       value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="<?= htmlspecialchars($backLink . '?' . http_build_query(array_merge($extraParams, ['_open' => $pfx]))) ?>"
               class="btn btn-secondary btn-sm">Clear</a>
        </form>

        <?php if (empty($reports)): ?>
            <p class="empty-state-inline">No reports found.</p>
        <?php else: ?>
            <table class="list-table">
                <thead>
                    <tr><th>Ref</th><th>Reporting Week</th><th>Report Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['report_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
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
            <?php
            echo drPagination($page, $pages, $backLink, 'hod-block-' . $hid, array_merge(
                $extraParams,
                array_filter($_GET, fn($k) => $k !== $pfx.'_page', ARRAY_FILTER_USE_KEY),
                ['_open' => $pfx, '_page_key' => $pfx . '_page']
            ));
            if ($pages > 1): ?>
            <p style="text-align:center;font-size:.78rem;color:#aaa;margin:.25rem 0 0;">
                <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?>
            </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($staffList)):
        // Keep the staff panel open if any staff block under it is active
        $panelOpen = false;
        foreach ($staffList as $_s) {
            $_spfx = 's' . (int)$_s['id'];
            if ($openBlock === $_spfx || (int)($_GET[$_spfx.'_page'] ?? 0) > 1) {
                $panelOpen = true;
                break;
            }
        }
    ?>
    <div id="staff-panel-<?= $hid ?>"
         style="<?= $panelOpen ? '' : 'display:none;' ?> background:#fdfcf8; border:1px solid var(--border); border-top:none; padding:.5rem .5rem .5rem 1.5rem;">
        <?php foreach ($staffList as $staff):
            $sid     = (int)$staff['id'];
            $spfx    = 's' . $sid;
            $sFrom   = $_GET[$spfx . '_df']   ?? '';
            $sTo     = $_GET[$spfx . '_dt']   ?? '';
            $sPage   = max(1, (int)($_GET[$spfx . '_page'] ?? 1));
            $sOffset = ($sPage - 1) * $perPage;
            $sTotal  = drCountReports($db, $sid, $sFrom, $sTo);
            $sPages  = (int)ceil($sTotal / $perPage);
            $sReps   = drFetchReports($db, $sid, $sFrom, $sTo, $sOffset, $perPage);
            $sIsOpen = ($openBlock === $spfx || $sFrom !== '' || $sTo !== '' || (int)($_GET[$spfx.'_page'] ?? 0) > 1);
        ?>
        <div class="staff-report-block" id="staff-block-<?= $sid ?>">
            <div class="staff-report-header" onclick="toggleStaffBlock(<?= $sid ?>)">
                <span>
                    <?= htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($staff['designation'])): ?>
                        <span class="text-muted"> &mdash; <?= htmlspecialchars($staff['designation'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <span class="task-cluster-count" style="margin-left:.5rem;"><?= $sTotal ?></span>
                </span>
                <span class="task-cluster-toggle" id="icon-staff-<?= $sid ?>">
                    <?= $sIsOpen ? 'Hide ▴' : 'Show ▾' ?>
                </span>
            </div>
            <div id="body-staff-<?= $sid ?>"
                 style="<?= $sIsOpen ? '' : 'display:none;' ?> padding:1rem; background:#fff; border-top:1px solid var(--border);">

                <form method="GET" action="<?= htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8') ?>" class="filters-form" style="margin-bottom:1rem;">
                    <?php foreach ($extraParams as $ek => $ev): ?>
                        <input type="hidden" name="<?= htmlspecialchars($ek) ?>" value="<?= htmlspecialchars((string)$ev) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="_open" value="<?= $spfx ?>">
                    <?php foreach (['_df','_dt'] as $suf):
                        if (!empty($_GET[$pfx.$suf])): ?>
                            <input type="hidden" name="<?= $pfx.$suf ?>" value="<?= htmlspecialchars($_GET[$pfx.$suf]) ?>">
                    <?php   endif;
                    endforeach; ?>
                    <div class="filter-group">
                        <label>Week From</label>
                        <input type="date" name="<?= $spfx ?>_df" class="filter-control"
                               value="<?= htmlspecialchars($sFrom) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Week To</label>
                        <input type="date" name="<?= $spfx ?>_dt" class="filter-control"
                               value="<?= htmlspecialchars($sTo) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="<?= htmlspecialchars($backLink . '?' . http_build_query(array_merge($extraParams, ['_open' => $spfx]))) ?>"
                       class="btn btn-secondary btn-sm">Clear</a>
                </form>

                <?php if (empty($sReps)): ?>
                    <p class="empty-state-inline">No reports found.</p>
                <?php else: ?>
                    <table class="list-table">
                        <thead>
                            <tr><th>Ref</th><th>Reporting Week</th><th>Report Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sReps as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['report_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
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
                    <?php
                    echo drPagination($sPage, $sPages, $backLink, 'staff-block-' . $sid, array_merge(
                        $extraParams,
                        array_filter($_GET, fn($k) => $k !== $spfx.'_page', ARRAY_FILTER_USE_KEY),
                        ['_open' => $spfx, '_page_key' => $spfx . '_page']
                    ));
                    if ($sPages > 1): ?>
                    <p style="text-align:center;font-size:.78rem;color:#aaa;margin:.25rem 0 0;">
                        <?= $sOffset + 1 ?>–<?= min($sOffset + $perPage, $sTotal) ?> of <?= $sTotal ?>
                    </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
<?php endforeach; ?>

<style>
.hod-report-block { margin-bottom:1rem; border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow); }
.hod-report-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:.85rem 1rem; background:#f5f0e8;
    border-left:4px solid var(--ksg-brown); cursor:pointer; user-select:none;
}
.hod-report-header:hover { background:#ede8de; }
.staff-report-block { margin:.25rem 0; }
.staff-report-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:.6rem 1rem; background:#faf7f0;
    border-left:4px solid var(--ksg-lime); cursor:pointer; user-select:none;
}
.staff-report-header:hover { background:#f0ede0; }
</style>

<script>
function toggleBlock(id) {
    const body = document.getElementById('body-hod-' + id);
    const icon = document.getElementById('icon-hod-' + id);
    if (!body) return;
    const hidden = body.style.display === 'none';
    body.style.display = hidden ? 'block' : 'none';
    icon.textContent   = hidden ? 'Hide ▴' : 'Show ▾';
}
function toggleStaffPanel(hodId) {
    const panel = document.getElementById('staff-panel-' + hodId);
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
function toggleStaffBlock(staffId) {
    const body = document.getElementById('body-staff-' + staffId);
    const icon = document.getElementById('icon-staff-' + staffId);
    if (!body) return;
    const hidden = body.style.display === 'none';
    body.style.display = hidden ? 'block' : 'none';
    icon.textContent   = hidden ? 'Hide ▴' : 'Show ▾';
}
</script>