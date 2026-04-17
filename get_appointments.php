<?php
// get_appointments.php
require 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Get parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Get first and last day of the month
    $first_day = date('Y-m-01', strtotime("$year-$month-01"));
    $last_day = date('Y-m-t', strtotime("$year-$month-01"));
    
    if ($user_type === 'DOCTOR' || $user_type === 'DOCTOR_OWNER') {
        // Get doctor's appointments
        $query = "SELECT a.*, 
                  p.first_name as patient_first_name, 
                  p.last_name as patient_last_name,
                  u.first_name as parent_first_name,
                  u.last_name as parent_last_name
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN users u ON p.parent_id = u.id
                  WHERE a.doctor_id = ?
                  AND a.appointment_date BETWEEN ? AND ?
                  ORDER BY a.appointment_date, a.appointment_time";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $first_day, $last_day);
        
    } else if ($user_type === 'PARENT') {
        // Get parent's children's appointments
        $query = "SELECT a.*, 
                  p.first_name as patient_first_name, 
                  p.last_name as patient_last_name,
                  u.first_name as doctor_first_name,
                  u.last_name as doctor_last_name
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN users u ON a.doctor_id = u.id
                  WHERE p.parent_id = ?
                  AND a.appointment_date BETWEEN ? AND ?
                  ORDER BY a.appointment_date, a.appointment_time";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $first_day, $last_day);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid user type']);
        exit();
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $appointments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'appointments' => $appointments
    ]);
    
} catch (Exception $e) {
    error_log("Error getting appointments: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving appointments']);
}
?>
