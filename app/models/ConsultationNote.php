<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ConsultationNote extends Model
{
    protected string $table = 'consultation_notes';

    /**
     * Get consultation notes for a patient.
     */
    public function getByPatient(int $patientId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT cn.*, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    d.specialization AS doctor_specialization
             FROM consultation_notes cn
             JOIN users d ON cn.doctor_id = d.id
             WHERE cn.patient_id = ?
             ORDER BY cn.consultation_date DESC
             LIMIT ?",
            [$patientId, $limit]
        );
    }

    /**
     * Get consultation notes for a patient visible to parents.
     */
    public function getByPatientForParent(int $patientId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT cn.*, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    d.specialization AS doctor_specialization
             FROM consultation_notes cn
             JOIN users d ON cn.doctor_id = d.id
             WHERE cn.patient_id = ? AND cn.is_visible_to_parent = 1
             ORDER BY cn.consultation_date DESC
             LIMIT ?",
            [$patientId, $limit]
        );
    }

    /**
     * Get notes by a doctor.
     */
    public function getByDoctor(int $doctorId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT cn.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name
             FROM consultation_notes cn
             JOIN patients p ON cn.patient_id = p.id
             WHERE cn.doctor_id = ?
             ORDER BY cn.consultation_date DESC
             LIMIT ? OFFSET ?",
            [$doctorId, $limit, $offset]
        );
    }
}
