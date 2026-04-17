<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PatientFile extends Model
{
    protected string $table = 'patient_files';

    /**
     * Get files for a patient.
     */
    public function getByPatient(int $patientId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT pf.*, u.first_name AS uploader_first_name, u.last_name AS uploader_last_name
             FROM patient_files pf
             JOIN users u ON pf.uploaded_by = u.id
             WHERE pf.patient_id = ?
             ORDER BY pf.created_at DESC
             LIMIT ?",
            [$patientId, $limit]
        );
    }

    /**
     * Check if a user has access to a file.
     */
    public function userHasAccess(int $fileId, int $userId, string $userType): bool
    {
        if ($userType === 'ADMIN') {
            return true;
        }

        $file = $this->find($fileId);
        if (!$file) {
            return false;
        }

        if ($userType === 'PARENT') {
            // Check if the patient belongs to the parent
            return $this->db->fetchColumn(
                "SELECT COUNT(*) FROM patients WHERE id = ? AND parent_id = ?",
                [$file['patient_id'], $userId]
            ) > 0;
        }

        if (in_array($userType, ['DOCTOR', 'DOCTOR_OWNER'])) {
            // Check if doctor has an appointment with this patient
            return $this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND doctor_id = ?",
                [$file['patient_id'], $userId]
            ) > 0;
        }

        return false;
    }
}
