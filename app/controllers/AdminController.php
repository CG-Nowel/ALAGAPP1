<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\UserService;
use App\Services\AuthService;
use App\Services\AppointmentService;
use App\Models\ActivityLog;
use App\Models\ClinicSetting;
use App\Models\Notification;
use App\Helpers\Response;

class AdminController extends Controller
{
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();
    }

    /**
     * Show admin dashboard.
     */
    public function dashboard(): void
    {
        $stats = $this->userService->getAdminStats();
        $user = $this->currentUser();
        $notificationModel = new Notification();
        $notifications = $notificationModel->getUnread($this->userId(), 10);
        $unreadCount = $notificationModel->getUnreadCount($this->userId());

        $activityLog = new ActivityLog();
        $recentActivity = $activityLog->getRecent(10);

        $this->view('admin/dashboard', [
            'user' => $user,
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * List users.
     */
    public function users(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $filters = [
            'search' => $_GET['search'] ?? '',
            'user_type' => $_GET['user_type'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];

        $result = $this->userService->getUsers($filters, $page);

        if ($this->isAjax()) {
            Response::success($result);
            return;
        }

        $this->view('admin/users', [
            'user' => $this->currentUser(),
            'users' => $result['data'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    /**
     * Create a doctor account.
     */
    public function createDoctor(): void
    {
        $validation = $this->validate([
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email',
            'specialization' => 'required',
            'license_number' => 'required',
        ]);

        if (!empty($validation['errors'])) {
            Response::validationError($validation['errors']);
            return;
        }

        $data = $validation['data'];
        $data['phone'] = $this->input('phone');
        $data['years_of_experience'] = $this->input('years_of_experience');
        $data['user_type'] = $this->input('user_type', 'DOCTOR');

        $authService = new AuthService();
        $result = $authService->createDoctorAccount($data);

        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Toggle user status.
     */
    public function toggleUserStatus(): void
    {
        $userId = (int) $this->input('user_id');
        $status = $this->input('status');

        $result = $this->userService->toggleStatus($userId, $status, $this->userId());
        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Bulk user actions.
     */
    public function bulkAction(): void
    {
        $action = $this->input('action');
        $userIds = $_POST['user_ids'] ?? [];

        if (empty($userIds)) {
            Response::error('No users selected.');
            return;
        }

        $result = match ($action) {
            'activate' => $this->userService->bulkUpdateStatus($userIds, 'active', $this->userId()),
            'deactivate' => $this->userService->bulkUpdateStatus($userIds, 'inactive', $this->userId()),
            'suspend' => $this->userService->bulkUpdateStatus($userIds, 'suspended', $this->userId()),
            default => ['success' => false, 'message' => 'Invalid action.'],
        };

        $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Admin create appointment.
     */
    public function createAppointment(): void
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

        $data = $validation['data'];
        $data['type'] = $this->input('type', 'CONSULTATION');
        $data['reason'] = $this->input('reason');
        $data['duration'] = (int) $this->input('duration', '30');

        $appointmentService = new AppointmentService();
        $result = $appointmentService->bookAppointment($data, $this->userId());

        $result['success'] ? Response::success($result['data'] ?? null, $result['message']) : Response::error($result['message']);
    }

    /**
     * Activity logs.
     */
    public function activityLogs(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 25;
        $search = $_GET['search'] ?? '';
        $action = $_GET['action'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        $activityLog = new ActivityLog();
        $offset = ($page - 1) * $perPage;

        $logs = $activityLog->search($search, $action ?: null, $dateFrom ?: null, $dateTo ?: null, $perPage, $offset);
        $total = $activityLog->count();

        if ($this->isAjax()) {
            Response::paginated($logs, [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]);
            return;
        }

        $this->view('admin/activity-logs', [
            'user' => $this->currentUser(),
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            'filters' => ['search' => $search, 'action' => $action, 'date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }

    /**
     * Clinic settings.
     */
    public function settings(): void
    {
        $settingsModel = new ClinicSetting();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = $_POST['settings'] ?? [];
            foreach ($settings as $key => $value) {
                $settingsModel->set($key, $value, 'STRING', $this->userId());
            }

            $this->logActivity('SETTINGS_UPDATED', 'clinic_settings', null, 'Clinic settings updated');
            Response::success(null, 'Settings updated successfully.');
            return;
        }

        $settings = $settingsModel->getAllSettings();

        if ($this->isAjax()) {
            Response::success($settings);
            return;
        }

        $this->view('admin/settings', [
            'user' => $this->currentUser(),
            'settings' => $settings,
        ]);
    }

    /**
     * Analytics data for charts.
     */
    public function analytics(): void
    {
        // Monthly appointment trends
        $appointmentTrends = $this->db->fetchAll(
            "SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS month,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
             FROM appointments
             WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
             ORDER BY month"
        );

        // Appointments by type
        $appointmentsByType = $this->db->fetchAll(
            "SELECT type, COUNT(*) AS total FROM appointments
             WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY type ORDER BY total DESC"
        );

        // Top doctors by appointments
        $topDoctors = $this->db->fetchAll(
            "SELECT u.first_name, u.last_name, u.specialization, COUNT(a.id) AS total_appointments
             FROM users u
             JOIN appointments a ON u.id = a.doctor_id
             WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
             GROUP BY u.id ORDER BY total_appointments DESC LIMIT 5"
        );

        // Vaccination trends
        $vaccinationTrends = $this->db->fetchAll(
            "SELECT DATE_FORMAT(administration_date, '%Y-%m') AS month, COUNT(*) AS total
             FROM vaccination_records WHERE status = 'COMPLETED'
             AND administration_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(administration_date, '%Y-%m') ORDER BY month"
        );

        // Patient age distribution
        $ageDistribution = $this->db->fetchAll(
            "SELECT
                CASE
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 12 THEN '0-1 yr'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 1 AND 3 THEN '1-3 yrs'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 4 AND 6 THEN '4-6 yrs'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 7 AND 12 THEN '7-12 yrs'
                    ELSE '13+ yrs'
                END AS age_group,
                COUNT(*) AS total
             FROM patients GROUP BY age_group ORDER BY MIN(date_of_birth) DESC"
        );

        Response::success([
            'appointment_trends' => $appointmentTrends,
            'appointments_by_type' => $appointmentsByType,
            'top_doctors' => $topDoctors,
            'vaccination_trends' => $vaccinationTrends,
            'age_distribution' => $ageDistribution,
        ]);
    }

    /**
     * Export users as CSV.
     */
    public function exportCsv(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'user_type' => $_GET['user_type'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];

        $csv = $this->userService->exportUsersCsv($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        echo $csv;
        exit;
    }
}
