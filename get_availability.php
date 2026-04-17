<?php
require 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : intval($_SESSION['user_id']);
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2030) {
    echo json_encode(['success' => false, 'message' => 'Invalid date range']);
    exit();
}

// Calculate date range
$first_day = date('Y-m-01', strtotime("$year-$month-01"));
$last_day = date('Y-m-t', strtotime("$year-$month-01"));

try {
    // Doctor's unavailable dates
    $unavailable = [];
    $unavail_query = "SELECT specific_date as date, start_time, end_time, reason, is_all_day, availability_type
                      FROM doctor_availability 
                      WHERE doctor_id = ? AND specific_date BETWEEN ? AND ? AND availability_type = 'UNAVAILABLE'
                      ORDER BY specific_date";
    $stmt = mysqli_prepare($conn, $unavail_query);
    mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $first_day, $last_day);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $unavailable[] = $row;
    }

    // Doctor's appointments
    $appointments = [];
    $appt_query = "SELECT appointment_date as date, appointment_time as time, status, type, duration,
                          p.first_name as patient_first_name, p.last_name as patient_last_name
                   FROM appointments a
                   JOIN patients p ON a.patient_id = p.id
                   WHERE a.doctor_id = ? AND appointment_date BETWEEN ? AND ?
                   AND status NOT IN ('CANCELLED', 'NO_SHOW')
                   ORDER BY appointment_date, appointment_time";
    $stmt = mysqli_prepare($conn, $appt_query);
    mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $first_day, $last_day);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }

    // Appointment counts per day
    $appointment_counts = [];
    foreach ($appointments as $appt) {
        $date = $appt['date'];
        if (!isset($appointment_counts[$date])) $appointment_counts[$date] = 0;
        $appointment_counts[$date]++;
    }

    // Working days — prefer doctor's own schedule, fall back to clinic hours
    $working_days = [];

    // 1. Check doctor_schedules for this doctor's active schedule days
    $ds_query = "SELECT DISTINCT day_of_week FROM doctor_schedules WHERE doctor_id = ? AND active = 1";
    $ds_stmt = mysqli_prepare($conn, $ds_query);
    mysqli_stmt_bind_param($ds_stmt, "i", $doctor_id);
    mysqli_stmt_execute($ds_stmt);
    $ds_result = mysqli_stmt_get_result($ds_stmt);
    while ($ds_row = mysqli_fetch_assoc($ds_result)) {
        $working_days[] = strtoupper($ds_row['day_of_week']);
    }

    // 2. Fall back to clinic business hours if no doctor-specific schedule
    if (empty($working_days)) {
        $bh_query = "SELECT setting_value FROM clinic_settings WHERE setting_key = 'business_hours' LIMIT 1";
        $bh_result = mysqli_query($conn, $bh_query);
        if ($bh_result && ($bh_row = mysqli_fetch_assoc($bh_result))) {
            $business_hours = json_decode($bh_row['setting_value'], true);
            if (is_array($business_hours)) {
                foreach ($business_hours as $day => $hours) {
                    if ($hours !== null) {
                        $working_days[] = strtoupper($day);
                    }
                }
            }
        }
    }

    // 3. Default Mon-Fri if nothing found
    if (empty($working_days)) {
        $working_days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'];
    }

    echo json_encode([
        'success' => true,
        'unavailable_dates' => $unavailable,
        'appointments' => $appointments,
        'appointment_counts' => $appointment_counts,
        'working_days' => $working_days
    ]);

} catch (Exception $e) {
    error_log("get_availability error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
