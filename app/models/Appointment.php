<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Appointment extends Model
{
    protected string $table = 'appointments';

    /**
     * Get appointment with full related data.
     */
    public function getWithDetails(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT a.*,
                    p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    p.date_of_birth AS patient_dob, p.gender AS patient_gender,
                    p.allergies AS patient_allergies,
                    d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    d.specialization AS doctor_specialization,
                    par.first_name AS parent_first_name, par.last_name AS parent_last_name,
                    par.phone AS parent_phone, par.email AS parent_email
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             JOIN users d ON a.doctor_id = d.id
             JOIN users par ON p.parent_id = par.id
             WHERE a.id = ?",
            [$id]
        );
    }

    /**
     * Get appointments for a doctor on a specific date.
     */
    public function getByDoctorDate(int $doctorId, string $date): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    p.date_of_birth AS patient_dob
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status != 'CANCELLED'
             ORDER BY a.appointment_time",
            [$doctorId, $date]
        );
    }

    /**
     * Get appointments for a doctor within a date range.
     */
    public function getByDoctorRange(int $doctorId, string $startDate, string $endDate, ?string $status = null): array
    {
        $sql = "SELECT a.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                       p.date_of_birth AS patient_dob, par.phone AS parent_phone
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users par ON p.parent_id = par.id
                WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ?";
        $params = [$doctorId, $startDate, $endDate];

        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.appointment_date, a.appointment_time";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get appointments for a patient.
     */
    public function getByPatient(int $patientId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    d.specialization AS doctor_specialization
             FROM appointments a
             JOIN users d ON a.doctor_id = d.id
             WHERE a.patient_id = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC
             LIMIT ?",
            [$patientId, $limit]
        );
    }

    /**
     * Get upcoming appointments for a parent's children.
     */
    public function getUpcomingForParent(int $parentId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    d.specialization AS doctor_specialization
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             JOIN users d ON a.doctor_id = d.id
             WHERE p.parent_id = ? AND a.appointment_date >= CURDATE()
                   AND a.status IN ('SCHEDULED', 'CONFIRMED')
             ORDER BY a.appointment_date, a.appointment_time
             LIMIT ?",
            [$parentId, $limit]
        );
    }

    /**
     * Check if a time slot is already booked (double-booking prevention).
     */
    public function isSlotBooked(int $doctorId, string $date, string $time, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM appointments
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
                AND status NOT IN ('CANCELLED', 'NO_SHOW')";
        $params = [$doctorId, $date, $time];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    /**
     * Check for overlapping appointments.
     */
    public function hasOverlap(int $doctorId, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM appointments
                WHERE doctor_id = ? AND appointment_date = ?
                AND status NOT IN ('CANCELLED', 'NO_SHOW')
                AND (
                    (appointment_time < ? AND ADDTIME(appointment_time, SEC_TO_TIME(duration * 60)) > ?)
                    OR (appointment_time >= ? AND appointment_time < ?)
                )";
        $params = [$doctorId, $date, $endTime, $startTime, $startTime, $endTime];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    /**
     * Get today's appointment count for a doctor.
     */
    public function getTodayCountForDoctor(int $doctorId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status != 'CANCELLED'",
            [$doctorId]
        );
    }

    /**
     * Get appointment stats.
     */
    public function getStats(?int $doctorId = null): array
    {
        $where = $doctorId ? "AND doctor_id = ?" : "";
        $params = $doctorId ? [$doctorId] : [];

        $todayParams = $params;
        $monthParams = $params;

        return [
            'today' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status != 'CANCELLED' $where",
                $todayParams
            ),
            'this_week' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE YEARWEEK(appointment_date) = YEARWEEK(CURDATE()) AND status != 'CANCELLED' $where",
                $params
            ),
            'this_month' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE()) AND status != 'CANCELLED' $where",
                $monthParams
            ),
            'upcoming' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND status IN ('SCHEDULED', 'CONFIRMED') $where",
                $params
            ),
            'completed' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE status = 'COMPLETED' $where",
                $params
            ),
        ];
    }

    /**
     * Get available time slots for a doctor on a given date.
     */
    public function getAvailableSlots(int $doctorId, string $date): array
    {
        // Get doctor's schedule for the day
        $dayOfWeek = strtoupper(date('l', strtotime($date)));

        $schedule = $this->db->fetchOne(
            "SELECT * FROM doctor_availability
             WHERE doctor_id = ? AND day_of_week = ? AND availability_type = 'RECURRING' AND active = 1",
            [$doctorId, $dayOfWeek]
        );

        // Check for date-specific overrides
        $override = $this->db->fetchOne(
            "SELECT * FROM doctor_availability
             WHERE doctor_id = ? AND specific_date = ? AND availability_type IN ('AVAILABLE', 'UNAVAILABLE')",
            [$doctorId, $date]
        );

        if ($override && $override['availability_type'] === 'UNAVAILABLE') {
            return []; // Doctor unavailable on this date
        }

        $activeSchedule = $override ?? $schedule;
        if (!$activeSchedule) {
            return [];
        }

        // Generate time slots
        $slots = [];
        $startTime = strtotime($activeSchedule['start_time']);
        $endTime = strtotime($activeSchedule['end_time']);
        $slotDuration = (int) $activeSchedule['slot_duration'] * 60;

        // Get existing appointments
        $booked = $this->db->fetchAll(
            "SELECT appointment_time, duration FROM appointments
             WHERE doctor_id = ? AND appointment_date = ? AND status NOT IN ('CANCELLED', 'NO_SHOW')",
            [$doctorId, $date]
        );

        $bookedTimes = [];
        foreach ($booked as $apt) {
            $bookedTimes[] = $apt['appointment_time'];
        }

        for ($time = $startTime; $time + $slotDuration <= $endTime; $time += $slotDuration) {
            $timeStr = date('H:i:s', $time);
            $slots[] = [
                'time' => $timeStr,
                'formatted' => date('g:i A', $time),
                'available' => !in_array($timeStr, $bookedTimes),
            ];
        }

        return $slots;
    }
}
