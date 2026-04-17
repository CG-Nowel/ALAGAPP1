<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class VaccinationRecord extends Model
{
    protected string $table = 'vaccination_records';

    /**
     * Get vaccination history for a patient.
     */
    public function getByPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT vr.*, u.first_name AS admin_first_name, u.last_name AS admin_last_name
             FROM vaccination_records vr
             JOIN users u ON vr.administered_by = u.id
             WHERE vr.patient_id = ?
             ORDER BY vr.administration_date DESC",
            [$patientId]
        );
    }

    /**
     * Get upcoming vaccinations for a patient.
     */
    public function getUpcoming(int $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM vaccination_records
             WHERE patient_id = ? AND status IN ('SCHEDULED') AND next_due_date >= CURDATE()
             ORDER BY next_due_date",
            [$patientId]
        );
    }

    /**
     * Get overdue vaccinations for a patient.
     */
    public function getOverdue(int $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM vaccination_records
             WHERE patient_id = ? AND status = 'OVERDUE'
             ORDER BY next_due_date",
            [$patientId]
        );
    }

    /**
     * Get vaccination stats.
     */
    public function getStats(): array
    {
        return [
            'total_administered' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM vaccination_records WHERE status = 'COMPLETED'"
            ),
            'this_month' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM vaccination_records
                 WHERE status = 'COMPLETED'
                 AND MONTH(administration_date) = MONTH(CURDATE())
                 AND YEAR(administration_date) = YEAR(CURDATE())"
            ),
            'overdue' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM vaccination_records WHERE status = 'OVERDUE'"
            ),
        ];
    }
}
