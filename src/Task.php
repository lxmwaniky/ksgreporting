<?php

declare(strict_types=1);

namespace KSG;

class Task
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getPdo();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tasks (title, description, assigned_by, assigned_to, department, section, campus, deadline, status)
            VALUES (:title, :description, :assigned_by, :assigned_to, :department, :section, :campus, :deadline, 'pending')
            RETURNING id
        ");
        $stmt->execute([
            ':title'       => trim($data['title']),
            ':description' => trim($data['description']),
            ':assigned_by' => $data['assigned_by'],
            ':assigned_to' => $data['assigned_to'],
            ':department'  => $data['department'],
            ':section'     => $data['section'] ?? null,
            ':campus'      => $data['campus'],
            ':deadline'    => $data['deadline'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   u1.name AS assigned_by_name,
                   u1.role AS assigned_by_role,
                   u2.name AS assigned_to_name,
                   u2.email AS assigned_to_email,
                   u2.role AS assigned_to_role
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_by = u1.id
            LEFT JOIN users u2 ON t.assigned_to = u2.id
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getAssignedTo(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name AS assigned_by_name, u.role AS assigned_by_role
            FROM tasks t
            LEFT JOIN users u ON t.assigned_by = u.id
            WHERE t.assigned_to = :user_id
            ORDER BY t.deadline ASC, t.created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAssignedBy(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name AS assigned_to_name, u.role AS assigned_to_role
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.assigned_by = :user_id
            ORDER BY t.deadline ASC, t.created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getHodsInCampus(string $campus): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, role, department, designation
            FROM users
            WHERE campus = :campus
              AND is_active = 1
              AND role = 'hod'
            ORDER BY department, name ASC
        ");
        $stmt->execute([':campus' => $campus]);
        return $stmt->fetchAll();
    }

    public function getStaffInDepartment(string $campus, string $department): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, role, designation
            FROM users
            WHERE campus = :campus
              AND department = :department
              AND is_active = 1
              AND role IN ('staff', 'hod')
            ORDER BY name ASC
        ");
        $stmt->execute([':campus' => $campus, ':department' => $department]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tasks SET status = :status WHERE id = :id
        ");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }
}