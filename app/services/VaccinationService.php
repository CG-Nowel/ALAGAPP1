<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VaccinationRecord;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Core\Database;

class VaccinationService
{
    private VaccinationRecord $vaccinationModel;
    private ActivityLog $activityLog;
    private Notification $notificationModel;
    private Database $db;

    public function __construct()
    {
        $this->vaccinationModel = new VaccinationRecord();
        $this->activityLog = new ActivityLog();
        $this->notificationModel = new Notification();
        $this->db = Database::getInstance();
    }

    /**
     * Record a vaccination.
     */
    public function recordVaccination(array $data, int $administeredBy): array
    {
        $this->db->beginTransaction();
        try {
            $recordId = $this->vaccinationModel->create([
                'patient_id' => (int) $data['patient_id'],
                'vaccine_id' => isset($data['vaccine_id']) ? (int) $data['vaccine_id'] : null,
                'vaccine_name' => $data['vaccine_name'],
                'vaccine_type' => $data['vaccine_type'] ?? 'ROUTINE',
                'dose_number' => (int) ($data['dose_number'] ?? 1),
                'total_doses' => (int) ($data['total_doses'] ?? 1),
                'administration_date' => $data['administration_date'],
                'next_due_date' => $data['next_due_date'] ?? null,
                'administered_by' => $administeredBy,
                'lot_number' => $data['lot_number'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'site' => $data['site'] ?? 'LEFT_ARM',
                'notes' => $data['notes'] ?? null,
                'status' => 'COMPLETED',
            ]);

            // Update patient_vaccine_needs if applicable
            if (!empty($data['vaccine_id'])) {
                $this->db->query(
                    "UPDATE patient_vaccine_needs SET status = 'COMPLETED'
                     WHERE patient_id = ? AND vaccine_id = ? AND dose_number = ? AND status = 'PENDING'",
                    [(int) $data['patient_id'], (int) $data['vaccine_id'], (int) ($data['dose_number'] ?? 1)]
                );

                // Schedule next dose if applicable
                if (!empty($data['next_due_date'])) {
                    $nextDose = (int) ($data['dose_number'] ?? 1) + 1;
                    if ($nextDose <= (int) ($data['total_doses'] ?? 1)) {
                        $this->db->insert('patient_vaccine_needs', [
                            'patient_id' => (int) $data['patient_id'],
                            'vaccine_id' => (int) $data['vaccine_id'],
                            'vaccine_name' => $data['vaccine_name'],
                            'dose_number' => $nextDose,
                            'recommended_date' => $data['next_due_date'],
                            'status' => 'PENDING',
                        ]);
                    }
                }
            }

            $this->db->commit();

            // Send notification to parent
            $parentId = $this->db->fetchColumn(
                "SELECT parent_id FROM patients WHERE id = ?",
                [(int) $data['patient_id']]
            );

            if ($parentId) {
                $patientName = $this->db->fetchOne(
                    "SELECT first_name FROM patients WHERE id = ?",
                    [(int) $data['patient_id']]
                );
                $this->notificationModel->createNotification(
                    (int) $parentId,
                    'Vaccination Recorded',
                    "{$data['vaccine_name']} (Dose {$data['dose_number']}) has been administered to {$patientName['first_name']}.",
                    'VACCINATION',
                    'IN_APP',
                    'vaccination_record',
                    $recordId
                );
            }

            $this->activityLog->log('VACCINATION_RECORDED', $administeredBy, 'vaccination_record', $recordId,
                "Recorded {$data['vaccine_name']} for patient #{$data['patient_id']}");

            return [
                'success' => true,
                'message' => 'Vaccination recorded successfully.',
                'data' => ['record_id' => $recordId],
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get vaccination history for a patient.
     */
    public function getPatientHistory(int $patientId): array
    {
        return $this->vaccinationModel->getByPatient($patientId);
    }

    /**
     * Get pending vaccinations for a patient.
     */
    public function getPendingVaccinations(int $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT pvn.*, v.description AS vaccine_description
             FROM patient_vaccine_needs pvn
             LEFT JOIN vaccines v ON pvn.vaccine_id = v.id
             WHERE pvn.patient_id = ? AND pvn.status IN ('PENDING', 'MISSED')
             ORDER BY pvn.recommended_date",
            [$patientId]
        );
    }

    /**
     * Get available vaccines.
     */
    public function getAvailableVaccines(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM vaccines WHERE active = 1 ORDER BY name"
        );
    }

    /**
     * Generate recommended vaccine schedule for a patient.
     */
    public function generateScheduleForPatient(int $patientId): array
    {
        $patient = $this->db->fetchOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
        if (!$patient) {
            return ['success' => false, 'message' => 'Patient not found.'];
        }

        $birthDate = new \DateTime($patient['date_of_birth']);
        $now = new \DateTime();
        $ageMonths = ($now->format('Y') - $birthDate->format('Y')) * 12 + ($now->format('m') - $birthDate->format('m'));

        $schedule = $this->db->fetchAll(
            "SELECT vs.*, v.name AS vaccine_name, v.vaccine_type
             FROM vaccine_schedule vs
             JOIN vaccines v ON vs.vaccine_id = v.id
             WHERE v.active = 1
             ORDER BY vs.recommended_age_months, v.name"
        );

        $existing = $this->db->fetchAll(
            "SELECT vaccine_id, dose_number FROM vaccination_records WHERE patient_id = ? AND status = 'COMPLETED'",
            [$patientId]
        );
        $completedMap = [];
        foreach ($existing as $record) {
            $completedMap[$record['vaccine_id'] . '-' . $record['dose_number']] = true;
        }

        $needed = [];
        foreach ($schedule as $item) {
            $key = $item['vaccine_id'] . '-' . $item['dose_number'];
            if (isset($completedMap[$key])) {
                continue;
            }

            $recommendedDate = (clone $birthDate)->modify("+{$item['recommended_age_months']} months");
            $status = $recommendedDate < $now ? 'MISSED' : 'PENDING';

            $needed[] = [
                'vaccine_id' => $item['vaccine_id'],
                'vaccine_name' => $item['vaccine_name'],
                'dose_number' => $item['dose_number'],
                'recommended_date' => $recommendedDate->format('Y-m-d'),
                'status' => $status,
                'is_mandatory' => (bool) $item['is_mandatory'],
            ];
        }

        return ['success' => true, 'data' => $needed];
    }
}
