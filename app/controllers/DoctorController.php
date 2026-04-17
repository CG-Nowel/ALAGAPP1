<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AppointmentService;
use App\Services\MedicalRecordService;
use App\Services\PrescriptionService;
use App\Services\VaccinationService;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Notification;
use App\Helpers\Response;

class DoctorController extends Controller
{
    private AppointmentService $appointmentService;
    private MedicalRecordService $medicalRecordService;

    public function __construct()
    {
        parent::__construct();
        $this->appointmentService = new AppointmentService();
        $this->medicalRecordService = new MedicalRecordService();
    }

    /**
     * Show doctor dashboard.
     */
    public function dashboard(): void
    {
        $user = $this->currentUser();
        $appointmentModel = new Appointment();
        $notificationModel = new Notification();

        $todayAppointments = $appointmentModel->getByDoctorDate($this->userId(), date('Y-m-d'));
        $stats = $appointmentModel->getStats($this->userId());
        $notifications = $notificationModel->getUnread($this->userId(), 10);
        $unreadCount = $notificationModel->getUnreadCount($this->userId());

        $this->view('doctor/dashboard', [
            'user' => $user,
            'todayAppointments' => $todayAppointments,
            'stats' => $stats,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Get appointments (AJAX).
     */
    public function appointments(): void
    {
        $filters = [
            'doctor_id' => $this->userId(),
            'date_from' => $_GET['date_from'] ?? date('Y-m-d'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d', strtotime('+7 days')),
            'status' => $_GET['status'] ?? null,
        ];
        $page = (int) ($_GET['page'] ?? 1);

        $result = $this->appointmentService->getFiltered($filters, $page);
        Response::paginated($result['data'], $result['pagination']);
    }

    /**
     * Get appointment details (AJAX).
     */
    public function appointmentDetails(string $id): void
    {
        $appointment = $this->appointmentService->getDetails((int) $id);
        if (!$appointment || (int) $appointment['doctor_id'] !== $this->userId()) {
            Response::error('Appointment not found.', 404);
            return;
        }
        Response::success($appointment);
    }

    /**
     * Update appointment status.
     */
    public function updateAppointmentStatus(): void
    {
        $appointmentId = (int) $this->input('appointment_id');
        $status = $this->input('status');
        $reason = $this->input('reason');

        $result = $this->appointmentService->updateStatus($appointmentId, $status, $this->userId(), $reason);
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Get patient medical records.
     */
    public function patientRecords(string $patientId): void
    {
        $pid = (int) $patientId;

        $data = [
            'patient' => $this->medicalRecordService->getPatientDetails($pid),
            'consultation_notes' => $this->medicalRecordService->getPatientConsultationNotes($pid),
            'prescriptions' => $this->medicalRecordService->getPatientPrescriptions($pid),
            'vaccination_history' => $this->medicalRecordService->getPatientVaccinationHistory($pid),
            'files' => $this->medicalRecordService->getPatientFiles($pid),
        ];

        Response::success($data);
    }

    /**
     * Create consultation note.
     */
    public function createConsultationNote(): void
    {
        $validation = $this->validate([
            'patient_id' => 'required|numeric',
            'diagnosis' => 'required',
        ]);

        if (!empty($validation['errors'])) {
            Response::validationError($validation['errors']);
            return;
        }

        $data = array_merge($validation['data'], [
            'chief_complaint' => $this->input('chief_complaint'),
            'symptoms' => $this->input('symptoms'),
            'treatment_plan' => $this->input('treatment_plan'),
            'notes' => $this->input('notes'),
            'temperature' => $this->input('temperature'),
            'blood_pressure' => $this->input('blood_pressure'),
            'heart_rate' => $this->input('heart_rate'),
            'respiratory_rate' => $this->input('respiratory_rate'),
            'height' => $this->input('height'),
            'weight' => $this->input('weight'),
            'follow_up_date' => $this->input('follow_up_date'),
            'appointment_id' => $this->input('appointment_id'),
            'is_visible_to_parent' => $this->input('is_visible_to_parent', '1'),
        ]);

        $result = $this->medicalRecordService->createConsultationNote($data, $this->userId());
        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Update consultation note.
     */
    public function updateConsultationNote(string $id): void
    {
        $result = $this->medicalRecordService->updateConsultationNote((int) $id, $_POST, $this->userId());
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Delete consultation note.
     */
    public function deleteConsultationNote(string $id): void
    {
        $result = $this->medicalRecordService->deleteConsultationNote((int) $id, $this->userId());
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Create prescription.
     */
    public function createPrescription(): void
    {
        $validation = $this->validate([
            'patient_id' => 'required|numeric',
            'medications' => 'required',
        ]);

        if (!empty($validation['errors'])) {
            Response::validationError($validation['errors']);
            return;
        }

        $data = $validation['data'];
        $data['diagnosis'] = $this->input('diagnosis');
        $data['notes'] = $this->input('notes');
        $data['appointment_id'] = $this->input('appointment_id');

        $prescriptionService = new PrescriptionService();
        $result = $prescriptionService->create($data, $this->userId());
        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Update prescription.
     */
    public function updatePrescription(string $id): void
    {
        $prescriptionService = new PrescriptionService();
        $result = $prescriptionService->update((int) $id, $_POST, $this->userId());
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Delete prescription.
     */
    public function deletePrescription(string $id): void
    {
        $prescriptionService = new PrescriptionService();
        $result = $prescriptionService->delete((int) $id, $this->userId());
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Record vaccination.
     */
    public function recordVaccination(): void
    {
        $validation = $this->validate([
            'patient_id' => 'required|numeric',
            'vaccine_name' => 'required',
            'administration_date' => 'required|date',
        ]);

        if (!empty($validation['errors'])) {
            Response::validationError($validation['errors']);
            return;
        }

        $data = array_merge($validation['data'], [
            'vaccine_id' => $this->input('vaccine_id'),
            'vaccine_type' => $this->input('vaccine_type', 'ROUTINE'),
            'dose_number' => $this->input('dose_number', '1'),
            'total_doses' => $this->input('total_doses', '1'),
            'next_due_date' => $this->input('next_due_date'),
            'lot_number' => $this->input('lot_number'),
            'manufacturer' => $this->input('manufacturer'),
            'site' => $this->input('site', 'LEFT_ARM'),
            'notes' => $this->input('notes'),
        ]);

        $vaccinationService = new VaccinationService();
        $result = $vaccinationService->recordVaccination($data, $this->userId());
        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Manage availability.
     */
    public function manageAvailability(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $availability = $this->db->fetchAll(
                "SELECT * FROM doctor_availability WHERE doctor_id = ? ORDER BY day_of_week, start_time",
                [$this->userId()]
            );
            Response::success($availability);
            return;
        }

        // POST: Set availability
        $type = $this->input('availability_type', 'UNAVAILABLE');

        $data = [
            'doctor_id' => $this->userId(),
            'availability_type' => $type,
            'start_time' => $this->input('start_time', '00:00:00'),
            'end_time' => $this->input('end_time', '23:59:59'),
            'reason' => $this->input('reason'),
        ];

        if ($type === 'RECURRING') {
            $data['day_of_week'] = $this->input('day_of_week');
            $data['slot_duration'] = (int) $this->input('slot_duration', '30');
            $data['max_patients'] = (int) $this->input('max_patients', '10');
        } else {
            $data['specific_date'] = $this->input('specific_date');
            $data['is_all_day'] = (int) $this->input('is_all_day', '0');
        }

        $id = $this->db->insert('doctor_availability', $data);
        $this->logActivity('AVAILABILITY_SET', 'doctor_availability', $id, "Availability set: $type");

        Response::success(['id' => $id], 'Availability updated.');
    }

    /**
     * Delete availability entry.
     */
    public function deleteAvailability(string $id): void
    {
        $this->db->delete('doctor_availability', 'id = ? AND doctor_id = ?', [(int) $id, $this->userId()]);
        Response::success(null, 'Availability entry removed.');
    }

    /**
     * Get patients for this doctor.
     */
    public function patients(): void
    {
        $patientModel = new Patient();
        $patients = $patientModel->getByDoctor($this->userId());
        Response::success($patients);
    }

    /**
     * Print prescription.
     */
    public function printPrescription(string $id): void
    {
        $prescriptionService = new PrescriptionService();
        $prescription = $prescriptionService->getForPrint((int) $id);

        if (!$prescription || (int) $prescription['doctor_id'] !== $this->userId()) {
            Response::error('Prescription not found.', 404);
            return;
        }

        $this->view('doctor/prescription-print', ['prescription' => $prescription]);
    }

    /**
     * Export patient data.
     */
    public function exportPatientData(string $patientId): void
    {
        $data = $this->medicalRecordService->exportPatientData((int) $patientId);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="patient_' . $patientId . '_export_' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
