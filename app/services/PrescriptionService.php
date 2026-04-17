<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Prescription;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Core\Database;

class PrescriptionService
{
    private Prescription $prescriptionModel;
    private ActivityLog $activityLog;
    private Notification $notificationModel;
    private Database $db;

    public function __construct()
    {
        $this->prescriptionModel = new Prescription();
        $this->activityLog = new ActivityLog();
        $this->notificationModel = new Notification();
        $this->db = Database::getInstance();
    }

    /**
     * Create a new prescription.
     */
    public function create(array $data, int $doctorId): array
    {
        $prescriptionNumber = $this->prescriptionModel->generatePrescriptionNumber();

        $medications = is_string($data['medications']) ? $data['medications'] : json_encode($data['medications']);

        $prescriptionId = $this->prescriptionModel->create([
            'prescription_number' => $prescriptionNumber,
            'patient_id' => (int) $data['patient_id'],
            'doctor_id' => $doctorId,
            'appointment_id' => isset($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            'prescription_date' => $data['prescription_date'] ?? date('Y-m-d'),
            'diagnosis' => $data['diagnosis'] ?? null,
            'medications' => $medications,
            'notes' => $data['notes'] ?? null,
        ]);

        // Notify parent
        $parentId = $this->db->fetchColumn("SELECT parent_id FROM patients WHERE id = ?", [(int) $data['patient_id']]);
        if ($parentId) {
            $patient = $this->db->fetchOne("SELECT first_name FROM patients WHERE id = ?", [(int) $data['patient_id']]);
            $this->notificationModel->createNotification(
                (int) $parentId,
                'New Prescription',
                "A new prescription ({$prescriptionNumber}) has been created for {$patient['first_name']}.",
                'SYSTEM',
                'IN_APP',
                'prescription',
                $prescriptionId
            );
        }

        $this->activityLog->log('PRESCRIPTION_CREATED', $doctorId, 'prescription', $prescriptionId,
            "Prescription {$prescriptionNumber} created for patient #{$data['patient_id']}");

        return [
            'success' => true,
            'message' => 'Prescription created successfully.',
            'data' => ['prescription_id' => $prescriptionId, 'prescription_number' => $prescriptionNumber],
        ];
    }

    /**
     * Update a prescription.
     */
    public function update(int $prescriptionId, array $data, int $userId): array
    {
        $prescription = $this->prescriptionModel->find($prescriptionId);
        if (!$prescription) {
            return ['success' => false, 'message' => 'Prescription not found.'];
        }

        $updateData = [];
        if (isset($data['medications'])) {
            $updateData['medications'] = is_string($data['medications']) ? $data['medications'] : json_encode($data['medications']);
        }
        if (isset($data['diagnosis'])) {
            $updateData['diagnosis'] = $data['diagnosis'];
        }
        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $this->prescriptionModel->updateById($prescriptionId, $updateData);

        $this->activityLog->log('PRESCRIPTION_UPDATED', $userId, 'prescription', $prescriptionId, 'Prescription updated');

        return ['success' => true, 'message' => 'Prescription updated successfully.'];
    }

    /**
     * Delete a prescription.
     */
    public function delete(int $prescriptionId, int $userId): array
    {
        $prescription = $this->prescriptionModel->find($prescriptionId);
        if (!$prescription) {
            return ['success' => false, 'message' => 'Prescription not found.'];
        }

        $this->prescriptionModel->deleteById($prescriptionId);
        $this->activityLog->log('PRESCRIPTION_DELETED', $userId, 'prescription', $prescriptionId,
            "Prescription {$prescription['prescription_number']} deleted");

        return ['success' => true, 'message' => 'Prescription deleted successfully.'];
    }

    /**
     * Get prescriptions for a patient.
     */
    public function getByPatient(int $patientId): array
    {
        return $this->prescriptionModel->getByPatient($patientId);
    }

    /**
     * Get prescription for printing.
     */
    public function getForPrint(int $prescriptionId): ?array
    {
        return $this->prescriptionModel->getForPrint($prescriptionId);
    }
}
