<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Prescription extends Model
{
    protected string $table = 'prescriptions';

    /**
     * Generate a unique prescription number using a transaction-safe sequence.
     */
    public function generatePrescriptionNumber(): string
    {
        $year = (int) date('Y');

        $this->db->beginTransaction();
        try {
            // Upsert the sequence row and atomically increment
            $this->db->query(
                "INSERT INTO prescription_sequences (year, last_number) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE last_number = last_number + 1",
                [$year]
            );

            $number = (int) $this->db->fetchColumn(
                "SELECT last_number FROM prescription_sequences WHERE year = ?",
                [$year]
            );

            $this->db->commit();

            return sprintf('RX-%d-%06d', $year, $number);
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get prescriptions for a patient.
     */
    public function getByPatient(int $patientId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT pr.*, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    d.specialization AS doctor_specialization, d.license_number
             FROM prescriptions pr
             JOIN users d ON pr.doctor_id = d.id
             WHERE pr.patient_id = ?
             ORDER BY pr.prescription_date DESC
             LIMIT ?",
            [$patientId, $limit]
        );
    }

    /**
     * Get prescriptions by a doctor.
     */
    public function getByDoctor(int $doctorId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT pr.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    p.date_of_birth AS patient_dob
             FROM prescriptions pr
             JOIN patients p ON pr.patient_id = p.id
             WHERE pr.doctor_id = ?
             ORDER BY pr.prescription_date DESC
             LIMIT ? OFFSET ?",
            [$doctorId, $limit, $offset]
        );
    }

    /**
     * Get prescription with full details for printing.
     */
    public function getForPrint(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT pr.*,
                    p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    p.date_of_birth AS patient_dob, p.gender AS patient_gender,
                    p.weight AS patient_weight, p.allergies AS patient_allergies,
                    d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    d.specialization, d.license_number,
                    par.first_name AS parent_first_name, par.last_name AS parent_last_name,
                    par.phone AS parent_phone
             FROM prescriptions pr
             JOIN patients p ON pr.patient_id = p.id
             JOIN users d ON pr.doctor_id = d.id
             JOIN users par ON p.parent_id = par.id
             WHERE pr.id = ?",
            [$id]
        );
    }
}
