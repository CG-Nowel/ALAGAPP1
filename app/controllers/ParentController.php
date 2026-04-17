<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\UserService;
use App\Services\AppointmentService;
use App\Services\MedicalRecordService;
use App\Services\VaccinationService;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Notification;
use App\Helpers\Response;

class ParentController extends Controller
{
    private UserService $userService;
    private AppointmentService $appointmentService;
    private MedicalRecordService $medicalRecordService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();
        $this->appointmentService = new AppointmentService();
        $this->medicalRecordService = new MedicalRecordService();
    }

    /**
     * Show parent dashboard.
     */
    public function dashboard(): void
    {
        $user = $this->currentUser();
        $children = $this->userService->getChildren($this->userId());
        $appointmentModel = new Appointment();
        $upcomingAppointments = $appointmentModel->getUpcomingForParent($this->userId(), 5);

        $notificationModel = new Notification();
        $notifications = $notificationModel->getUnread($this->userId(), 10);
        $unreadCount = $notificationModel->getUnreadCount($this->userId());

        $this->view('parent/dashboard', [
            'user' => $user,
            'children' => $children,
            'upcomingAppointments' => $upcomingAppointments,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * List children.
     */
    public function children(): void
    {
        $children = $this->userService->getChildren($this->userId());

        if ($this->isAjax()) {
            Response::success($children);
            return;
        }

        $this->view('parent/children', [
            'user' => $this->currentUser(),
            'children' => $children,
        ]);
    }

    /**
     * Add a child.
     */
    public function addChild(): void
    {
        $validation = $this->validate([
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
        ]);

        if (!empty($validation['errors'])) {
            Response::validationError($validation['errors']);
            return;
        }

        $data = array_merge($validation['data'], [
            'blood_type' => $this->input('blood_type'),
            'height' => $this->input('height'),
            'weight' => $this->input('weight'),
            'allergies' => $this->input('allergies'),
            'medical_conditions' => $this->input('medical_conditions'),
            'special_notes' => $this->input('special_notes'),
        ]);

        $result = $this->userService->addChild($data, $this->userId());
        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Update a child.
     */
    public function updateChild(string $id): void
    {
        $result = $this->userService->updateChild((int) $id, $_POST, $this->userId());
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Book an appointment.
     */
    public function bookAppointment(): void
    {
        $validation = $this->validate([
            'patient_id' => 'required|numeric',
            'doctor_id' => 'required|numeric',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
        ]);

        if (!empty($validation['errors'])) {
            Response::validationError($validation['errors']);
            return;
        }

        // Verify the child belongs to this parent
        $patientModel = new Patient();
        if (!$patientModel->belongsToParent((int) $validation['data']['patient_id'], $this->userId())) {
            Response::error('Access denied.', 403);
            return;
        }

        $data = array_merge($validation['data'], [
            'type' => $this->input('type', 'CONSULTATION'),
            'reason' => $this->input('reason'),
            'duration' => (int) $this->input('duration', '30'),
        ]);

        $result = $this->appointmentService->bookAppointment($data, $this->userId());
        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Cancel an appointment.
     */
    public function cancelAppointment(string $id): void
    {
        $reason = $this->input('reason', 'Cancelled by parent');
        $result = $this->appointmentService->updateStatus((int) $id, 'CANCELLED', $this->userId(), $reason);
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Get available time slots.
     */
    public function getAvailableSlots(): void
    {
        $doctorId = (int) ($_GET['doctor_id'] ?? 0);
        $date = $_GET['date'] ?? '';

        if (!$doctorId || !$date) {
            Response::error('Doctor ID and date are required.');
            return;
        }

        $slots = $this->appointmentService->getAvailableSlots($doctorId, $date);
        Response::success($slots);
    }

    /**
     * Get available doctors.
     */
    public function getDoctors(): void
    {
        $userModel = new User();
        $doctors = $userModel->getDoctors();
        Response::success($doctors);
    }

    /**
     * Get patient medical records.
     */
    public function patientRecords(string $patientId): void
    {
        $pid = (int) $patientId;
        $patientModel = new Patient();

        if (!$patientModel->belongsToParent($pid, $this->userId())) {
            Response::error('Access denied.', 403);
            return;
        }

        $data = [
            'patient' => $this->medicalRecordService->getPatientDetails($pid),
            'consultation_notes' => $this->medicalRecordService->getPatientConsultationNotes($pid, true),
            'prescriptions' => $this->medicalRecordService->getPatientPrescriptions($pid),
            'vaccination_history' => $this->medicalRecordService->getPatientVaccinationHistory($pid),
            'files' => $this->medicalRecordService->getPatientFiles($pid),
        ];

        Response::success($data);
    }

    /**
     * Get vaccination schedule for a child.
     */
    public function vaccinationSchedule(string $patientId): void
    {
        $pid = (int) $patientId;
        $patientModel = new Patient();

        if (!$patientModel->belongsToParent($pid, $this->userId())) {
            Response::error('Access denied.', 403);
            return;
        }

        $vaccinationService = new VaccinationService();
        $history = $vaccinationService->getPatientHistory($pid);
        $pending = $vaccinationService->getPendingVaccinations($pid);

        Response::success(['history' => $history, 'pending' => $pending]);
    }

    /**
     * Upload a file for a patient.
     */
    public function uploadFile(): void
    {
        $patientId = (int) $this->input('patient_id');
        $patientModel = new Patient();

        if (!$patientModel->belongsToParent($patientId, $this->userId())) {
            Response::error('Access denied.', 403);
            return;
        }

        if (empty($_FILES['file'])) {
            Response::error('No file uploaded.');
            return;
        }

        $category = $this->input('file_category', 'OTHER');
        $description = $this->input('description');

        $result = $this->medicalRecordService->uploadFile($patientId, $_FILES['file'], $this->userId(), $category, $description);
        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Download a file.
     */
    public function downloadFile(string $fileId): void
    {
        $result = $this->medicalRecordService->downloadFile((int) $fileId, $this->userId(), 'PARENT');

        if (!$result['success']) {
            Response::error($result['message'], 403);
            return;
        }

        $file = $result['data'];
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
        header('Content-Length: ' . $file['size']);
        readfile($file['path']);
        exit;
    }

    /**
     * Add to waitlist.
     */
    public function addToWaitlist(): void
    {
        $validation = $this->validate([
            'patient_id' => 'required|numeric',
            'doctor_id' => 'required|numeric',
            'preferred_date' => 'required|date',
        ]);

        if (!empty($validation['errors'])) {
            Response::validationError($validation['errors']);
            return;
        }

        $data = array_merge($validation['data'], [
            'preferred_time_start' => $this->input('preferred_time_start'),
            'preferred_time_end' => $this->input('preferred_time_end'),
            'type' => $this->input('type', 'CONSULTATION'),
            'reason' => $this->input('reason'),
        ]);

        $result = $this->appointmentService->addToWaitlist($data, $this->userId());
        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * View prescription (printable).
     */
    public function viewPrescription(string $id): void
    {
        $prescriptionService = new \App\Services\PrescriptionService();
        $prescription = $prescriptionService->getForPrint((int) $id);

        if (!$prescription) {
            Response::error('Prescription not found.', 404);
            return;
        }

        // Verify parent owns this patient
        $patientModel = new Patient();
        if (!$patientModel->belongsToParent((int) $prescription['patient_id'], $this->userId())) {
            Response::error('Access denied.', 403);
            return;
        }

        $this->view('parent/prescription-print', ['prescription' => $prescription]);
    }

    /**
     * Get parent's appointments.
     */
    public function myAppointments(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $filters = [
            'parent_id' => $this->userId(),
            'status' => $_GET['status'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
        ];

        $result = $this->appointmentService->getFiltered($filters, $page);
        Response::paginated($result['data'], $result['pagination']);
    }
}
