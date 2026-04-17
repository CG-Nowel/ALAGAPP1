<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConsultationNote;
use App\Models\PatientFile;
use App\Models\Patient;
use App\Models\ActivityLog;
use App\Helpers\FileUpload;
use App\Core\Database;

class MedicalRecordService
{
    private ConsultationNote $consultationModel;
    private PatientFile $fileModel;
    private Patient $patientModel;
    private ActivityLog $activityLog;
    private Database $db;

    public function __construct()
    {
        $this->consultationModel = new ConsultationNote();
        $this->fileModel = new PatientFile();
        $this->patientModel = new Patient();
        $this->activityLog = new ActivityLog();
        $this->db = Database::getInstance();
    }

    // ─── Consultation Notes ────────────────────────────────────────────

    /**
     * Create a consultation note.
     */
    public function createConsultationNote(array $data, int $doctorId): array
    {
        $noteId = $this->consultationModel->create([
            'patient_id' => (int) $data['patient_id'],
            'doctor_id' => $doctorId,
            'appointment_id' => isset($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            'consultation_date' => $data['consultation_date'] ?? date('Y-m-d'),
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'symptoms' => $data['symptoms'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'treatment_plan' => $data['treatment_plan'] ?? null,
            'notes' => $data['notes'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'blood_pressure' => $data['blood_pressure'] ?? null,
            'heart_rate' => isset($data['heart_rate']) ? (int) $data['heart_rate'] : null,
            'respiratory_rate' => isset($data['respiratory_rate']) ? (int) $data['respiratory_rate'] : null,
            'height' => $data['height'] ?? null,
            'weight' => $data['weight'] ?? null,
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'is_visible_to_parent' => isset($data['is_visible_to_parent']) ? (int) $data['is_visible_to_parent'] : 1,
        ]);

        $this->activityLog->log('CONSULTATION_NOTE_CREATED', $doctorId, 'consultation_note', $noteId,
            "Consultation note created for patient #{$data['patient_id']}");

        return [
            'success' => true,
            'message' => 'Consultation note saved successfully.',
            'data' => ['note_id' => $noteId],
        ];
    }

    /**
     * Update a consultation note.
     */
    public function updateConsultationNote(int $noteId, array $data, int $userId): array
    {
        $note = $this->consultationModel->find($noteId);
        if (!$note) {
            return ['success' => false, 'message' => 'Consultation note not found.'];
        }

        $updateData = array_filter([
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'symptoms' => $data['symptoms'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'treatment_plan' => $data['treatment_plan'] ?? null,
            'notes' => $data['notes'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'blood_pressure' => $data['blood_pressure'] ?? null,
            'heart_rate' => isset($data['heart_rate']) ? (int) $data['heart_rate'] : null,
            'respiratory_rate' => isset($data['respiratory_rate']) ? (int) $data['respiratory_rate'] : null,
            'height' => $data['height'] ?? null,
            'weight' => $data['weight'] ?? null,
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'is_visible_to_parent' => isset($data['is_visible_to_parent']) ? (int) $data['is_visible_to_parent'] : null,
        ], fn($v) => $v !== null);

        if (empty($updateData)) {
            return ['success' => false, 'message' => 'No data to update.'];
        }

        $this->consultationModel->updateById($noteId, $updateData);
        $this->activityLog->log('CONSULTATION_NOTE_UPDATED', $userId, 'consultation_note', $noteId, 'Note updated');

        return ['success' => true, 'message' => 'Consultation note updated successfully.'];
    }

    /**
     * Delete a consultation note.
     */
    public function deleteConsultationNote(int $noteId, int $userId): array
    {
        $note = $this->consultationModel->find($noteId);
        if (!$note) {
            return ['success' => false, 'message' => 'Consultation note not found.'];
        }

        $this->consultationModel->deleteById($noteId);
        $this->activityLog->log('CONSULTATION_NOTE_DELETED', $userId, 'consultation_note', $noteId, 'Note deleted');

        return ['success' => true, 'message' => 'Consultation note deleted successfully.'];
    }

    /**
     * Get consultation notes for a patient.
     */
    public function getConsultationNotes(int $patientId, bool $parentView = false): array
    {
        if ($parentView) {
            return $this->consultationModel->getByPatientForParent($patientId);
        }
        return $this->consultationModel->getByPatient($patientId);
    }

    // ─── Patient Files ─────────────────────────────────────────────────

    /**
     * Upload a patient file.
     */
    public function uploadFile(int $patientId, array $file, int $uploadedBy, ?string $category = null, ?string $description = null): array
    {
        $uploader = new FileUpload();

        try {
            $fileInfo = $uploader->upload($file, (string) $patientId);

            $fileId = $this->fileModel->create([
                'patient_id' => $patientId,
                'uploaded_by' => $uploadedBy,
                'original_filename' => $fileInfo['original_filename'],
                'stored_filename' => $fileInfo['stored_filename'],
                'mime_type' => $fileInfo['mime_type'],
                'file_size' => $fileInfo['file_size'],
                'file_category' => $category ?? 'OTHER',
                'description' => $description,
            ]);

            $this->activityLog->log('FILE_UPLOADED', $uploadedBy, 'patient_file', $fileId,
                "File uploaded for patient #{$patientId}: {$fileInfo['original_filename']}");

            return [
                'success' => true,
                'message' => 'File uploaded successfully.',
                'data' => ['file_id' => $fileId, 'filename' => $fileInfo['original_filename']],
            ];
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Download a patient file.
     */
    public function downloadFile(int $fileId, int $userId, string $userType): array
    {
        $file = $this->fileModel->find($fileId);
        if (!$file) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        if (!$this->fileModel->userHasAccess($fileId, $userId, $userType)) {
            return ['success' => false, 'message' => 'Access denied.'];
        }

        $uploader = new FileUpload();
        $filePath = $uploader->getPath($file['stored_filename']);

        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'File not found on disk.'];
        }

        return [
            'success' => true,
            'data' => [
                'path' => $filePath,
                'filename' => $file['original_filename'],
                'mime_type' => $file['mime_type'],
                'size' => $file['file_size'],
            ],
        ];
    }

    /**
     * Get all files for a patient.
     */
    public function getPatientFiles(int $patientId): array
    {
        return $this->fileModel->getByPatient($patientId);
    }

    /**
     * Delete a patient file.
     */
    public function deleteFile(int $fileId, int $userId): array
    {
        $file = $this->fileModel->find($fileId);
        if (!$file) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        $uploader = new FileUpload();
        $uploader->delete($file['stored_filename']);

        $this->fileModel->deleteById($fileId);
        $this->activityLog->log('FILE_DELETED', $userId, 'patient_file', $fileId,
            "File deleted: {$file['original_filename']}");

        return ['success' => true, 'message' => 'File deleted successfully.'];
    }

    // ─── Patient Details (Canonical Source) ─────────────────────────────

    /**
     * Get complete patient details (single canonical implementation).
     */
    public function getPatientDetails(int $patientId): ?array
    {
        return $this->patientModel->getDetails($patientId);
    }

    /**
     * Get patient prescriptions (single canonical implementation).
     */
    public function getPatientPrescriptions(int $patientId): array
    {
        $prescriptionService = new PrescriptionService();
        return $prescriptionService->getByPatient($patientId);
    }

    /**
     * Get patient consultation notes (single canonical implementation).
     */
    public function getPatientConsultationNotes(int $patientId, bool $parentView = false): array
    {
        return $this->getConsultationNotes($patientId, $parentView);
    }

    /**
     * Get patient vaccination history (single canonical implementation).
     */
    public function getPatientVaccinationHistory(int $patientId): array
    {
        $vaccinationService = new VaccinationService();
        return $vaccinationService->getPatientHistory($patientId);
    }

    /**
     * Export patient data as array (for CSV/PDF export).
     */
    public function exportPatientData(int $patientId): array
    {
        return [
            'patient' => $this->getPatientDetails($patientId),
            'appointments' => $this->db->fetchAll(
                "SELECT a.*, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name
                 FROM appointments a JOIN users d ON a.doctor_id = d.id
                 WHERE a.patient_id = ? ORDER BY a.appointment_date DESC",
                [$patientId]
            ),
            'prescriptions' => $this->getPatientPrescriptions($patientId),
            'consultation_notes' => $this->getPatientConsultationNotes($patientId),
            'vaccination_history' => $this->getPatientVaccinationHistory($patientId),
            'files' => $this->getPatientFiles($patientId),
        ];
    }
}
