<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Patient extends Model
{
    protected string $table = 'patients';

    /**
     * Get patients belonging to a parent.
     */
    public function getByParent(int $parentId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM patients WHERE parent_id = ? ORDER BY first_name",
            [$parentId]
        );
    }

    /**
     * Get full patient details with parent info.
     */
    public function getDetails(int $patientId): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*, u.first_name AS parent_first_name, u.last_name AS parent_last_name,
                    u.email AS parent_email, u.phone AS parent_phone
             FROM patients p
             JOIN users u ON p.parent_id = u.id
             WHERE p.id = ?",
            [$patientId]
        );
    }

    /**
     * Check if a patient belongs to a specific parent.
     */
    public function belongsToParent(int $patientId, int $parentId): bool
    {
        return $this->db->fetchColumn(
            "SELECT COUNT(*) FROM patients WHERE id = ? AND parent_id = ?",
            [$patientId, $parentId]
        ) > 0;
    }

    /**
     * Get patients seen by a specific doctor.
     */
    public function getByDoctor(int $doctorId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT p.*, u.first_name AS parent_first_name, u.last_name AS parent_last_name
             FROM patients p
             JOIN users u ON p.parent_id = u.id
             JOIN appointments a ON a.patient_id = p.id
             WHERE a.doctor_id = ?
             ORDER BY p.last_name, p.first_name
             LIMIT ? OFFSET ?",
            [$doctorId, $limit, $offset]
        );
    }

    /**
     * Search patients.
     */
    public function search(string $query, ?int $parentId = null, int $limit = 15): array
    {
        $sql = "SELECT p.*, u.first_name AS parent_first_name, u.last_name AS parent_last_name
                FROM patients p
                JOIN users u ON p.parent_id = u.id
                WHERE (p.first_name LIKE ? OR p.last_name LIKE ?)";
        $params = ["%$query%", "%$query%"];

        if ($parentId) {
            $sql .= " AND p.parent_id = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY p.first_name LIMIT ?";
        $params[] = $limit;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get patient stats.
     */
    public function getStats(): array
    {
        return [
            'total_patients' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM patients"),
            'new_this_month' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM patients WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            ),
        ];
    }
}
