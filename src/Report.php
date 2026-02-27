<?php

declare(strict_types=1);

namespace KSG;

use PDO;
use InvalidArgumentException;
use RuntimeException;

class Report
{
    private PDO $db;

    private const CAMPUS_CODES = [
        'nairobi' => 'NBO',
        'mombasa' => 'MSA',
        'matuga'  => 'MTG',
        'embu'    => 'EBU',
        'baringo' => 'BRG',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance()->getPdo();
    }

    public function generateReportCode(string $campus): string
    {
        $campusCode = self::CAMPUS_CODES[$campus] ?? strtoupper(substr($campus, 0, 3));

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM reports WHERE campus = :campus
        ");
        $stmt->execute([':campus' => $campus]);
        $count = (int) $stmt->fetchColumn();

        $serial = str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);

        return "KSG/01/{$campusCode}/{$serial}";
    }

    public function create(array $data): int
    {
        $this->validateReportData($data);

        $this->db->beginTransaction();

        try {
            $reportCode = $this->generateReportCode($data['campus']);

            $stmt = $this->db->prepare("
                INSERT INTO reports 
                    (report_code, campus, department, hod_name, reporting_week_start, reporting_week_end, 
                     report_date, prepared_by_name, prepared_by_designation, prepared_date,
                     reviewed_by_name, reviewed_by_designation, reviewed_date, created_by, created_at)
                VALUES 
                    (:report_code, :campus, :department, :hod_name, :week_start, :week_end, 
                     :report_date, :prep_name, :prep_desig, :prep_date,
                     :rev_name, :rev_desig, :rev_date, :created_by, CURRENT_TIMESTAMP)
                RETURNING id
            ");

            $currentUser = Auth::currentUser();

            $stmt->execute([
                ':report_code' => $reportCode,
                ':campus'      => $data['campus'],
                ':department'  => $data['department'],
                ':hod_name'    => trim($data['hod_name']),
                ':week_start'  => $data['week_start'],
                ':week_end'    => $data['week_end'],
                ':report_date' => $data['report_date'],
                ':prep_name'   => trim($data['prepared_by_name']),
                ':prep_desig'  => trim($data['prepared_by_designation']),
                ':prep_date'   => $data['prepared_date'] ?? date('Y-m-d'),
                ':rev_name'    => !empty($data['reviewed_by_name']) ? trim($data['reviewed_by_name']) : null,
                ':rev_desig'   => !empty($data['reviewed_by_designation']) ? trim($data['reviewed_by_designation']) : null,
                ':rev_date'    => $data['reviewed_date'] ?? null,
                ':created_by'  => $currentUser['id'] ?? null,
            ]);

            $result   = $stmt->fetch();
            $reportId = (int) $result['id'];

            $this->insertActivities($reportId, $data['activities']);
            $this->db->commit();

            return $reportId;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Report creation failed: " . $e->getMessage());
            throw new RuntimeException('Failed to save report. Please try again.');
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM reports WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $report = $stmt->fetch();

        if (!$report) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM report_activities 
            WHERE report_id = :id 
            ORDER BY item_no ASC
        ");
        $stmt->execute([':id' => $id]);
        $report['activities'] = $stmt->fetchAll();

        return $report;
    }

    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['campus'])) {
            $where[]           = 'campus = :campus';
            $params[':campus'] = $filters['campus'];
        }

        if (!empty($filters['department'])) {
            $where[]               = 'department = :department';
            $params[':department'] = $filters['department'];
        }

        if (!empty($filters['week_start'])) {
            $where[]               = 'reporting_week_start >= :week_start';
            $params[':week_start'] = $filters['week_start'];
        }

        $sql = "SELECT id, report_code, campus, department, hod_name, reporting_week_start, 
                       reporting_week_end, report_date, prepared_by_name, created_at
                FROM reports 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['campus'])) {
            $where[]           = 'campus = :campus';
            $params[':campus'] = $filters['campus'];
        }

        if (!empty($filters['department'])) {
            $where[]               = 'department = :department';
            $params[':department'] = $filters['department'];
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reports WHERE " . implode(' AND ', $where));
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function insertActivities(int $reportId, array $activities): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO report_activities (report_id, item_no, activity, status, action_needed, notes)
            VALUES (:report_id, :item_no, :activity, :status, :action_needed, :notes)
        ");

        foreach ($activities as $index => $act) {
            if (empty(trim($act['activity'] ?? ''))) {
                continue;
            }

            $stmt->execute([
                ':report_id'     => $reportId,
                ':item_no'       => $index + 1,
                ':activity'      => trim($act['activity']),
                ':status'        => !empty($act['status']) ? $act['status'] : 'pending',
                ':action_needed' => trim($act['action_needed'] ?? ''),
                ':notes'         => trim($act['notes'] ?? ''),
            ]);
        }
    }

    private function validateReportData(array $data): void
    {
        $required = [
            'campus', 'department', 'hod_name', 'week_start', 'week_end',
            'report_date', 'prepared_by_name', 'prepared_by_designation',
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("The field '{$field}' is required.");
            }
        }

        if (!array_key_exists($data['campus'], CAMPUSES)) {
            throw new InvalidArgumentException('Please select a valid campus.');
        }

        if (!array_key_exists($data['department'], DEPARTMENTS)) {
            throw new InvalidArgumentException('Please select a valid department.');
        }

        if ($data['week_start'] > $data['week_end']) {
            throw new InvalidArgumentException('Week end date must be after start date.');
        }

        if (empty($data['activities']) || !is_array($data['activities'])) {
            throw new InvalidArgumentException('Please add at least one activity.');
        }

        $hasValidActivity = false;
        foreach ($data['activities'] as $act) {
            if (!empty(trim($act['activity'] ?? ''))) {
                $hasValidActivity = true;
                break;
            }
        }

        if (!$hasValidActivity) {
            throw new InvalidArgumentException('Please provide at least one activity description.');
        }
    }
}