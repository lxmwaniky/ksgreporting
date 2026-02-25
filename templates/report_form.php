<div class="container">
    <div class="report-header">
        <img src="/assets/img/ksg-logo.png" alt="KSG Logo" class="logo">
        <h1>KENYA SCHOOL OF GOVERNMENT</h1>
        <h2>WEEKLY STATUS REPORT</h2>
        <p class="form-ref">KSG/01/MSA/01</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="/submit.php" id="reportForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(KSG\Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="campus">Campus <span class="required">*</span></label>
                <select name="campus" id="campus" required>
                    <option value="">-- Select Campus --</option>
                    <?php foreach (CAMPUSES as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= ($_POST['campus'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="department">Department / Unit <span class="required">*</span></label>
                <select name="department" id="department" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach (DEPARTMENTS as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= ($_POST['department'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="hod_name">HoD / HoS / Team Leader <span class="required">*</span></label>
                <input type="text" name="hod_name" id="hod_name"
                       value="<?= htmlspecialchars($_POST['hod_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="150">
            </div>

            <div class="form-group">
                <label for="report_date">Date <span class="required">*</span></label>
                <input type="date" name="report_date" id="report_date"
                       value="<?= htmlspecialchars($_POST['report_date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="form-group">
                <label for="week_start">Reporting Week: From <span class="required">*</span></label>
                <input type="date" name="week_start" id="week_start"
                       value="<?= htmlspecialchars($_POST['week_start'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="form-group">
                <label for="week_end">To <span class="required">*</span></label>
                <input type="date" name="week_end" id="week_end"
                       value="<?= htmlspecialchars($_POST['week_end'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
        </div>

        <h3>Activities / Issues</h3>
        <div class="table-responsive">
            <table class="activities-table" id="activitiesTable">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Activity / Issue <span class="required">*</span></th>
                        <th>Status <span class="required">*</span></th>
                        <th>Action Needed</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $existingActivities = $_POST['activities'] ?? array_fill(0, 6, []);
                    foreach ($existingActivities as $i => $act):
                    ?>
                    <tr class="activity-row">
                        <td class="row-num"><?= $i + 1 ?></td>
                        <td>
                            <input type="text" name="activities[<?= $i ?>][activity]"
                                   value="<?= htmlspecialchars($act['activity'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Describe the activity or issue" maxlength="500">
                        </td>
                        <td>
                            <select name="activities[<?= $i ?>][status]">
                                <option value="">-- Status --</option>
                                <?php foreach (STATUSES as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= ($act['status'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="activities[<?= $i ?>][action_needed]"
                                   value="<?= htmlspecialchars($act['action_needed'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Action required" maxlength="500">
                        </td>
                        <td>
                            <input type="text" name="activities[<?= $i ?>][notes]"
                                   value="<?= htmlspecialchars($act['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Additional notes" maxlength="500">
                        </td>
                        <td>
                            <button type="button" class="btn-remove-row" title="Remove row">✕</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="button" id="addRow" class="btn btn-secondary">+ Add Row</button>

        <div class="signatures-grid">
            <fieldset>
                <legend>Prepared By</legend>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="prepared_by_name"
                           value="<?= htmlspecialchars($_POST['prepared_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required maxlength="150">
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" name="prepared_by_designation"
                           value="<?= htmlspecialchars($_POST['prepared_by_designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required maxlength="150">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="prepared_date" value="<?= date('Y-m-d') ?>">
                </div>
            </fieldset>

            <fieldset>
                <legend>Reviewed By</legend>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="reviewed_by_name"
                           value="<?= htmlspecialchars($_POST['reviewed_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="150">
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" name="reviewed_by_designation"
                           value="<?= htmlspecialchars($_POST['reviewed_by_designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="150">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="reviewed_date">
                </div>
            </fieldset>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Report</button>
            <a href="/index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
