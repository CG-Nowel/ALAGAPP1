<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Core\Database;
use RuntimeException;

class AppointmentService
{
    private Appointment $appointmentModel;
    private Patient $patientModel;
    private ActivityLog $activityLog;
    private Notification $notificationModel;
    private Database $db;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->patientModel = new Patient();
        $this->activityLog = new ActivityLog();
        $this->notificationModel = new Notification();
        $this->db = Database::getInstance();
    }

    /**
     * Book a new appointment with double-booking prevention.
     */
    public function bookAppointment(array $data, int $createdBy): array
    {
        // Validate the appointment date is not in the past
        if (strtotime($data['appointment_date']) < strtotime('today')) {
            return ['success' => false, 'message' => 'Cannot book appointments in the past.'];
        }

        // Check advance booking limit
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $maxDays = $config['security']['cancellation_hours'] ?? 60;

        $this->db->beginTransaction();
        try {
            // Double-booking prevention: check within transaction
            $duration = (int) ($data['duration'] ?? 30);
            $endTime = date('H:i:s', strtotime($data['appointment_time']) + ($duration * 60));

            if ($this->appointmentModel->hasOverlap(
                (int) $data['doctor_id'],
                $data['appointment_date'],
                $data['appointment_time'],
                $endTime
            )) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'This time slot is no longer available. Please choose another.'];
            }

            $appointmentId = $this->appointmentModel->create([
                'patient_id' => (int) $data['patient_id'],
                'doctor_id' => (int) $data['doctor_id'],
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'end_time' => $endTime,
                'type' => $data['type'] ?? 'CONSULTATION',
                'reason' => $data['reason'] ?? null,
                'duration' => $duration,
                'created_by' => $createdBy,
            ]);

            $this->db->commit();

            // Send notifications
            $this->sendAppointmentNotifications($appointmentId, 'booked');

            $this->activityLog->log('APPOINTMENT_CREATED', $createdBy, 'appointment', $appointmentId,
                "Appointment booked for " . $data['appointment_date'] . " at " . $data['appointment_time']);

            return [
                'success' => true,
                'message' => 'Appointment booked successfully.',
                'data' => ['appointment_id' => $appointmentId],
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(int $appointmentId, string $status, int $userId, ?string $reason = null): array
    {
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            return ['success' => false, 'message' => 'Appointment not found.'];
        }

        $validTransitions = [
            'SCHEDULED' => ['CONFIRMED', 'CANCELLED', 'WAITLISTED'],
            'CONFIRMED' => ['IN_PROGRESS', 'CANCELLED', 'NO_SHOW'],
            'IN_PROGRESS' => ['COMPLETED'],
            'WAITLISTED' => ['SCHEDULED', 'CANCELLED'],
        ];

        $currentStatus = $appointment['status'];
        if (!isset($validTransitions[$currentStatus]) || !in_array($status, $validTransitions[$currentStatus])) {
            return ['success' => false, 'message' => "Cannot change status from $currentStatus to $status."];
        }

        $updateData = ['status' => $status];

        if ($status === 'CANCELLED') {
            // Enforce cancellation policy
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $cancelHours = $config['security']['cancellation_hours'];
            $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);

            if ($appointmentDateTime - time() < ($cancelHours * 3600)) {
                // Late cancellation - log but still allow
                $this->activityLog->log('LATE_CANCELLATION', $userId, 'appointment', $appointmentId,
                    "Appointment cancelled within {$cancelHours}h policy window");
            }

            $updateData['cancellation_reason'] = $reason;
            $updateData['cancelled_at'] = date('Y-m-d H:i:s');

            // Check waitlist and notify
            $this->processWaitlist($appointment);
        }

        $this->appointmentModel->updateById($appointmentId, $updateData);
        $this->sendAppointmentNotifications($appointmentId, strtolower($status));

        $this->activityLog->log('APPOINTMENT_STATUS_CHANGE', $userId, 'appointment', $appointmentId,
            "Status changed from $currentStatus to $status");

        return ['success' => true, 'message' => "Appointment {$status} successfully."];
    }

    /**
     * Get available time slots for a doctor on a given date.
     */
    public function getAvailableSlots(int $doctorId, string $date): array
    {
        return $this->appointmentModel->getAvailableSlots($doctorId, $date);
    }

    /**
     * Get appointment details.
     */
    public function getDetails(int $appointmentId): ?array
    {
        return $this->appointmentModel->getWithDetails($appointmentId);
    }

    /**
     * Get appointments with filtering.
     */
    public function getFiltered(array $filters, int $page = 1, int $perPage = 15): array
    {
        $sql = "SELECT a.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                       d.first_name AS doctor_first_name, d.last_name AS doctor_last_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users d ON a.doctor_id = d.id
                WHERE 1=1";
        $countSql = "SELECT COUNT(*) FROM appointments a
                     JOIN patients p ON a.patient_id = p.id
                     WHERE 1=1";
        $params = [];
        $countParams = [];

        if (!empty($filters['doctor_id'])) {
            $sql .= " AND a.doctor_id = ?";
            $countSql .= " AND a.doctor_id = ?";
            $params[] = $filters['doctor_id'];
            $countParams[] = $filters['doctor_id'];
        }

        if (!empty($filters['patient_id'])) {
            $sql .= " AND a.patient_id = ?";
            $countSql .= " AND a.patient_id = ?";
            $params[] = $filters['patient_id'];
            $countParams[] = $filters['patient_id'];
        }

        if (!empty($filters['parent_id'])) {
            $sql .= " AND p.parent_id = ?";
            $countSql .= " AND p.parent_id = ?";
            $params[] = $filters['parent_id'];
            $countParams[] = $filters['parent_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND a.appointment_date >= ?";
            $countSql .= " AND a.appointment_date >= ?";
            $params[] = $filters['date_from'];
            $countParams[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND a.appointment_date <= ?";
            $countSql .= " AND a.appointment_date <= ?";
            $params[] = $filters['date_to'];
            $countParams[] = $filters['date_to'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $countSql .= " AND a.status = ?";
            $params[] = $filters['status'];
            $countParams[] = $filters['status'];
        }

        $total = (int) $this->db->fetchColumn($countSql, $countParams);
        $offset = ($page - 1) * $perPage;

        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $data = $this->db->fetchAll($sql, $params);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Add to waitlist.
     */
    public function addToWaitlist(array $data, int $createdBy): array
    {
        $id = $this->db->insert('appointment_waitlist', [
            'patient_id' => (int) $data['patient_id'],
            'doctor_id' => (int) $data['doctor_id'],
            'preferred_date' => $data['preferred_date'],
            'preferred_time_start' => $data['preferred_time_start'] ?? null,
            'preferred_time_end' => $data['preferred_time_end'] ?? null,
            'type' => $data['type'] ?? 'CONSULTATION',
            'reason' => $data['reason'] ?? null,
            'created_by' => $createdBy,
            'expires_at' => date('Y-m-d H:i:s', strtotime($data['preferred_date'] . ' +1 day')),
        ]);

        return [
            'success' => true,
            'message' => 'Added to waitlist. You will be notified if a slot opens up.',
            'data' => ['waitlist_id' => $id],
        ];
    }

    /**
     * Process waitlist when an appointment is cancelled.
     */
    private function processWaitlist(array $cancelledAppointment): void
    {
        $waitlistEntries = $this->db->fetchAll(
            "SELECT w.*, p.parent_id FROM appointment_waitlist w
             JOIN patients p ON w.patient_id = p.id
             WHERE w.doctor_id = ? AND w.preferred_date = ? AND w.status = 'WAITING'
             ORDER BY w.created_at ASC LIMIT 1",
            [$cancelledAppointment['doctor_id'], $cancelledAppointment['appointment_date']]
        );

        foreach ($waitlistEntries as $entry) {
            $this->db->update('appointment_waitlist', [
                'status' => 'OFFERED',
                'notified_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$entry['id']]);

            $this->notificationModel->createNotification(
                $entry['parent_id'],
                'Appointment Slot Available',
                'A slot has opened up for your waitlisted appointment. Please book soon!',
                'APPOINTMENT',
                'ALL',
                'waitlist',
                $entry['id']
            );
        }
    }

    /**
     * Send notifications for appointment events.
     */
    private function sendAppointmentNotifications(int $appointmentId, string $event): void
    {
        $appointment = $this->appointmentModel->getWithDetails($appointmentId);
        if (!$appointment) {
            return;
        }

        $parentId = $this->db->fetchColumn(
            "SELECT parent_id FROM patients WHERE id = ?",
            [$appointment['patient_id']]
        );

        $titles = [
            'booked' => 'Appointment Booked',
            'confirmed' => 'Appointment Confirmed',
            'cancelled' => 'Appointment Cancelled',
            'completed' => 'Appointment Completed',
        ];

        $title = $titles[$event] ?? 'Appointment Update';
        $date = date('M j, Y', strtotime($appointment['appointment_date']));
        $time = date('g:i A', strtotime($appointment['appointment_time']));

        // Notify parent
        if ($parentId) {
            $this->notificationModel->createNotification(
                (int) $parentId,
                $title,
                "Appointment for {$appointment['patient_first_name']} on {$date} at {$time} has been {$event}.",
                'APPOINTMENT',
                'IN_APP',
                'appointment',
                $appointmentId
            );
        }

        // Notify doctor
        $this->notificationModel->createNotification(
            (int) $appointment['doctor_id'],
            $title,
            "Appointment with {$appointment['patient_first_name']} {$appointment['patient_last_name']} on {$date} at {$time}.",
            'APPOINTMENT',
            'IN_APP',
            'appointment',
            $appointmentId
        );
    }
}
