<?php

declare(strict_types=1);

namespace KSG;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;

class Mailer
{
    private PDO $db;
    private bool $enabled;
    private array $config;

    public function __construct()
    {
        $this->db      = Database::getInstance()->getPdo();
        $this->enabled = filter_var($_ENV['MAIL_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->config = [
            'host'       => $_ENV['MAIL_HOST']        ?? 'localhost',
            'port'       => (int)($_ENV['MAIL_PORT']  ?? 587),
            'username'   => $_ENV['MAIL_USERNAME']     ?? '',
            'password'   => $_ENV['MAIL_PASSWORD']     ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION']   ?? 'tls',
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@ksg.ac.ke',
            'from_name'  => $_ENV['MAIL_FROM_NAME']    ?? 'KSG Reports System',
        ];
    }

    // ── Public sending methods ────────────────────────────────────────────────

    public function sendReportNotification(int $reportId): bool
    {
        if (!$this->enabled) {
            error_log("Email notifications are disabled");
            return false;
        }

        $report = $this->getReportDetails($reportId);
        if (!$report) {
            error_log("Report not found: $reportId");
            return false;
        }

        $subject = sprintf(
            "Weekly Status Report - %s - %s",
            CAMPUSES[$report['campus']]        ?? $report['campus'],
            DEPARTMENTS[$report['department']] ?? $report['department']
        );

        $body = $this->buildReportEmail($report);

        if ($report['creator_role'] === 'staff') {
            $hod = $this->getCampusHod($report['campus'], $report['department']);
            if (!$hod) {
                error_log("No HoD found for campus: {$report['campus']}, department: {$report['department']}");
                return false;
            }
            $sent = $this->sendEmail($hod['email'], $hod['name'], $subject, $body, $reportId);

        } elseif ($report['creator_role'] === 'hod') {
            $deputy = $this->getCampusDeputyDirector($report['campus']);
            if (!$deputy) {
                error_log("No deputy director found for campus: {$report['campus']}");
                return false;
            }
            $director = $this->getCampusDirector($report['campus']);
            $bcc      = $director
                ? [['email' => $director['director_email'], 'name' => $director['director_name']]]
                : [];
            $sent = $this->sendEmailWithBcc($deputy['email'], $deputy['name'], $subject, $body, $bcc, $reportId);

        } elseif ($report['creator_role'] === 'deputy_director') {
            $director = $this->getCampusDirector($report['campus']);
            if (!$director) {
                error_log("No director found for campus: {$report['campus']}");
                return false;
            }
            $sent = $this->sendEmail($director['director_email'], $director['director_name'], $subject, $body, $reportId);

        } else {
            return false;
        }

        if ($sent) {
            $this->markReportAsSent($reportId);
        }

        return $sent;
    }

    public function sendTaskNotification(int $taskId): bool
    {
        if (!$this->enabled) {
            error_log("Email notifications are disabled");
            return false;
        }

        $task = $this->getTaskDetails($taskId);
        if (!$task || empty($task['assigned_to_email'])) {
            error_log("Task not found or assignee has no email: $taskId");
            return false;
        }

        $body = $this->buildTaskEmail($task, 'assigned');

        return $this->sendEmailDirect(
            $task['assigned_to_email'],
            $task['assigned_to_name'],
            "New Task Assigned: {$task['title']}",
            $body,
            taskId: $taskId,
            emailType: 'task_assigned'
        );
    }

    public function sendOverdueNotification(array $task): bool
    {
        if (!$this->enabled) {
            error_log("Email notifications are disabled");
            return false;
        }

        $success = true;

        // Notify the assignee
        if (!empty($task['assigned_to_email'])) {
            $body = $this->buildTaskEmail($task, 'overdue_assignee');
            $sent = $this->sendEmailDirect(
                $task['assigned_to_email'],
                $task['assigned_to_name'],
                "Overdue Task: {$task['title']}",
                $body,
                taskId: $task['id'],
                emailType: 'task_overdue'
            );
            if (!$sent) $success = false;
        }

        // Notify the assigner
        if (!empty($task['assigned_by_email'])) {
            $body = $this->buildTaskEmail($task, 'overdue_assigner');
            $sent = $this->sendEmailDirect(
                $task['assigned_by_email'],
                $task['assigned_by_name'],
                "Task Overdue: {$task['title']}",
                $body,
                taskId: $task['id'],
                emailType: 'task_overdue'
            );
            if (!$sent) $success = false;
        }

        return $success;
    }

    public function sendWelcomeEmail(
        string $toEmail,
        string $toName,
        string $plainPassword,
        string $role,
        string $campus
    ): bool {
        if (!$this->enabled) {
            error_log("Email notifications are disabled — welcome email not sent to {$toEmail}");
            return false;
        }

        $appUrl     = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        $loginUrl   = $appUrl . '/login.php';
        $roleName   = ROLES[$role]      ?? ucfirst($role);
        $campusName = CAMPUSES[$campus] ?? ucfirst($campus);
        $year       = date('Y');

        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;line-height:1.6;">
<div style="max-width:600px;margin:0 auto;padding:20px;">
    <div style="background:#5c4a1e;color:white;padding:20px;text-align:center;">
        <h1 style="margin:0;font-size:1.4rem;">Kenya School of Government</h1>
        <p style="margin:4px 0 0;font-size:.95rem;">Reports System — Account Created</p>
    </div>
    <div style="background:#f9f9f9;padding:28px;">
        <p>Dear {$toName},</p>
        <p>Your account on the <strong>KSG Weekly Reports System</strong> has been created.
           Below are your login credentials:</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr>
                <th style="text-align:left;padding:10px 12px;border:1px solid #ddd;background:#f0ede6;width:35%;">Login URL</th>
                <td style="padding:10px 12px;border:1px solid #ddd;">
                    <a href="{$loginUrl}" style="color:#5c4a1e;">{$loginUrl}</a>
                </td>
            </tr>
            <tr>
                <th style="text-align:left;padding:10px 12px;border:1px solid #ddd;background:#f0ede6;">Username (Email)</th>
                <td style="padding:10px 12px;border:1px solid #ddd;font-family:monospace;">{$toEmail}</td>
            </tr>
            <tr>
                <th style="text-align:left;padding:10px 12px;border:1px solid #ddd;background:#f0ede6;">Password</th>
                <td style="padding:10px 12px;border:1px solid #ddd;font-family:monospace;">{$plainPassword}</td>
            </tr>
            <tr>
                <th style="text-align:left;padding:10px 12px;border:1px solid #ddd;background:#f0ede6;">Role</th>
                <td style="padding:10px 12px;border:1px solid #ddd;">{$roleName}</td>
            </tr>
            <tr>
                <th style="text-align:left;padding:10px 12px;border:1px solid #ddd;background:#f0ede6;">Campus</th>
                <td style="padding:10px 12px;border:1px solid #ddd;">{$campusName}</td>
            </tr>
        </table>
        <div style="background:#fff8e1;border-left:4px solid #f0ad00;padding:12px 16px;margin:16px 0;font-size:.9rem;">
            <strong>Security tip:</strong> You can change your password at any time by clicking your name
            in the top navigation bar after logging in, then going to <em>My Profile</em>.
        </div>
        <p style="text-align:center;margin-top:24px;">
            <a href="{$loginUrl}"
               style="display:inline-block;padding:12px 32px;background:#5c4a1e;color:white;text-decoration:none;border-radius:4px;font-weight:bold;">
                Log In Now
            </a>
        </p>
    </div>
    <div style="text-align:center;padding:16px;color:#999;font-size:12px;">
        <p>This is an automated message from the KSG Reports System. Please do not reply.</p>
        <p>Kenya School of Government &copy; {$year}</p>
    </div>
</div>
</body>
</html>
HTML;

        return $this->sendEmailDirect(
            $toEmail,
            $toName,
            'Your KSG Reports System Account',
            $body,
            taskId: null,
            emailType: 'welcome'
        );
    }

    // ── Email builders ────────────────────────────────────────────────────────

    private function buildTaskEmail(array $task, string $type): string
    {
        $appUrl        = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        $viewUrl       = $appUrl . '/task_view.php?id=' . $task['id'];
        $deadline      = date('d M Y', strtotime($task['deadline']));
        $deptName      = DEPARTMENTS[$task['department']] ?? ($task['department'] ?? '—');
        $year          = date('Y');
        $headerBg      = '#5c4a1e';
        $headerTitle   = 'Task Assignment Notification';
        $intro         = "A new task has been assigned to you by <strong>{$task['assigned_by_name']}</strong>.";
        $btnLabel      = 'View Task';
        $btnColor      = '#5c4a1e';
        $deadlineStyle = 'color:#c0392b;';

        if ($type === 'overdue_assignee') {
            $headerBg      = '#c0392b';
            $headerTitle   = 'Overdue Task Notification';
            $intro         = "The following task assigned to you by <strong>{$task['assigned_by_name']}</strong> is now <strong style=\"color:#c0392b;\">overdue</strong>. Please action it immediately.";
            $btnLabel      = 'View Overdue Task';
            $btnColor      = '#c0392b';
            $deadlineStyle = 'color:#c0392b;font-weight:bold;';
        } elseif ($type === 'overdue_assigner') {
            $headerBg      = '#c0392b';
            $headerTitle   = 'Overdue Task Alert';
            $intro         = "A task you assigned to <strong>{$task['assigned_to_name']}</strong> is now <strong style=\"color:#c0392b;\">overdue</strong> and has not been completed.";
            $btnLabel      = 'View Overdue Task';
            $btnColor      = '#c0392b';
            $deadlineStyle = 'color:#c0392b;font-weight:bold;';
        }

        $recipient = ($type === 'overdue_assigner')
            ? $task['assigned_by_name']
            : $task['assigned_to_name'];

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;line-height:1.6;">
<div style="max-width:700px;margin:0 auto;padding:20px;">
    <div style="background:{$headerBg};color:white;padding:20px;text-align:center;">
        <h1 style="margin:0;">Kenya School of Government</h1>
        <p style="margin:4px 0 0;">{$headerTitle}</p>
    </div>
    <div style="background:#f9f9f9;padding:24px;">
        <p>Dear {$recipient},</p>
        <p>{$intro}</p>
        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            <tr><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f0f0f0;width:30%;">Task</th><td style="padding:8px;border:1px solid #ddd;">{$task['title']}</td></tr>
            <tr><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f0f0f0;">Description</th><td style="padding:8px;border:1px solid #ddd;">{$task['description']}</td></tr>
            <tr><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f0f0f0;">Department</th><td style="padding:8px;border:1px solid #ddd;">{$deptName}</td></tr>
            <tr><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f0f0f0;">Deadline</th><td style="padding:8px;border:1px solid #ddd;{$deadlineStyle}">{$deadline}</td></tr>
            <tr><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f0f0f0;">Assigned To</th><td style="padding:8px;border:1px solid #ddd;">{$task['assigned_to_name']}</td></tr>
            <tr><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f0f0f0;">Assigned By</th><td style="padding:8px;border:1px solid #ddd;">{$task['assigned_by_name']}</td></tr>
        </table>
        <p style="text-align:center;">
            <a href="{$viewUrl}" style="display:inline-block;padding:12px 28px;background:{$btnColor};color:white;text-decoration:none;border-radius:4px;">{$btnLabel}</a>
        </p>
    </div>
    <div style="text-align:center;padding:16px;color:#999;font-size:12px;">
        <p>This is an automated notification from the KSG Reports System.</p>
        <p>Kenya School of Government &copy; {$year}</p>
    </div>
</div>
</body>
</html>
HTML;
    }

    private function buildReportEmail(array $report): string
    {
        $appUrl     = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        $viewUrl    = $appUrl . '/view.php?id=' . $report['id'];
        $campusName = CAMPUSES[$report['campus']]        ?? $report['campus'];
        $deptName   = DEPARTMENTS[$report['department']] ?? $report['department'];
        $weekStart  = date('d M Y', strtotime($report['reporting_week_start']));
        $weekEnd    = date('d M Y', strtotime($report['reporting_week_end']));
        $year       = date('Y');

        $activitiesHtml = '';
        foreach ($report['activities'] as $activity) {
            $statusLabel = STATUSES[$activity['status']] ?? $activity['status'];
            $activitiesHtml .= sprintf(
                '<tr>
                    <td style="padding:8px;border:1px solid #ddd;">%d</td>
                    <td style="padding:8px;border:1px solid #ddd;">%s</td>
                    <td style="padding:8px;border:1px solid #ddd;"><strong>%s</strong></td>
                    <td style="padding:8px;border:1px solid #ddd;">%s</td>
                </tr>',
                $activity['item_no'],
                htmlspecialchars($activity['activity']),
                htmlspecialchars($statusLabel),
                htmlspecialchars($activity['action_needed'] ?: '—')
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
<div style="max-width:800px;margin:0 auto;padding:20px;">
    <div style="background:#1a5c2a;color:white;padding:20px;text-align:center;">
        <h1>Kenya School of Government</h1>
        <h2>Weekly Status Report</h2>
    </div>
    <div style="background:#f9f9f9;padding:20px;">
        <p>Dear Director,</p>
        <p>A new weekly status report has been submitted for your review.</p>
        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            <tr><th style="background:#f0f0f0;padding:10px;text-align:left;width:30%;border:1px solid #ddd;">Campus:</th><td style="padding:10px;border:1px solid #ddd;">{$campusName}</td></tr>
            <tr><th style="background:#f0f0f0;padding:10px;text-align:left;border:1px solid #ddd;">Department:</th><td style="padding:10px;border:1px solid #ddd;">{$deptName}</td></tr>
            <tr><th style="background:#f0f0f0;padding:10px;text-align:left;border:1px solid #ddd;">HoD/HoS:</th><td style="padding:10px;border:1px solid #ddd;">{$report['hod_name']}</td></tr>
            <tr><th style="background:#f0f0f0;padding:10px;text-align:left;border:1px solid #ddd;">Reporting Week:</th><td style="padding:10px;border:1px solid #ddd;">{$weekStart} to {$weekEnd}</td></tr>
            <tr><th style="background:#f0f0f0;padding:10px;text-align:left;border:1px solid #ddd;">Prepared By:</th><td style="padding:10px;border:1px solid #ddd;">{$report['prepared_by_name']} ({$report['prepared_by_designation']})</td></tr>
        </table>
        <h3>Activities Summary</h3>
        <table style="width:100%;border-collapse:collapse;margin-top:20px;">
            <thead><tr style="background:#1a5c2a;color:white;">
                <th style="padding:10px;text-align:left;width:50px;">No.</th>
                <th style="padding:10px;text-align:left;">Activity</th>
                <th style="padding:10px;text-align:left;width:120px;">Status</th>
                <th style="padding:10px;text-align:left;width:200px;">Action Needed</th>
            </tr></thead>
            <tbody>{$activitiesHtml}</tbody>
        </table>
        <p style="text-align:center;">
            <a href="{$viewUrl}" style="display:inline-block;padding:12px 24px;background:#1a5c2a;color:white;text-decoration:none;border-radius:4px;margin-top:20px;">View Full Report</a>
        </p>
    </div>
    <div style="text-align:center;padding:20px;color:#666;font-size:12px;">
        <p>This is an automated notification from the KSG Weekly Reports System.</p>
        <p>Kenya School of Government &copy; {$year}</p>
    </div>
</div>
</body>
</html>
HTML;
    }

    // ── Private sending helpers ───────────────────────────────────────────────

    private function sendEmail(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        int $reportId
    ): bool {
        $this->logEmailAttempt(
            reportId:  $reportId,
            taskId:    null,
            emailType: 'report',
            email:     $toEmail,
            name:      $toName,
            subject:   $subject
        );

        $mail = new PHPMailer(true);
        try {
            $this->configureMail($mail);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            $mail->send();
            $this->updateEmailLog(reportId: $reportId, taskId: null, email: $toEmail, status: 'sent');
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: {$mail->ErrorInfo}");
            $this->updateEmailLog(reportId: $reportId, taskId: null, email: $toEmail, status: 'failed', error: $mail->ErrorInfo);
            return false;
        }
    }

    private function sendEmailWithBcc(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        array  $bcc,
        int    $reportId
    ): bool {
        $this->logEmailAttempt(
            reportId:  $reportId,
            taskId:    null,
            emailType: 'report',
            email:     $toEmail,
            name:      $toName,
            subject:   $subject
        );

        $mail = new PHPMailer(true);
        try {
            $this->configureMail($mail);
            $mail->addAddress($toEmail, $toName);
            foreach ($bcc as $b) {
                $mail->addBCC($b['email'], $b['name']);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            $mail->send();
            $this->updateEmailLog(reportId: $reportId, taskId: null, email: $toEmail, status: 'sent');
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: {$mail->ErrorInfo}");
            $this->updateEmailLog(reportId: $reportId, taskId: null, email: $toEmail, status: 'failed', error: $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * General-purpose send. Now fully auditable via optional taskId + emailType.
     * report emails still use sendEmail()/sendEmailWithBcc() above.
     */
    private function sendEmailDirect(
        string  $toEmail,
        string  $toName,
        string  $subject,
        string  $body,
        ?int    $taskId    = null,
        string  $emailType = 'task_assigned'
    ): bool {
        $this->logEmailAttempt(
            reportId:  null,
            taskId:    $taskId,
            emailType: $emailType,
            email:     $toEmail,
            name:      $toName,
            subject:   $subject
        );

        $mail = new PHPMailer(true);
        try {
            $this->configureMail($mail);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            $mail->send();
            $this->updateEmailLog(reportId: null, taskId: $taskId, email: $toEmail, status: 'sent');
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed [{$emailType}]: {$mail->ErrorInfo}");
            $this->updateEmailLog(reportId: null, taskId: $taskId, email: $toEmail, status: 'failed', error: $mail->ErrorInfo);
            return false;
        }
    }

    // ── PHPMailer configuration (DRY helper) ──────────────────────────────────

    private function configureMail(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host       = $this->config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->config['username'];
        $mail->Password   = $this->config['password'];
        $mail->SMTPSecure = $this->config['encryption'];
        $mail->Port       = $this->config['port'];
        $mail->setFrom($this->config['from_email'], $this->config['from_name']);
        $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    private function getTaskDetails(int $taskId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   u1.name  AS assigned_by_name,
                   u1.email AS assigned_by_email,
                   u1.role  AS assigned_by_role,
                   u2.name  AS assigned_to_name,
                   u2.email AS assigned_to_email,
                   u2.role  AS assigned_to_role
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_by = u1.id
            LEFT JOIN users u2 ON t.assigned_to = u2.id
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $taskId]);
        return $stmt->fetch() ?: null;
    }

    private function getReportDetails(int $reportId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.name AS creator_name, u.email AS creator_email, u.role AS creator_role
            FROM reports r
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $reportId]);
        $report = $stmt->fetch();
        if (!$report) return null;

        $stmt = $this->db->prepare("
            SELECT * FROM report_activities WHERE report_id = :id ORDER BY item_no ASC
        ");
        $stmt->execute([':id' => $reportId]);
        $report['activities'] = $stmt->fetchAll();
        return $report;
    }

    private function getCampusDirector(string $campus): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM campus_directors WHERE campus = :campus AND is_active = 1 LIMIT 1
        ");
        $stmt->execute([':campus' => $campus]);
        return $stmt->fetch() ?: null;
    }

    private function getCampusDeputyDirector(string $campus): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email FROM users
            WHERE campus = :campus AND role = 'deputy_director' AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':campus' => $campus]);
        return $stmt->fetch() ?: null;
    }

    private function getCampusHod(string $campus, string $department): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email FROM users
            WHERE campus = :campus AND department = :department AND role = 'hod' AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':campus' => $campus, ':department' => $department]);
        return $stmt->fetch() ?: null;
    }

    private function markReportAsSent(int $reportId): void
    {
        $stmt = $this->db->prepare("
            UPDATE reports SET email_sent = 1, email_sent_at = CURRENT_TIMESTAMP WHERE id = :id
        ");
        $stmt->execute([':id' => $reportId]);
    }

    private function logEmailAttempt(
        ?int   $reportId,
        ?int   $taskId,
        string $emailType,
        string $email,
        string $name,
        string $subject
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO email_logs
                (report_id, task_id, email_type, recipient_email, recipient_name, subject, status, created_at)
            VALUES
                (:report_id, :task_id, :email_type, :email, :name, :subject, 'pending', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':report_id'  => $reportId,
            ':task_id'    => $taskId,
            ':email_type' => $emailType,
            ':email'      => $email,
            ':name'       => $name,
            ':subject'    => $subject,
        ]);
    }

    private function updateEmailLog(
        ?int    $reportId,
        ?int    $taskId,
        string  $email,
        string  $status,
        ?string $error = null
    ): void {
        // Scope the UPDATE to the most recent pending row for this recipient
        // matched by whichever ID is available (report or task)
        $scopeClause = $reportId !== null
            ? 'report_id = :scope_id'
            : ($taskId !== null ? 'task_id = :scope_id' : '1=1');

        $scopeId = $reportId ?? $taskId;

        $stmt = $this->db->prepare("
            UPDATE email_logs
            SET status = :status, error_message = :error, sent_at = CURRENT_TIMESTAMP
            WHERE recipient_email = :email
              AND status = 'pending'
              AND {$scopeClause}
              AND id = (
                  SELECT id FROM email_logs
                  WHERE recipient_email = :email2
                    AND status = 'pending'
                    AND {$scopeClause2}
                  ORDER BY created_at DESC LIMIT 1
              )
        ");

        $params = [
            ':status' => $status,
            ':error'  => $error,
            ':email'  => $email,
            ':email2' => $email,
        ];

        if ($scopeId !== null) {
            $params[':scope_id']  = $scopeId;
            $params[':scope_id2'] = $scopeId; // needed for subquery alias
        }

        // Replace placeholder in subquery
        $sql = str_replace('{$scopeClause2}', $scopeClause === '1=1' ? '1=1' : str_replace(':scope_id', ':scope_id2', $scopeClause), $stmt->queryString ?? '');

        // Re-prepare with correct subquery scope
        $finalSql = "
            UPDATE email_logs
            SET status = :status, error_message = :error, sent_at = CURRENT_TIMESTAMP
            WHERE recipient_email = :email
              AND status = 'pending'
              AND {$scopeClause}
              AND id = (
                  SELECT id FROM email_logs
                  WHERE recipient_email = :email2
                    AND status = 'pending'
                    AND " . ($scopeId !== null ? str_replace(':scope_id', ':scope_id2', $scopeClause) : '1=1') . "
                  ORDER BY created_at DESC LIMIT 1
              )
        ";

        $stmt = $this->db->prepare($finalSql);
        $stmt->execute($params);
    }

    public function testConnection(): bool
    {
        if (!$this->enabled) {
            echo "Email is disabled in configuration\n";
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $this->configureMail($mail);
            $mail->SMTPDebug = 0;
            $mail->Timeout   = 10;
            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("SMTP connection test failed: {$mail->ErrorInfo}");
            return false;
        }
    }
}