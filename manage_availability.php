<?php
// manage_availability.php
require 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'DOCTOR' && $_SESSION['user_type'] !== 'DOCTOR_OWNER')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
}

$doctor_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

try {
    if ($action === 'set_unavailable') {
        $date = trim($_POST['date']);
        $reason = trim($_POST['reason'] ?? '');
        $is_all_day = isset($_POST['is_all_day']) ? 1 : 0;

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit();
        }

        // Check if already exists
        $check_query = "SELECT id FROM doctor_availability
                       WHERE doctor_id = ? AND specific_date = ? AND availability_type = 'UNAVAILABLE'";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "is", $doctor_id, $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            // Update existing
            $update_query = "UPDATE doctor_availability
                           SET reason = ?,
                               is_all_day = ?,
                               updated_at = NOW()
                           WHERE doctor_id = ? AND specific_date = ? AND availability_type = 'UNAVAILABLE'";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "siis", $reason, $is_all_day, $doctor_id, $date);
        } else {
            // Insert new
            $insert_query = "INSERT INTO doctor_availability
                           (doctor_id, specific_date, start_time, end_time, availability_type, reason, is_all_day, active)
                           VALUES (?, ?, '00:00:00', '23:59:59', 'UNAVAILABLE', ?, ?, 1)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "issi", $doctor_id, $date, $reason, $is_all_day);
        }

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Marked as unavailable']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating availability']);
        }

    } else if ($action === 'remove_unavailable') {
        $date = trim($_POST['date']);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit();
        }

        $delete_query = "DELETE FROM doctor_availability
                        WHERE doctor_id = ? AND specific_date = ? AND availability_type = 'UNAVAILABLE'";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "is", $doctor_id, $date);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Availability restored']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing unavailability']);
        }

    } else if ($action === 'get_unavailable_dates') {
        $month = isset($_GET['month']) ? intval($_GET['month']) : (isset($_POST['month']) ? intval($_POST['month']) : date('n'));
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($_POST['year']) ? intval($_POST['year']) : date('Y'));

        $first_day = date('Y-m-01', strtotime("$year-$month-01"));
        $last_day = date('Y-m-t', strtotime("$year-$month-01"));

        $query = "SELECT id, specific_date as date, reason, is_all_day, start_time, end_time, availability_type
                 FROM doctor_availability
                 WHERE doctor_id = ?
                 AND availability_type = 'UNAVAILABLE'
                 AND specific_date BETWEEN ? AND ?
                 AND active = 1
                 ORDER BY specific_date ASC";

        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $first_day, $last_day);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $unavailable_dates = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['is_all_day'] = (int)$row['is_all_day'];
            $unavailable_dates[] = $row;
        }

        // Also get appointments for this month
        $appt_query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.type, a.duration,
                       p.first_name as patient_first_name, p.last_name as patient_last_name
                       FROM appointments a
                       JOIN patients p ON a.patient_id = p.id
                       WHERE a.doctor_id = ?
                       AND a.appointment_date BETWEEN ? AND ?
                       AND a.status NOT IN ('CANCELLED','NO_SHOW')
                       ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        $stmt = mysqli_prepare($conn, $appt_query);
        mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $first_day, $last_day);
        mysqli_stmt_execute($stmt);
        $appt_result = mysqli_stmt_get_result($stmt);

        $appointments = [];
        $appointment_counts = [];
        while ($row = mysqli_fetch_assoc($appt_result)) {
            $appointments[] = $row;
            $d = $row['appointment_date'];
            if (!isset($appointment_counts[$d])) $appointment_counts[$d] = 0;
            $appointment_counts[$d]++;
        }

        echo json_encode([
            'success' => true,
            'unavailable_dates' => $unavailable_dates,
            'appointments' => $appointments,
            'appointment_counts' => $appointment_counts
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Error managing availability: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
