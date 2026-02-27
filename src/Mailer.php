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
        $this->db = Database::getInstance()->getPdo();
        $this->enabled = filter_var($_ENV['MAIL_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        $this->config = [
            'host'       => $_ENV['MAIL_HOST']       ?? 'localhost',
            'port'       => (int)($_ENV['MAIL_PORT'] ?? 587),
            'username'   => $_ENV['MAIL_USERNAME']   ?? '',
            'password'   => $_ENV['MAIL_PASSWORD']   ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@ksg.ac.ke',
            'from_name'  => $_ENV['MAIL_FROM_NAME']  ?? 'KSG Reports System',
        ];
    }

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

        $director = $this->getCampusDirector($report['campus']);
        if (!$director) {
            error_log("No director found for campus: {$report['campus']}");
            return false;
        }

        $subject = sprintf(
            "Weekly Status Report - %s - %s",
            CAMPUSES[$report['campus']] ?? $report['campus'],
            DEPARTMENTS[$report['department']] ?? $report['department']
        );

        $body = $this->buildReportEmail($report);

        $sent = $this->sendEmail(
            $director['director_email'],
            $director['director_name'],
            $subject,
            $body,
            $reportId
        );

        if ($sent) {
            $this->markReportAsSent($reportId);
        }

        return $sent;
    }

    private function sendEmail(
        string $toEmail, 
        string $toName, 
        string $subject, 
        string $body, 
        int $reportId
    ): bool {
        $this->logEmailAttempt($reportId, $toEmail, $toName, $subject);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port       = $this->config['port'];

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();
            
            $this->updateEmailLog($reportId, $toEmail, 'sent');
            return true;

        } catch (Exception $e) {
            error_log("Email sending failed: {$mail->ErrorInfo}");
            $this->updateEmailLog($reportId, $toEmail, 'failed', $mail->ErrorInfo);
            return false;
        }
    }

    private function getReportDetails(int $reportId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.name as creator_name, u.email as creator_email
            FROM reports r
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM report_activities 
            WHERE report_id = :id 
            ORDER BY item_no ASC
        ");
        $stmt->execute([':id' => $reportId]);
        $report['activities'] = $stmt->fetchAll();

        return $report;
    }

    private function getCampusDirector(string $campus): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM campus_directors 
            WHERE campus = :campus AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([':campus' => $campus]);
        return $stmt->fetch() ?: null;
    }

    private function buildReportEmail(array $report): string
    {
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $viewUrl = $appUrl . '/public/view.php?id=' . $report['id'];
        
        $campusName = CAMPUSES[$report['campus']] ?? $report['campus'];
        $deptName = DEPARTMENTS[$report['department']] ?? $report['department'];
        
        $weekStart = date('d M Y', strtotime($report['reporting_week_start']));
        $weekEnd = date('d M Y', strtotime($report['reporting_week_end']));
        $year = date('Y');

        $activitiesHtml = '';
        foreach ($report['activities'] as $activity) {
            $statusLabel = STATUSES[$activity['status']] ?? $activity['status'];
            $activitiesHtml .= sprintf(
                '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd;">%d</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">%s</td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">%s</td>
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
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background: #1a5c2a; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table th { background: #f0f0f0; padding: 10px; text-align: left; width: 30%; border: 1px solid #ddd; }
        .info-table td { padding: 10px; border: 1px solid #ddd; }
        .activities-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .activities-table th { background: #1a5c2a; color: white; padding: 10px; text-align: left; }
        .button { display: inline-block; padding: 12px 24px; background: #1a5c2a; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kenya School of Government</h1>
            <h2>Weekly Status Report</h2>
        </div>
        
        <div class="content">
            <p>Dear Director,</p>
            <p>A new weekly status report has been submitted for your review.</p>
            
            <table class="info-table">
                <tr>
                    <th>Campus:</th>
                    <td>{$campusName}</td>
                </tr>
                <tr>
                    <th>Department:</th>
                    <td>{$deptName}</td>
                </tr>
                <tr>
                    <th>HoD/HoS:</th>
                    <td>{$report['hod_name']}</td>
                </tr>
                <tr>
                    <th>Reporting Week:</th>
                    <td>{$weekStart} to {$weekEnd}</td>
                </tr>
                <tr>
                    <th>Prepared By:</th>
                    <td>{$report['prepared_by_name']} ({$report['prepared_by_designation']})</td>
                </tr>
            </table>
            
            <h3>Activities Summary</h3>
            <table class="activities-table">
                <thead>
                    <tr>
                        <th width="50">No.</th>
                        <th>Activity</th>
                        <th width="120">Status</th>
                        <th width="200">Action Needed</th>
                    </tr>
                </thead>
                <tbody>
                    {$activitiesHtml}
                </tbody>
            </table>
            
            <p style="text-align: center;">
                <a href="{$viewUrl}" class="button">View Full Report</a>
            </p>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from the KSG Weekly Reports System.</p>
            <p>Kenya School of Government &copy; {$year}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function markReportAsSent(int $reportId): void
    {
        $stmt = $this->db->prepare("
            UPDATE reports 
            SET email_sent = 1, email_sent_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $reportId]);
    }

    private function logEmailAttempt(
        int $reportId, 
        string $email, 
        string $name, 
        string $subject
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO email_logs (report_id, recipient_email, recipient_name, subject, status, created_at)
            VALUES (:report_id, :email, :name, :subject, 'pending', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':report_id' => $reportId,
            ':email'     => $email,
            ':name'      => $name,
            ':subject'   => $subject,
        ]);
    }

    private function updateEmailLog(
        int $reportId, 
        string $email, 
        string $status, 
        ?string $error = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE email_logs 
            SET status = :status, error_message = :error, sent_at = CURRENT_TIMESTAMP
            WHERE report_id = :report_id 
            AND recipient_email = :email 
            AND status = 'pending'
            AND id = (
                SELECT id FROM email_logs 
                WHERE report_id = :report_id2 
                AND recipient_email = :email2 
                AND status = 'pending'
                ORDER BY created_at DESC 
                LIMIT 1
            )
        ");
        $stmt->execute([
            ':status'     => $status,
            ':error'      => $error,
            ':report_id'  => $reportId,
            ':email'      => $email,
            ':report_id2' => $reportId,
            ':email2'     => $email,
        ]);
    }

    public function testConnection(): bool
    {
        if (!$this->enabled) {
            echo "Email is disabled in configuration\n";
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port       = $this->config['port'];

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