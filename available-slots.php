<?php
require 'db_connect.php';
header('Content-Type: application/json');

// Check if parent logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'PARENT') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (!$doctor_id || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Get doctor's availability for this date
$availability = get_doctor_availability_for_date($conn, $doctor_id, $date);

// Generate 30-minute slots
$slots = generate_available_slots($availability);

echo json_encode([
    'success' => true,
    'data' => $slots
]);

function get_doctor_availability_for_date($conn, $doctor_id, $date) {
    $doctor_id = intval($doctor_id);
    
    // Check specific date overrides first
    $query = "SELECT start_time, end_time, availability_type FROM doctor_availability 
              WHERE doctor_id = ? AND specific_date = ? AND active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $doctor_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row;
    }
    
    // Fallback to recurring schedule (day of week)
    $day_name = strtoupper(date('l', strtotime($date)));
    $query = "SELECT start_time, end_time, availability_type FROM doctor_availability
              WHERE doctor_id = ? AND day_of_week = ? AND availability_type = 'RECURRING' AND active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $doctor_id, $day_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result) ?: ['start_time' => '09:00:00', 'end_time' => '17:00:00', 'availability_type' => 'RECURRING'];
}

function generate_available_slots($availability) {
    $start = new DateTime($availability['start_time']);
    $end = new DateTime($availability['end_time']);
    $slots = [];
    
    if ($availability['availability_type'] === 'UNAVAILABLE') {
        return $slots; // No slots
    }
    
    for ($current = clone $start; $current < $end; $current->modify('+30 minutes')) {
        $time24 = $current->format('H:i:s');
        $timeFormatted = $current->format('g:i A');
        
        $slots[] = [
            'time' => $time24,
            'formatted' => $timeFormatted,
            'available' => true
        ];
    }
    
    return $slots;
}
?>