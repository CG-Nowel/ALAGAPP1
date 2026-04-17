<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AdminController;
use App\Controllers\DoctorController;
use App\Controllers\ParentController;
use App\Controllers\NotificationController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\DoctorMiddleware;
use App\Middleware\ParentMiddleware;

/** @var \App\Core\Router $router */

// ─── Public Routes ──────────────────────────────────────────────────────
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/verify-email', [AuthController::class, 'verifyEmail']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

// ─── Authenticated Routes ───────────────────────────────────────────────
$router->group(['middleware' => [AuthMiddleware::class]], function ($router) {

    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/logout', [AuthController::class, 'logout']);
    $router->get('/change-password', [AuthController::class, 'showChangePassword']);
    $router->post('/change-password', [AuthController::class, 'changePassword']);
    $router->post('/verify-2fa', [AuthController::class, 'verify2FA']);

    // ─── Notifications ──────────────────────────────────────────────
    $router->get('/api/notifications/bell', [NotificationController::class, 'bell']);
    $router->get('/api/notifications', [NotificationController::class, 'index']);
    $router->post('/api/notifications/{id}/read', [NotificationController::class, 'markRead']);
    $router->post('/api/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // ─── Admin Routes ───────────────────────────────────────────────
    $router->group(['middleware' => [AdminMiddleware::class]], function ($router) {
        $router->get('/admin/dashboard', [AdminController::class, 'dashboard']);
        $router->get('/admin/users', [AdminController::class, 'users']);
        $router->post('/admin/users/create-doctor', [AdminController::class, 'createDoctor']);
        $router->post('/admin/users/toggle-status', [AdminController::class, 'toggleUserStatus']);
        $router->post('/admin/users/bulk-action', [AdminController::class, 'bulkAction']);
        $router->post('/admin/appointments/create', [AdminController::class, 'createAppointment']);
        $router->get('/admin/activity-logs', [AdminController::class, 'activityLogs']);
        $router->get('/admin/settings', [AdminController::class, 'settings']);
        $router->post('/admin/settings', [AdminController::class, 'settings']);
        $router->get('/admin/analytics', [AdminController::class, 'analytics']);
        $router->get('/admin/export/users', [AdminController::class, 'exportCsv']);
    });

    // ─── Doctor Routes ──────────────────────────────────────────────
    $router->group(['middleware' => [DoctorMiddleware::class]], function ($router) {
        $router->get('/doctor/dashboard', [DoctorController::class, 'dashboard']);
        $router->get('/doctor/appointments', [DoctorController::class, 'appointments']);
        $router->get('/doctor/appointments/{id}', [DoctorController::class, 'appointmentDetails']);
        $router->post('/doctor/appointments/update-status', [DoctorController::class, 'updateAppointmentStatus']);
        $router->get('/doctor/patients', [DoctorController::class, 'patients']);
        $router->get('/doctor/patients/{id}/records', [DoctorController::class, 'patientRecords']);
        $router->post('/doctor/consultation-notes', [DoctorController::class, 'createConsultationNote']);
        $router->post('/doctor/consultation-notes/{id}/update', [DoctorController::class, 'updateConsultationNote']);
        $router->post('/doctor/consultation-notes/{id}/delete', [DoctorController::class, 'deleteConsultationNote']);
        $router->post('/doctor/prescriptions', [DoctorController::class, 'createPrescription']);
        $router->post('/doctor/prescriptions/{id}/update', [DoctorController::class, 'updatePrescription']);
        $router->post('/doctor/prescriptions/{id}/delete', [DoctorController::class, 'deletePrescription']);
        $router->get('/doctor/prescriptions/{id}/print', [DoctorController::class, 'printPrescription']);
        $router->post('/doctor/vaccinations', [DoctorController::class, 'recordVaccination']);
        $router->get('/doctor/availability', [DoctorController::class, 'manageAvailability']);
        $router->post('/doctor/availability', [DoctorController::class, 'manageAvailability']);
        $router->post('/doctor/availability/{id}/delete', [DoctorController::class, 'deleteAvailability']);
        $router->get('/doctor/patients/{id}/export', [DoctorController::class, 'exportPatientData']);
    });

    // ─── Parent Routes ──────────────────────────────────────────────
    $router->group(['middleware' => [ParentMiddleware::class]], function ($router) {
        $router->get('/parent/dashboard', [ParentController::class, 'dashboard']);
        $router->get('/parent/children', [ParentController::class, 'children']);
        $router->post('/parent/children', [ParentController::class, 'addChild']);
        $router->post('/parent/children/{id}/update', [ParentController::class, 'updateChild']);
        $router->post('/parent/appointments', [ParentController::class, 'bookAppointment']);
        $router->post('/parent/appointments/{id}/cancel', [ParentController::class, 'cancelAppointment']);
        $router->get('/parent/appointments', [ParentController::class, 'myAppointments']);
        $router->get('/parent/available-slots', [ParentController::class, 'getAvailableSlots']);
        $router->get('/parent/doctors', [ParentController::class, 'getDoctors']);
        $router->get('/parent/patients/{id}/records', [ParentController::class, 'patientRecords']);
        $router->get('/parent/patients/{id}/vaccinations', [ParentController::class, 'vaccinationSchedule']);
        $router->post('/parent/files/upload', [ParentController::class, 'uploadFile']);
        $router->get('/parent/files/{id}/download', [ParentController::class, 'downloadFile']);
        $router->post('/parent/waitlist', [ParentController::class, 'addToWaitlist']);
        $router->get('/parent/prescriptions/{id}/print', [ParentController::class, 'viewPrescription']);
    });
});
