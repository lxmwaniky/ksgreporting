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
            ':department'  => $data['department'] ?? null,
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
                   u1.name  AS assigned_by_name,
                   u1.role  AS assigned_by_role,
                   u2.name  AS assigned_to_name,
                   u2.email AS assigned_to_email,
                   u2.role  AS assigned_to_role
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_by = u1.id
            LEFT JOIN users u2 ON t.assigned_to = u2.id
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getAssignedTo(int $userId, string $role, string $campus, ?string $department = null): array
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

    public function getAssignedBy(int $userId, string $role, string $campus, ?string $department = null): array
    {
        if ($role === 'hod') {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name AS assigned_to_name, u.role AS assigned_to_role
                FROM tasks t
                INNER JOIN users u ON t.assigned_to = u.id
                WHERE t.assigned_by = :user_id
                  AND u.campus = :campus
                  AND u.department = :department
                  AND u.role = 'staff'
                ORDER BY t.deadline ASC, t.created_at DESC
            ");
            $stmt->execute([
                ':user_id'    => $userId,
                ':campus'     => $campus,
                ':department' => $department,
            ]);

        } elseif ($role === 'deputy_director') {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name AS assigned_to_name, u.role AS assigned_to_role
                FROM tasks t
                INNER JOIN users u ON t.assigned_to = u.id
                WHERE t.assigned_by = :user_id
                  AND u.campus = :campus
                  AND u.role = 'hod'
                ORDER BY t.deadline ASC, t.created_at DESC
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':campus'  => $campus,
            ]);

        } elseif ($role === 'director') {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name AS assigned_to_name, u.role AS assigned_to_role
                FROM tasks t
                INNER JOIN users u ON t.assigned_to = u.id
                WHERE t.assigned_by = :user_id
                  AND u.campus = :campus
                  AND u.role = 'deputy_director'
                ORDER BY t.deadline ASC, t.created_at DESC
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':campus'  => $campus,
            ]);

        } else {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name AS assigned_to_name, u.role AS assigned_to_role
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.assigned_by = :user_id
                ORDER BY t.deadline ASC, t.created_at DESC
            ");
            $stmt->execute([':user_id' => $userId]);
        }

        return $stmt->fetchAll();
    }

    public function getAssignees(string $role, string $campus, ?string $department = null): array
    {
        if ($role === 'director') {
            $stmt = $this->db->prepare("
                SELECT id, name, email, role, department, designation
                FROM users
                WHERE campus = :campus AND role = 'deputy_director' AND is_active = 1
                ORDER BY name ASC
            ");
            $stmt->execute([':campus' => $campus]);

        } elseif ($role === 'deputy_director') {
            $stmt = $this->db->prepare("
                SELECT id, name, email, role, department, designation
                FROM users
                WHERE campus = :campus AND role = 'hod' AND is_active = 1
                ORDER BY department, name ASC
            ");
            $stmt->execute([':campus' => $campus]);

        } elseif ($role === 'hod') {
            $stmt = $this->db->prepare("
                SELECT id, name, email, role, department, designation
                FROM users
                WHERE campus = :campus AND department = :department
                  AND role = 'staff' AND is_active = 1
                ORDER BY name ASC
            ");
            $stmt->execute([':campus' => $campus, ':department' => $department]);

        } else {
            return [];
        }

        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tasks SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id
        ");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Fetch all tasks that are past their deadline and not yet completed,
     * cancelled, or already marked overdue. Used by the cron job.
     */
    public function getOverdueCandidates(): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   u1.name  AS assigned_by_name,
                   u1.email AS assigned_by_email,
                   u2.name  AS assigned_to_name,
                   u2.email AS assigned_to_email
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_by = u1.id
            LEFT JOIN users u2 ON t.assigned_to = u2.id
            WHERE t.deadline < CURRENT_DATE
              AND t.status NOT IN ('completed', 'cancelled', 'overdue')
              AND t.overdue_notified = 0
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark a task as overdue and flag that the notification has been sent.
     */
    public function markOverdue(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tasks
            SET status = 'overdue', overdue_notified = 1, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }
}