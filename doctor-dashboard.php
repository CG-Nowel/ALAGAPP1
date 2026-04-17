<?php
// doctor-dashboard.php
session_start();
require_once 'db_connect.php';

// Enhanced security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'DOCTOR' && $_SESSION['user_type'] !== 'DOCTOR_OWNER')) {
    header('Location: index.php');
    exit();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get current user data
$current_user = [
    'id' => $_SESSION['user_id'],
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'user_type' => $_SESSION['user_type'] ?? '',
    'specialization' => $_SESSION['specialization'] ?? 'Pediatrician'
];

// ===== MISSING HELPER FUNCTIONS =====



/**
 * Get all vaccination records for a patient
 */
function get_patient_vaccination_records($conn, $patient_id, $doctor_id) {
    $patient_id = intval($patient_id);
    $doctor_id = intval($doctor_id);
    
    $verify_query = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $patient_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) === 0) {
        return ['success' => false, 'message' => 'Not authorized to view this patient'];
    }
    
    $records = [];
    $query = "SELECT * FROM vaccination_records 
              WHERE patient_id = ? 
              ORDER BY administration_date DESC, created_at DESC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
    
    return ['success' => true, 'data' => $records];
}

/**
 * Get a single vaccination record
 */
function get_vaccination_record($conn, $vaccination_id, $doctor_id) {
    $vaccination_id = intval($vaccination_id);
    $doctor_id = intval($doctor_id);
    
    $verify_query = "SELECT vr.* FROM vaccination_records vr
                     JOIN appointments a ON vr.patient_id = a.patient_id
                     WHERE vr.id = ? AND a.doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $vaccination_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($result) === 0) {
        return null;
    }
    
    return mysqli_fetch_assoc($result);
}

/**
 * Edit a vaccination record
 */
function edit_vaccination_record($conn, $vaccination_id, $data, $doctor_id) {
    $vaccination_id = intval($vaccination_id);
    $doctor_id = intval($doctor_id);
    
    // Verify the vaccination record belongs to this doctor
    $verify_query = "SELECT vr.id, vr.patient_id FROM vaccination_records vr
                     JOIN appointments a ON vr.patient_id = a.patient_id
                     WHERE vr.id = ? AND a.doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $vaccination_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) === 0) {
        return ['success' => false, 'message' => 'Not authorized to edit this vaccination record'];
    }
    
    $vaccine_name = mysqli_real_escape_string($conn, $data['vaccine_name'] ?? '');
    $dose_number = intval($data['dose_number'] ?? 1);
    $administration_date = $data['administration_date'] ?? '';
    $lot_number = mysqli_real_escape_string($conn, $data['lot_number'] ?? '');
    $notes = mysqli_real_escape_string($conn, $data['notes'] ?? '');
    
    if (!$vaccine_name || !$administration_date) {
        return ['success' => false, 'message' => 'Vaccine name and administration date are required'];
    }
    
    if (!strtotime($administration_date)) {
        return ['success' => false, 'message' => 'Invalid date format'];
    }
    
    $query = "UPDATE vaccination_records 
              SET vaccine_name = ?, dose_number = ?, administration_date = ?, 
                  lot_number = ?, notes = ?, updated_at = NOW()
              WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sissssi", $vaccine_name, $dose_number, $administration_date, 
                          $lot_number, $notes, $vaccination_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Vaccination record updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update vaccination record'];
}

/**
 * Delete a vaccination record
 */
function delete_vaccination_record($conn, $vaccination_id, $doctor_id) {
    $vaccination_id = intval($vaccination_id);
    $doctor_id = intval($doctor_id);
    
    // Verify the vaccination record belongs to this doctor
    $verify_query = "SELECT vr.id, vr.patient_id FROM vaccination_records vr
                     JOIN appointments a ON vr.patient_id = a.patient_id
                     WHERE vr.id = ? AND a.doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $vaccination_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) === 0) {
        return ['success' => false, 'message' => 'Not authorized to delete this vaccination record'];
    }
    
    $delete_query = "DELETE FROM vaccination_records WHERE id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "i", $vaccination_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        return ['success' => true, 'message' => 'Vaccination record deleted successfully'];
    }
    return ['success' => false, 'message' => 'Failed to delete vaccination record'];
}

// ===== VACCINE NEEDS FUNCTIONS =====
function get_patient_vaccine_needs($conn, $patient_id, $doctor_id) {
    $patient_id = intval($patient_id);
    $doctor_id = intval($doctor_id);
    
    $verify_query = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $patient_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) === 0) {
        return ['success' => false, 'message' => 'Not authorized or patient not found'];
    }

    $rows = [];
    $query = "SELECT id, vaccine_name, recommended_date, status, notes, created_by, created_at, updated_at
              FROM patient_vaccine_needs
              WHERE patient_id = ?
              ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    
    return ['success' => true, 'data' => $rows];
}

function set_patient_vaccine_need($conn, $data, $doctor_id) {
    $doctor_id = intval($doctor_id);
    $patient_id = intval($data['patient_id'] ?? 0);
    $vaccine_name = mysqli_real_escape_string($conn, trim($data['vaccine_name'] ?? ''));
    $recommended_date = !empty($data['recommended_date']) ? $data['recommended_date'] : null;
    $status = in_array($data['status'] ?? 'RECOMMENDED', ['RECOMMENDED','SCHEDULED','GIVEN','NOT_NEEDED']) 
        ? $data['status'] 
        : 'RECOMMENDED';
    $notes = mysqli_real_escape_string($conn, $data['notes'] ?? '');
    $id = isset($data['id']) ? intval($data['id']) : 0;

    if ($patient_id <= 0 || $vaccine_name === '') {
        return ['success' => false, 'message' => 'Patient and vaccine name are required'];
    }

    $verify_query = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $patient_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) === 0) {
        return ['success' => false, 'message' => 'Not authorized to modify this patient'];
    }

    if ($id > 0) {
        $query = "UPDATE patient_vaccine_needs 
                  SET vaccine_name = ?, 
                      recommended_date = ?, 
                      status = ?, 
                      notes = ?, 
                      updated_at = NOW() 
                  WHERE id = ? AND patient_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssii", $vaccine_name, $recommended_date, $status, $notes, $id, $patient_id);
        
        if (mysqli_stmt_execute($stmt)) {
            return ['success' => true, 'message' => 'Vaccine need updated successfully', 'id' => $id];
        } else {
            return ['success' => false, 'message' => 'Failed to update vaccine need'];
        }
    } else {
        $query = "INSERT INTO patient_vaccine_needs 
                  (patient_id, vaccine_name, recommended_date, status, notes, created_by, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isssis", $patient_id, $vaccine_name, $recommended_date, $status, $notes, $doctor_id);
        
        if (mysqli_stmt_execute($stmt)) {
            return ['success' => true, 'message' => 'Vaccine need added successfully', 'id' => mysqli_insert_id($conn)];
        } else {
            return ['success' => false, 'message' => 'Failed to add vaccine need'];
        }
    }
}

function delete_patient_vaccine_need($conn, $vaccine_need_id, $doctor_id) {
    $vaccine_need_id = intval($vaccine_need_id);
    $doctor_id = intval($doctor_id);
    
    $verify_query = "SELECT patient_id FROM patient_vaccine_needs WHERE id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "i", $vaccine_need_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) === 0) {
        return ['success' => false, 'message' => 'Vaccine need not found'];
    }
    
    $vaccine_row = mysqli_fetch_assoc($verify_result);
    $patient_id = $vaccine_row['patient_id'];
    
    $patient_check = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
    $patient_stmt = mysqli_prepare($conn, $patient_check);
    mysqli_stmt_bind_param($patient_stmt, "ii", $patient_id, $doctor_id);
    mysqli_stmt_execute($patient_stmt);
    $patient_result = mysqli_stmt_get_result($patient_stmt);
    
    if (mysqli_num_rows($patient_result) === 0) {
        return ['success' => false, 'message' => 'Not authorized to delete this vaccine need'];
    }
    
    $delete_query = "DELETE FROM patient_vaccine_needs WHERE id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "i", $vaccine_need_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        return ['success' => true, 'message' => 'Vaccine need deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete vaccine need'];
    }
}

function get_recommended_vaccines_for_age($conn, $age_months) {
    $age_months = intval($age_months);
    $vaccines = [];
    
    $query = "SELECT DISTINCT vaccine_name, disease_protected 
              FROM vaccine_schedule 
              WHERE recommended_age_months <= ? 
              ORDER BY vaccine_name";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $age_months);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $vaccines[] = $row;
    }
    
    return $vaccines;
}

// ===== EXISTING FUNCTIONS =====
function get_doctor_stats($conn, $doctor_id) {
    $stats = [
        'today_appointments' => 0,
        'pending_appointments' => 0,
        'total_patients' => 0,
        'vaccinations_given' => 0
    ];
    
    try {
        $today = date('Y-m-d');
        $doctor_id = intval($doctor_id);
        
        $query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status IN ('SCHEDULED', 'CONFIRMED', 'IN_PROGRESS')";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $doctor_id, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['today_appointments'] = $row['count'];
        }
        
        $query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'SCHEDULED'";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['pending_appointments'] = $row['count'];
        }
        
        $query = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_patients'] = $row['count'];
        }
        
        $query = "SELECT COUNT(*) as count FROM vaccination_records WHERE administered_by = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['vaccinations_given'] = $row['count'];
        }
        
    } catch (Exception $e) {
        error_log("Error getting doctor stats: " . $e->getMessage());
    }
    
    return $stats;
}

function get_appointment_chart_data($conn, $doctor_id) {
    $data = ['dates' => [], 'counts' => [], 'status_labels' => [], 'status_counts' => []];
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT DATE(appointment_date) as date, COUNT(*) as count 
                  FROM appointments 
                  WHERE doctor_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY DATE(appointment_date) 
                  ORDER BY date";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data['dates'][] = $row['date'];
            $data['counts'][] = (int)$row['count'];
        }
        
        $query = "SELECT status, COUNT(*) as count 
                  FROM appointments 
                  WHERE doctor_id = ? 
                  GROUP BY status";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data['status_labels'][] = $row['status'];
            $data['status_counts'][] = (int)$row['count'];
        }
        
    } catch (Exception $e) {
        error_log("Error getting chart data: " . $e->getMessage());
    }
    
    return $data;
}

function get_vaccination_chart_data($conn, $doctor_id) {
    $data = ['months' => [], 'vaccination_counts' => [], 'vaccine_names' => [], 'vaccine_counts' => []];
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT DATE_FORMAT(administration_date, '%Y-%m') as month, COUNT(*) as count 
                  FROM vaccination_records 
                  WHERE administered_by = ? AND administration_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(administration_date, '%Y-%m') 
                  ORDER BY month";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data['months'][] = $row['month'];
            $data['vaccination_counts'][] = (int)$row['count'];
        }
        
        $query = "SELECT vaccine_name, COUNT(*) as count 
                  FROM vaccination_records 
                  WHERE administered_by = ?
                  GROUP BY vaccine_name 
                  ORDER BY count DESC 
                  LIMIT 5";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data['vaccine_names'][] = $row['vaccine_name'];
            $data['vaccine_counts'][] = (int)$row['count'];
        }
        
    } catch (Exception $e) {
        error_log("Error getting vaccination chart data: " . $e->getMessage());
    }
    
    return $data;
}

function get_today_appointments($conn, $doctor_id) {
    $appointments = [];
    $today = date('Y-m-d');
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT a.*, p.first_name, p.last_name, p.date_of_birth, 
                         u.first_name as parent_first, u.last_name as parent_last,
                         p.gender, p.blood_type
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN users u ON p.parent_id = u.id
                  WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ? 
                  AND a.status IN ('SCHEDULED', 'CONFIRMED', 'IN_PROGRESS')
                  ORDER BY a.appointment_time ASC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $doctor_id, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $appointments[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting today's appointments: " . $e->getMessage());
    }
    
    return $appointments;
}

function get_recent_visits($conn, $doctor_id, $limit = 5) {
    $visits = [];
    $doctor_id = intval($doctor_id);
    $limit = intval($limit);
    
    try {
        $query = "SELECT a.*, p.first_name, p.last_name, p.date_of_birth, p.gender
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  WHERE a.doctor_id = ? AND a.status = 'COMPLETED'
                  ORDER BY a.appointment_date DESC, a.appointment_time DESC
                  LIMIT ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $doctor_id, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $visits[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting recent visits: " . $e->getMessage());
    }
    
    return $visits;
}

function get_pending_appointments($conn, $doctor_id) {
    $appointments = [];
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT a.*, p.first_name, p.last_name, p.date_of_birth, 
                         u.first_name as parent_first, u.last_name as parent_last,
                         p.gender, p.blood_type
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN users u ON p.parent_id = u.id
                  WHERE a.doctor_id = ? AND a.status = 'SCHEDULED'
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC
                  LIMIT 20";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $appointments[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting pending appointments: " . $e->getMessage());
    }
    
    return $appointments;
}

function get_doctor_patients($conn, $doctor_id) {
    $patients = [];
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT DISTINCT p.*, u.first_name as parent_first, u.last_name as parent_last,
                         u.phone as parent_phone, u.email as parent_email,
                         COUNT(a.id) as visit_count,
                         MAX(a.appointment_date) as last_visit
                  FROM patients p
                  JOIN appointments a ON p.id = a.patient_id
                  JOIN users u ON p.parent_id = u.id
                  WHERE a.doctor_id = ?
                  GROUP BY p.id
                  ORDER BY p.first_name, p.last_name";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $birth_date = new DateTime($row['date_of_birth']);
            $today = new DateTime();
            $age = $birth_date->diff($today);
            $row['age_years'] = $age->y;
            $row['age_months'] = $age->m;
            
            $patients[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting doctor patients: " . $e->getMessage());
    }
    
    return $patients;
}

function get_all_appointments($conn, $doctor_id, $filter = 'all') {
    $appointments = [];
    $doctor_id = intval($doctor_id);
    
    try {
        $base_query = "SELECT a.*, p.first_name, p.last_name, p.date_of_birth, p.gender,
                              u.first_name as parent_first, u.last_name as parent_last
                       FROM appointments a
                       JOIN patients p ON a.patient_id = p.id
                       JOIN users u ON p.parent_id = u.id
                       WHERE a.doctor_id = ?";
        
        $params = [$doctor_id];
        $types = "i";
        
        switch($filter) {
            case 'today':
                $base_query .= " AND DATE(a.appointment_date) = CURDATE()";
                break;
            case 'upcoming':
                $base_query .= " AND a.appointment_date >= CURDATE() AND a.status IN ('SCHEDULED', 'CONFIRMED')";
                break;
            case 'pending':
                $base_query .= " AND a.status = 'SCHEDULED'";
                break;
            case 'completed':
                $base_query .= " AND a.status = 'COMPLETED'";
                break;
        }
        
        $base_query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 50";
        
        $stmt = mysqli_prepare($conn, $base_query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $appointments[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting appointments: " . $e->getMessage());
    }
    
    return $appointments;
}

function get_recent_vaccinations($conn, $doctor_id, $limit = 10) {
    $vaccinations = [];
    $doctor_id = intval($doctor_id);
    $limit = intval($limit);
    
    try {
        $query = "SELECT vr.*, p.first_name, p.last_name, p.date_of_birth
                  FROM vaccination_records vr
                  JOIN patients p ON vr.patient_id = p.id
                  WHERE vr.administered_by = ?
                  ORDER BY vr.administration_date DESC, vr.created_at DESC
                  LIMIT ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $doctor_id, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $vaccinations[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting recent vaccinations: " . $e->getMessage());
    }
    
    return $vaccinations;
}

function get_vaccination_schedule($conn, $doctor_id) {
    $schedule = [];
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT p.id as patient_id, p.first_name, p.last_name, p.date_of_birth,
                         TIMESTAMPDIFF(MONTH, p.date_of_birth, CURDATE()) as current_age_months,
                         CASE 
                             WHEN TIMESTAMPDIFF(MONTH, p.date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Hepatitis B, BCG'
                             WHEN TIMESTAMPDIFF(MONTH, p.date_of_birth, CURDATE()) BETWEEN 2 AND 4 THEN 'DTaP, IPV, Hib, PCV, Rotavirus'
                             WHEN TIMESTAMPDIFF(MONTH, p.date_of_birth, CURDATE()) BETWEEN 12 AND 15 THEN 'MMR, Varicella, Hepatitis A'
                             ELSE 'Checkup needed'
                         END as recommended_vaccines,
                         CASE 
                             WHEN TIMESTAMPDIFF(MONTH, p.date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'DUE'
                             WHEN TIMESTAMPDIFF(MONTH, p.date_of_birth, CURDATE()) BETWEEN 2 AND 4 THEN 'DUE'
                             WHEN TIMESTAMPDIFF(MONTH, p.date_of_birth, CURDATE()) BETWEEN 12 AND 15 THEN 'DUE'
                             ELSE 'UPCOMING'
                         END as status
                  FROM patients p
                  INNER JOIN appointments a ON p.id = a.patient_id
                  WHERE a.doctor_id = ?
                  HAVING recommended_vaccines != 'Checkup needed'
                  ORDER BY p.date_of_birth ASC
                  LIMIT 10";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $schedule[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting vaccination schedule: " . $e->getMessage());
    }
    
    return $schedule;
}

function get_vaccines($conn) {
    $vaccines = [];
    
    try {
        $query = "SELECT name, disease_protected FROM vaccines ORDER BY name";
        $result = mysqli_query($conn, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $vaccines[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting vaccines: " . $e->getMessage());
    }
    
    return $vaccines;
}

function get_patient_medical_records($conn, $patient_id, $doctor_id) {
    $records = [];
    $patient_id = intval($patient_id);
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT mr.*, doc.first_name as doctor_first, doc.last_name as doctor_last
                  FROM medical_records mr
                  JOIN users doc ON mr.doctor_id = doc.id
                  WHERE mr.patient_id = ? AND mr.doctor_id = ?
                  ORDER BY mr.record_date DESC, mr.created_at DESC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $records[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting medical records: " . $e->getMessage());
    }
    
    return $records;
}

function update_patient_info($conn, $data, $doctor_id) {
    $patient_id = intval($data['patient_id']);
    $height = isset($data['height']) ? floatval($data['height']) : null;
    $weight = isset($data['weight']) ? floatval($data['weight']) : null;
    $allergies = mysqli_real_escape_string($conn, $data['allergies'] ?? '');
    $medical_conditions = mysqli_real_escape_string($conn, $data['medical_conditions'] ?? '');
    $special_notes = mysqli_real_escape_string($conn, $data['special_notes'] ?? '');
    
    $verify_query = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $patient_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($result) === 0) {
        return ['success' => false, 'message' => 'Patient not found or not authorized'];
    }
    
    $query = "UPDATE patients SET height = ?, weight = ?, allergies = ?, medical_conditions = ?, special_notes = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ddsssi", $height, $weight, $allergies, $medical_conditions, $special_notes, $patient_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Patient information updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update patient information'];
}

function generate_prescription_number($conn) {
    $prefix = 'RX';
    $year = date('Y');
    $query = "SELECT COUNT(*) as count FROM prescriptions WHERE prescription_number LIKE '{$prefix}{$year}%'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $sequence = $row['count'] + 1;
    return $prefix . $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

function print_prescription($conn, $prescription_id, $doctor_id) {
    $prescription_id = intval($prescription_id);
    
    try {
        $query = "SELECT p.*, pt.first_name, pt.last_name, pt.date_of_birth, 
                         u.first_name as doctor_first, u.last_name as doctor_last,
                         u.license_number, u.specialization
                  FROM prescriptions p
                  JOIN patients pt ON p.patient_id = pt.id
                  JOIN users u ON p.doctor_id = u.id
                  WHERE p.id = ? AND p.doctor_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $prescription = mysqli_fetch_assoc($result);
        
        if (!$prescription) {
            return ['success' => false, 'message' => 'Prescription not found'];
        }
        
        if (empty($prescription['prescription_number'])) {
            $prescription_number = generate_prescription_number($conn);
            $update_query = "UPDATE prescriptions SET prescription_number = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "si", $prescription_number, $prescription_id);
            mysqli_stmt_execute($update_stmt);
            $prescription['prescription_number'] = $prescription_number;
        }
        
        $print_query = "UPDATE prescriptions SET is_printed = 1, printed_at = NOW() WHERE id = ?";
        $print_stmt = mysqli_prepare($conn, $print_query);
        mysqli_stmt_bind_param($print_stmt, "i", $prescription_id);
        mysqli_stmt_execute($print_stmt);
        
        return [
            'success' => true, 
            'prescription' => $prescription,
            'message' => 'Prescription ready for printing'
        ];
        
    } catch (Exception $e) {
        error_log("Error printing prescription: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to print prescription'];
    }
}

function approveAppointment($conn, $data, $doctor_id) {
    $appointment_id = intval($data['appointment_id']);
    $query = "UPDATE appointments SET status = 'CONFIRMED', updated_at = NOW() WHERE id = ? AND doctor_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Appointment approved successfully'];
    }
    return ['success' => false, 'message' => 'Failed to approve appointment'];
}

function rejectAppointment($conn, $data, $doctor_id) {
    $appointment_id = intval($data['appointment_id']);
    $reason = mysqli_real_escape_string($conn, $data['reason'] ?? '');
    
    $query = "UPDATE appointments SET status = 'CANCELLED', notes = CONCAT(COALESCE(notes, ''), ' Rejection reason: ', ?), updated_at = NOW() WHERE id = ? AND doctor_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sii", $reason, $appointment_id, $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Appointment rejected successfully'];
    }
    return ['success' => false, 'message' => 'Failed to reject appointment'];
}

function completeAppointment($conn, $data, $doctor_id) {
    $appointment_id = intval($data['appointment_id']);
    $query = "UPDATE appointments SET status = 'COMPLETED', updated_at = NOW() WHERE id = ? AND doctor_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Appointment marked as completed'];
    }
    return ['success' => false, 'message' => 'Failed to complete appointment'];
}

function recordVaccination($conn, $data, $doctor_id) {
    $patient_id = intval($data['patient_id']);
    $vaccine_name = mysqli_real_escape_string($conn, $data['vaccine_name']);
    $dose_number = intval($data['dose_number']);
    $administration_date = $data['administration_date'];
    $lot_number = mysqli_real_escape_string($conn, $data['lot_number'] ?? '');
    $notes = mysqli_real_escape_string($conn, $data['notes'] ?? '');
    
    if (!strtotime($administration_date)) {
        return ['success' => false, 'message' => 'Invalid date format'];
    }
    
    $query = "INSERT INTO vaccination_records (patient_id, vaccine_name, dose_number, administration_date, lot_number, notes, administered_by, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isissis", $patient_id, $vaccine_name, $dose_number, $administration_date, $lot_number, $notes, $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Vaccination recorded successfully'];
    }
    return ['success' => false, 'message' => 'Failed to record vaccination'];
}

function setAvailability($conn, $data, $doctor_id) {
    $date = $data['date'];
    $start_time = $data['start_time'];
    $end_time = $data['end_time'];
    
    if (!strtotime($date) || !strtotime($start_time) || !strtotime($end_time)) {
        return ['success' => false, 'message' => 'Invalid date or time format'];
    }
    
    if (strtotime($start_time) >= strtotime($end_time)) {
        return ['success' => false, 'message' => 'End time must be after start time'];
    }
    
    $check_query = "SELECT id FROM doctor_availability WHERE doctor_id = ? AND date = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "is", $doctor_id, $date);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $query = "UPDATE doctor_availability SET start_time = ?, end_time = ?, updated_at = NOW() WHERE doctor_id = ? AND date = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssis", $start_time, $end_time, $doctor_id, $date);
    } else {
        $query = "INSERT INTO doctor_availability (doctor_id, date, start_time, end_time, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isss", $doctor_id, $date, $start_time, $end_time);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Availability set successfully'];
    }
    return ['success' => false, 'message' => 'Failed to set availability'];
}

function searchPatients($conn, $data, $doctor_id) {
    $search_term = '%' . mysqli_real_escape_string($conn, $data['search_term']) . '%';
    $query = "SELECT p.*, u.first_name as parent_first, u.last_name as parent_last
              FROM patients p
              JOIN users u ON p.parent_id = u.id
              WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)
              AND p.id IN (SELECT DISTINCT patient_id FROM appointments WHERE doctor_id = ?)
              LIMIT 10";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssi", $search_term, $search_term, $search_term, $search_term, $doctor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $patients = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $patients[] = $row;
    }
    return ['success' => true, 'patients' => $patients];
}

function updatePatientNotes($conn, $data, $doctor_id) {
    $patient_id = intval($data['patient_id']);
    $notes = mysqli_real_escape_string($conn, $data['notes']);
    
    $verify_query = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $patient_id, $doctor_id);
    mysqli_stmt_execute($verify_stmt);
    $result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($result) === 0) {
        return ['success' => false, 'message' => 'Patient not found'];
    }
    
    $query = "UPDATE patients SET special_notes = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $notes, $patient_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Patient notes updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update patient notes'];
}

function addPrescription($conn, $data, $doctor_id) {
    $patient_id = intval($data['patient_id']);
    $medication_name = mysqli_real_escape_string($conn, $data['medication_name']);
    $dosage = mysqli_real_escape_string($conn, $data['dosage']);
    $frequency = mysqli_real_escape_string($conn, $data['frequency']);
    $duration = mysqli_real_escape_string($conn, $data['duration']);
    $instructions = mysqli_real_escape_string($conn, $data['instructions'] ?? '');
    
    $query = "INSERT INTO prescriptions (patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iisssss", $patient_id, $doctor_id, $medication_name, $dosage, $frequency, $duration, $instructions);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Prescription added successfully'];
    }
    return ['success' => false, 'message' => 'Failed to add prescription'];
}

function addConsultationNotes($conn, $data, $doctor_id) {
    $patient_id = intval($data['patient_id']);
    $notes = mysqli_real_escape_string($conn, $data['notes']);
    $diagnosis = mysqli_real_escape_string($conn, $data['diagnosis'] ?? '');
    $treatment_plan = mysqli_real_escape_string($conn, $data['treatment_plan'] ?? '');
    
    $query = "INSERT INTO consultation_notes (patient_id, doctor_id, diagnosis, notes, treatment_plan, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iisss", $patient_id, $doctor_id, $diagnosis, $notes, $treatment_plan);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Consultation notes added successfully'];
    }
    return ['success' => false, 'message' => 'Failed to add consultation notes'];
}

function add_medical_record($conn, $data, $doctor_id) {
    $patient_id = intval($data['patient_id'] ?? 0);
    $record_type = trim((string)($data['record_type'] ?? ''));
    $diagnosis = trim((string)($data['diagnosis'] ?? ''));
    $symptoms = trim((string)($data['symptoms'] ?? ''));
    $treatment_plan = trim((string)($data['treatment_plan'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));
    $blood_pressure = trim((string)($data['blood_pressure'] ?? ''));

    // Numeric vitals: treat empty string as NULL, validate otherwise
    $numOrNull = function ($v, $min, $max) {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        $f = (float)$v;
        if ($f < $min || $f > $max) return null;
        return $f;
    };
    $temperature = $numOrNull($data['temperature'] ?? null, 30.0, 45.0);
    $heart_rate  = $numOrNull($data['heart_rate']  ?? null, 20, 300);
    $heart_rate  = $heart_rate !== null ? (int)$heart_rate : null;
    $height      = $numOrNull($data['height'] ?? null, 10.0, 250.0);
    $weight      = $numOrNull($data['weight'] ?? null, 0.5, 300.0);

    if ($patient_id <= 0 || $record_type === '') {
        return ['success' => false, 'message' => 'Patient and record type are required.'];
    }

    // Authorize: either existing appointment patient OR parent-owned patient OR free-range record
    $verify_query = "SELECT 1 FROM patients WHERE id = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "i", $patient_id);
    mysqli_stmt_execute($verify_stmt);
    $result = mysqli_stmt_get_result($verify_stmt);
    if (mysqli_num_rows($result) === 0) {
        return ['success' => false, 'message' => 'Patient not found.'];
    }

    $query = "INSERT INTO medical_records (patient_id, doctor_id, record_date, record_type,
              diagnosis, symptoms, temperature, blood_pressure, heart_rate,
              height, weight, treatment_plan, notes, created_at)
              VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)];
    }
    // Types: i i s s s  d s i  d d s s  = iisssdsiddss (12)
    mysqli_stmt_bind_param(
        $stmt, "iisssdsiddss",
        $patient_id, $doctor_id, $record_type, $diagnosis, $symptoms,
        $temperature, $blood_pressure, $heart_rate,
        $height, $weight, $treatment_plan, $notes
    );

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Medical record added successfully'];
    }
    return ['success' => false, 'message' => 'Failed to add medical record: ' . mysqli_stmt_error($stmt)];
}

function updateDoctorProfile($conn, $data, $doctor_id) {
    $first_name = trim((string)($data['first_name'] ?? ''));
    $last_name  = trim((string)($data['last_name']  ?? ''));
    $email      = trim((string)($data['email']      ?? ''));
    $phone      = trim((string)($data['phone']      ?? ''));
    $specialization = trim((string)($data['specialization'] ?? ''));
    $license_number = trim((string)($data['license_number'] ?? ''));
    $years_experience = isset($data['years_of_experience']) && $data['years_of_experience'] !== ''
        ? intval($data['years_of_experience']) : null;
    $address = trim((string)($data['address'] ?? ''));
    $dob = trim((string)($data['date_of_birth'] ?? ''));
    $gender = trim((string)($data['gender'] ?? ''));

    if ($first_name === '' || $last_name === '' || $email === '' || $phone === '') {
        return ['success' => false, 'message' => 'First name, last name, email and phone are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        return ['success' => false, 'message' => 'Please enter a valid phone number.'];
    }
    if ($years_experience !== null && ($years_experience < 0 || $years_experience > 80)) {
        return ['success' => false, 'message' => 'Years of experience must be between 0 and 80.'];
    }
    if ($dob !== '' && strtotime($dob) > time()) {
        return ['success' => false, 'message' => 'Date of birth cannot be in the future.'];
    }

    // Email uniqueness across other users
    $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    mysqli_stmt_bind_param($chk, "si", $email, $doctor_id);
    mysqli_stmt_execute($chk);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk))) {
        return ['success' => false, 'message' => 'That email is already used by another account.'];
    }

    $dob_value = $dob !== '' ? $dob : null;
    $gender_value = $gender !== '' ? $gender : null;

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users
         SET first_name = ?, last_name = ?, email = ?, phone = ?,
             specialization = ?, license_number = ?, years_of_experience = ?,
             date_of_birth = ?, gender = ?, address = ?
         WHERE id = ?"
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)];
    }
    // Types: s s s s s s i s s s i = 11 chars for 11 params
    mysqli_stmt_bind_param(
        $stmt, "ssssssisssi",
        $first_name, $last_name, $email, $phone,
        $specialization, $license_number, $years_experience,
        $dob_value, $gender_value, $address, $doctor_id
    );
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Profile updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update profile: ' . mysqli_stmt_error($stmt)];
}

function changeDoctorPassword($conn, $data, $doctor_id) {
    $current = (string)($data['current_password'] ?? '');
    $new     = (string)($data['new_password']     ?? '');
    $confirm = (string)($data['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        return ['success' => false, 'message' => 'All password fields are required.'];
    }
    if (strlen($new) < 8) {
        return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
    }
    if ($new !== $confirm) {
        return ['success' => false, 'message' => 'New password and confirmation do not match.'];
    }

    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$row) {
        return ['success' => false, 'message' => 'User not found.'];
    }
    $stored = (string)$row['password'];
    $ok = password_verify($current, $stored)
        || hash_equals($stored, md5($current))
        || hash_equals($stored, $current);
    if (!$ok) {
        return ['success' => false, 'message' => 'Current password is incorrect.'];
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "si", $hash, $doctor_id);
    if (mysqli_stmt_execute($upd)) {
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    return ['success' => false, 'message' => 'Failed to change password: ' . mysqli_stmt_error($upd)];
}

function send_message_to_parent($conn, $data, $doctor_id) {
    $patient_id = intval($data['patient_id']);
    $message = mysqli_real_escape_string($conn, $data['message']);
    $subject = mysqli_real_escape_string($conn, $data['subject'] ?? 'Message from Doctor');
    
    $query = "SELECT u.email, u.first_name as parent_first, u.last_name as parent_last,
                     p.first_name as patient_first, p.last_name as patient_last
              FROM patients p
              JOIN users u ON p.parent_id = u.id
              WHERE p.id = ? AND p.id IN (SELECT DISTINCT patient_id FROM appointments WHERE doctor_id = ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $parent = mysqli_fetch_assoc($result);
    
    if (!$parent) {
        return ['success' => false, 'message' => 'Parent not found'];
    }
    
    $email = $parent['email'];
    error_log("Would send email to: $email, Subject: $subject, Message: $message");
    
    return ['success' => true, 'message' => 'Message sent successfully'];
}

function get_doctor_availability($conn, $doctor_id) {
    $availability = [];
    $doctor_id = intval($doctor_id);

    try {
        // build a 30-day (or configurable) window from today to show upcoming availability
        $start = new DateTime();
        $end = (new DateTime())->modify('+30 days');

        // get clinic hours
        $bh_query = "SELECT setting_value FROM clinic_settings WHERE setting_key = 'business_hours' LIMIT 1";
        $bh_res = mysqli_query($conn, $bh_query);
        $business_hours = [];
        if ($bh_res && ($row = mysqli_fetch_assoc($bh_res))) {
            $decoded = json_decode($row['setting_value'], true);
            if (is_array($decoded)) $business_hours = $decoded;
        }

        // get holidays in window
        $first_day = $start->format('Y-m-d');
        $last_day = $end->format('Y-m-d');
        $holidays = [];
        $hol_query = "SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ?";
        $hol_stmt = mysqli_prepare($conn, $hol_query);
        mysqli_stmt_bind_param($hol_stmt, "ss", $first_day, $last_day);
        mysqli_stmt_execute($hol_stmt);
        $hol_res = mysqli_stmt_get_result($hol_stmt);
        while ($h = mysqli_fetch_assoc($hol_res)) {
            $holidays[$h['holiday_date']] = $h['name'];
        }

        // get doctor's explicit availability/unavailability
        $avail_query = "SELECT date, start_time, end_time, availability_type, reason, is_all_day 
                       FROM doctor_availability 
                       WHERE doctor_id = ? 
                       AND date BETWEEN ? AND ?";
        $avail_stmt = mysqli_prepare($conn, $avail_query);
        mysqli_stmt_bind_param($avail_stmt, "iss", $doctor_id, $first_day, $last_day);
        mysqli_stmt_execute($avail_stmt);
        $avail_res = mysqli_stmt_get_result($avail_stmt);

        $doctor_avail_map = [];
        while ($a = mysqli_fetch_assoc($avail_res)) {
            $doctor_avail_map[$a['date']][] = $a;
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));

        foreach ($period as $dt) {
            $date = $dt->format('Y-m-d');
            $dayLower = strtolower($dt->format('l'));
            $clinic_open = null;
            $clinic_close = null;
            if (!empty($business_hours[$dayLower]) && isset($business_hours[$dayLower]['open'])) {
                $clinic_open = $business_hours[$dayLower]['open'];
                $clinic_close = $business_hours[$dayLower]['close'];
            }

            if (empty($clinic_open)) continue; // clinic closed this weekday

            if (isset($holidays[$date])) {
                // treat as unavailable
                $availability[] = [
                    'date' => $date,
                    'available' => false,
                    'reason' => 'Holiday: ' . $holidays[$date],
                    'is_all_day' => 1
                ];
                continue;
            }

            // default available for clinic hours
            $slot = [
                'date' => $date,
                'available' => true,
                'start_time' => $clinic_open,
                'end_time' => $clinic_close,
                'source' => 'clinic_hours'
            ];

            if (isset($doctor_avail_map[$date])) {
                $entries = $doctor_avail_map[$date];
                // UNAVAILABLE entries take precedence unless doctor explicitly marked AVAILABLE during clinic open hours
                $isUnavailable = false;
                $avail_override = null;
                foreach ($entries as $e) {
                    if ($e['availability_type'] === 'UNAVAILABLE') {
                        $isUnavailable = true;
                        $reason = $e['reason'] ?? 'Not available';
                    } elseif ($e['availability_type'] === 'AVAILABLE') {
                        $avail_override = $e;
                    }
                }

                if ($isUnavailable && is_null($avail_override)) {
                    $availability[] = [
                        'date' => $date,
                        'available' => false,
                        'reason' => $reason,
                        'is_all_day' => 1
                    ];
                    continue;
                } elseif (!is_null($avail_override)) {
                    if ($avail_override['is_all_day']) {
                        $availability[] = [
                            'date' => $date,
                            'available' => true,
                            'start_time' => $clinic_open,
                            'end_time' => $clinic_close,
                            'source' => 'doctor_available_all_day'
                        ];
                        continue;
                    } else {
                        $availability[] = [
                            'date' => $date,
                            'available' => true,
                            'start_time' => ($avail_override['start_time'] ?: $clinic_open),
                            'end_time' => ($avail_override['end_time'] ?: $clinic_close),
                            'source' => 'doctor_available_custom'
                        ];
                        continue;
                    }
                }
            }

            // default clinic slot
            $availability[] = array_merge($slot, ['available' => true]);
        }

    } catch (Exception $e) {
        error_log("Error getting doctor availability in dashboard: " . $e->getMessage());
    }

    return $availability;
}

function cancel_availability($conn, $data, $doctor_id) {
    $availability_id = intval($data['availability_id']);
    $doctor_id = intval($doctor_id);
    
    $query = "DELETE FROM doctor_availability WHERE id = ? AND doctor_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $availability_id, $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Availability slot cancelled successfully'];
    }
    return ['success' => false, 'message' => 'Failed to cancel availability slot'];
}

function export_patient_data($conn, $patient_id, $doctor_id, $format = 'csv') {
    $patient_id = intval($patient_id);
    $doctor_id = intval($doctor_id);
    
    try {
        $verify_query = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
        $verify_stmt = mysqli_prepare($conn, $verify_query);
        mysqli_stmt_bind_param($verify_stmt, "ii", $patient_id, $doctor_id);
        mysqli_stmt_execute($verify_stmt);
        $result = mysqli_stmt_get_result($verify_stmt);
        
        if (mysqli_num_rows($result) === 0) {
            return ['success' => false, 'message' => 'Patient not found or not authorized'];
        }
        
        $export_data = [
            'patient' => get_patient_details($conn, $patient_id, $doctor_id),
            'appointments' => get_all_appointments($conn, $doctor_id),
            'prescriptions' => get_patient_prescriptions($conn, $patient_id, $doctor_id),
            'consultation_notes' => get_patient_consultation_notes($conn, $patient_id, $doctor_id),
            'medical_records' => get_patient_medical_records($conn, $patient_id, $doctor_id)
        ];
        
        return [
            'success' => true, 
            'message' => 'Export data prepared',
            'data' => $export_data
        ];
        
    } catch (Exception $e) {
        error_log("Error exporting patient data: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to export patient data'];
    }
}

function get_appointment_details($conn, $appointment_id, $doctor_id) {
    $appointment_id = intval($appointment_id);
    $doctor_id = intval($doctor_id);
    
    try {
        $query = "SELECT a.*, 
                         p.first_name, p.last_name, p.date_of_birth, p.gender, p.blood_type,
                         p.height, p.weight, p.allergies, p.medical_conditions, p.special_notes,
                         u.first_name as parent_first, u.last_name as parent_last,
                         u.phone as parent_phone, u.email as parent_email
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN users u ON p.parent_id = u.id
                  WHERE a.id = ? AND a.doctor_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
                $appointment = mysqli_fetch_assoc($result);
        
        if ($appointment) {
            $birth_date = new DateTime($appointment['date_of_birth']);
            $today = new DateTime();
            $age = $birth_date->diff($today);
            $appointment['age_years'] = $age->y;
            $appointment['age_months'] = $age->m;
        }
        
        return $appointment;
        
    } catch (Exception $e) {
        error_log("Error getting appointment details: " . $e->getMessage());
        return null;
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'SCHEDULED': return 'bg-orange-100 text-orange-800 border border-orange-200';
        case 'CONFIRMED': return 'bg-green-100 text-green-800 border border-green-200';
        case 'IN_PROGRESS': return 'bg-blue-100 text-blue-800 border border-blue-200';
        case 'COMPLETED': return 'bg-purple-100 text-purple-800 border border-purple-200';
        case 'CANCELLED': return 'bg-red-100 text-red-800 border border-red-200';
        default: return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
}

function formatAppointmentTime($time) {
    return date('g:i A', strtotime($time));
}

function calculateAge($birth_date) {
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $birth->diff($today);
    
    if ($age->y > 0) {
        return $age->y . ' years';
    } else {
        return $age->m . ' months';
    }
}

// Initialize data
$stats = get_doctor_stats($conn, $current_user['id']);
$appointment_chart_data = get_appointment_chart_data($conn, $current_user['id']);
$vaccination_chart_data = get_vaccination_chart_data($conn, $current_user['id']);
$today_appointments = get_today_appointments($conn, $current_user['id']);
$recent_visits = get_recent_visits($conn, $current_user['id']);
$pending_appointments = get_pending_appointments($conn, $current_user['id']);
$doctor_patients = get_doctor_patients($conn, $current_user['id']);
$recent_vaccinations = get_recent_vaccinations($conn, $current_user['id']);
$vaccination_schedule = get_vaccination_schedule($conn, $current_user['id']);
$vaccines = get_vaccines($conn);

// Handle form submissions with enhanced security
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response = ['success' => false, 'message' => 'Security token invalid'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            switch ($action) {
                case 'approve_appointment':
                    $response = approveAppointment($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'reject_appointment':
                    $response = rejectAppointment($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'complete_appointment':
                    $response = completeAppointment($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'record_vaccination':
                    $response = recordVaccination($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'set_availability':
                    $response = setAvailability($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'search_patients':
                    $response = searchPatients($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'update_patient_notes':
                    $response = updatePatientNotes($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'add_prescription':
                    $response = addPrescription($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'add_consultation_notes':
                    $response = addConsultationNotes($conn, $_POST, $current_user['id']);
                    break;

                case 'update_doctor_profile':
                    $response = updateDoctorProfile($conn, $_POST, $current_user['id']);
                    break;

                case 'change_doctor_password':
                    $response = changeDoctorPassword($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'get_patient_details':
                    $patient_id = intval($_POST['patient_id']);
                    $patient = get_patient_details($conn, $patient_id, $current_user['id']);
                    if ($patient) {
                        $response = ['success' => true, 'patient' => $patient];
                    } else {
                        $response = ['success' => false, 'message' => 'Patient not found'];
                    }
                    break;

                case 'get_patient_prescriptions':
                    $patient_id = intval($_POST['patient_id']);
                    $prescriptions = get_patient_prescriptions($conn, $patient_id, $current_user['id']);
                    $response = ['success' => true, 'prescriptions' => $prescriptions];
                    break;

                case 'get_consultation_notes':
                    $patient_id = intval($_POST['patient_id']);
                    $notes = get_patient_consultation_notes($conn, $patient_id, $current_user['id']);
                    $response = ['success' => true, 'notes' => $notes];
                    break;

                case 'update_patient_info':
                    $response = update_patient_info($conn, $_POST, $current_user['id']);
                    break;

                case 'print_prescription':
                    $prescription_id = intval($_POST['prescription_id']);
                    $response = print_prescription($conn, $prescription_id, $current_user['id']);
                    break;
                    
                case 'add_medical_record':
                    $response = add_medical_record($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'get_medical_records':
                    $patient_id = intval($_POST['patient_id']);
                    $records = get_patient_medical_records($conn, $patient_id, $current_user['id']);
                    $response = ['success' => true, 'records' => $records];
                    break;
                    
                case 'send_message':
                    $response = send_message_to_parent($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'get_availability':
                    $availability = get_doctor_availability($conn, $current_user['id']);
                    $response = ['success' => true, 'availability' => $availability];
                    break;
                    
                case 'cancel_availability':
                    $response = cancel_availability($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'get_appointment_details':
                    $appointment_id = intval($_POST['appointment_id']);
                    $appointment = get_appointment_details($conn, $appointment_id, $current_user['id']);
                    if ($appointment) {
                        $response = ['success' => true, 'appointment' => $appointment];
                    } else {
                        $response = ['success' => false, 'message' => 'Appointment not found'];
                    }
                    break;
                    
                case 'export_patient_data':
                    $patient_id = intval($_POST['patient_id']);
                    $response = export_patient_data($conn, $patient_id, $current_user['id'], $_POST['format'] ?? 'csv');
                    break;
                
                case 'get_vaccine_needs':
                    $patient_id = intval($_POST['patient_id']);
                    $response = get_patient_vaccine_needs($conn, $patient_id, $current_user['id']);
                    break;

                case 'set_vaccine_need':
                    $response = set_patient_vaccine_need($conn, $_POST, $current_user['id']);
                    break;
                    
                case 'delete_vaccine_need':
                    $vaccine_need_id = intval($_POST['vaccine_need_id']);
                    $response = delete_patient_vaccine_need($conn, $vaccine_need_id, $current_user['id']);
                    break;
                    
                case 'get_recommended_vaccines':
                    $patient_id = intval($_POST['patient_id']);
                    $patient_query = "SELECT date_of_birth FROM patients WHERE id = ?";
                    $patient_stmt = mysqli_prepare($conn, $patient_query);
                    mysqli_stmt_bind_param($patient_stmt, "i", $patient_id);
                    mysqli_stmt_execute($patient_stmt);
                    $patient_result = mysqli_stmt_get_result($patient_stmt);
                    
                    if (mysqli_num_rows($patient_result) === 0) {
                        $response = ['success' => false, 'message' => 'Patient not found'];
                    } else {
                        $patient = mysqli_fetch_assoc($patient_result);
                        $birth_date = new DateTime($patient['date_of_birth']);
                        $today = new DateTime();
                        $interval = $birth_date->diff($today);
                        $age_months = ($interval->y * 12) + $interval->m;
                        
                        $vaccines_rec = get_recommended_vaccines_for_age($conn, $age_months);
                        $response = ['success' => true, 'data' => $vaccines_rec, 'age_months' => $age_months];
                    }
                    break;

                case 'get_patient_vaccination_records':
                    $patient_id = intval($_POST['patient_id']);
                    $response = get_patient_vaccination_records($conn, $patient_id, $current_user['id']);
                    break;

                case 'get_vaccination_record':
                    $vaccination_id = intval($_POST['vaccination_id']);
                    $record = get_vaccination_record($conn, $vaccination_id, $current_user['id']);
                    if ($record) {
                        $response = ['success' => true, 'record' => $record];
                    } else {
                        $response = ['success' => false, 'message' => 'Vaccination record not found'];
                    }
                    break;

                case 'edit_vaccination_record':
                    $vaccination_id = intval($_POST['vaccination_id']);
                    $response = edit_vaccination_record($conn, $vaccination_id, $_POST, $current_user['id']);
                    break;

                case 'delete_vaccination_record':
                    $vaccination_id = intval($_POST['vaccination_id']);
                    $response = delete_vaccination_record($conn, $vaccination_id, $current_user['id']);
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Form submission error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - AlagApp Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-pink: #FF6B9A;
            --light-pink: #FFBCD9;
            --dark-text: #333333;
            --light-gray: #F6F6F8;
        }
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #fef5f8 100%);
            min-height: 100vh;
        }
        
        .font-inter { font-family: 'Inter', sans-serif; }
        
        .text-primary { color: var(--primary-pink); }
        .bg-primary { background-color: var(--primary-pink); }
        .bg-light-pink { background-color: var(--light-pink); }
        
        .sidebar {
            background: linear-gradient(180deg, #FF6B9A 0%, #FFBCD9 100%);
            min-height: 100vh;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink), var(--light-pink));
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 154, 0.4);
        }
        
        .loading-spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #FF6B9A;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success { background: #10b981; }
        .notification.error { background: #ef4444; }
        .notification.info { background: #3b82f6; }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        
        .nav-item:hover, .nav-item.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .modal-backdrop {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .patient-item.selected {
            background-color: rgba(255, 107, 154, 0.1);
            border-color: #FF6B9A;
        }
    </style>
    <script src="assets/toast.js"></script>
</head>
<body class="flex">
    <!-- Notification System -->
    <div id="notification" class="notification hidden"></div>

    <!-- Sidebar -->
    <div class="sidebar w-64 text-white">
        <div class="p-6">
            <h1 class="text-2xl font-inter font-bold mb-8 flex items-center">
                <i class="fas fa-stethoscope mr-2"></i>
                AlagApp
            </h1>
            
            <div class="mb-8">
                <div class="flex items-center space-x-3 mb-4 p-3 bg-white/10 rounded-lg">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-md text-white"></i>
                    </div>
                    <div>
                        <div class="font-semibold" id="doctorName">
                            Dr. <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                        </div>
                        <div class="text-sm text-white/80" id="doctorSpecialty">
                            <?php echo htmlspecialchars($current_user['specialization'] ?? 'Pediatrician'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <nav class="space-y-1">
                <a href="#dashboard" data-section="dashboard" class="nav-item active">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="#patients" data-section="patients" class="nav-item">
                    <i class="fas fa-user-injured mr-3"></i>
                    <span>Patients</span>
                </a>
                
                <a href="#appointments" data-section="appointments" class="nav-item">
                    <i class="fas fa-calendar-check mr-3"></i>
                    <span>Appointments</span>
                </a>
                
                <a href="#vaccinations" data-section="vaccinations" class="nav-item">
                    <i class="fas fa-syringe mr-3"></i>
                    <span>Vaccinations</span>
                </a>

                <a href="#profile" data-section="profile" class="nav-item">
                    <i class="fas fa-user-cog mr-3"></i>
                    <span>My Profile</span>
                </a>
            </nav>

            <div class="mt-8 pt-8 border-t border-white/20">
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="flex-1 main-content overflow-auto">
        <!-- Dashboard Section -->
        <div id="dashboard-section" class="section-content p-6 fade-in">
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Welcome, Dr. <?php echo htmlspecialchars($current_user['first_name']); ?>!</h1>
                        <p class="text-gray-600">Here's your overview for <?php echo date('l, F j, Y'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-blue-100 mr-4">
                            <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Today's Appointments</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['today_appointments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-orange-100 mr-4">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Pending Approval</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_appointments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-green-100 mr-4">
                            <i class="fas fa-users text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Patients</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_patients']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-purple-100 mr-4">
                            <i class="fas fa-syringe text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Vaccinations Given</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['vaccinations_given']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="card card-hover p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Appointments Overview</h3>
                    <div class="h-64">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>
                
                <div class="card card-hover p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Vaccination Statistics</h3>
                    <div class="h-64">
                        <canvas id="vaccinationsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Today's Appointments -->
                <div class="card card-hover p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-inter font-semibold text-gray-800">Today's Appointments</h3>
                        <span class="text-sm text-gray-500"><?php echo date('M j, Y'); ?></span>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($today_appointments)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar-times text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">No appointments scheduled for today</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($today_appointments as $appointment): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo formatAppointmentTime($appointment['appointment_time']); ?> • 
                                                <?php echo htmlspecialchars($appointment['type'] ?? 'Checkup'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 text-xs font-medium rounded-full <?php echo getStatusBadge($appointment['status']); ?>">
                                        <?php echo htmlspecialchars($appointment['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Patient Visits -->
                <div class="card card-hover p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Recent Patient Visits</h3>
                    <div class="space-y-4">
                        <?php if (empty($recent_visits)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-user-clock text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">No recent patient visits</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_visits as $visit): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-check text-green-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="far fa-calendar mr-1"></i>
                                                <?php echo date('M j, Y', strtotime($visit['appointment_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-800">
                                            <?php echo htmlspecialchars($visit['type'] ?? 'Checkup'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">Completed</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patients Section -->
        <div id="patients-section" class="section-content p-6 hidden">
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Patient Records</h1>
                        <p class="text-gray-600">Access and manage patient information</p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="card card-hover p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Patient List</h3>
                        <div id="patientList" class="space-y-4">
                            <?php if (empty($doctor_patients)): ?>
                                <p class="text-gray-500">No patients found</p>
                            <?php else: ?>
                                <?php foreach ($doctor_patients as $patient): ?>
                                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer patient-item" 
                                         onclick="selectPatient(<?php echo $patient['id']; ?>, this)" 
                                         data-patient-id="<?php echo $patient['id']; ?>">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mr-4">
                                                    <i class="fas fa-user text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-gray-800">
                                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-600">
                                                        DOB: <?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?>
                                                        (<?php echo $patient['age_years']; ?> years <?php echo $patient['age_months']; ?> months)
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm text-gray-600">Visits: <?php echo $patient['visit_count']; ?></div>
                                                <div class="text-xs text-gray-500">
                                                    Last: <?php echo $patient['last_visit'] ? date('M j, Y', strtotime($patient['last_visit'])) : 'Never'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <!-- Patient Summary -->
                    <div class="card card-hover p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Patient Summary</h3>
                        <div id="patientSummary" class="text-center text-gray-500">
                            Select a patient to view details
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card card-hover p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <button onclick="openVaccinationModal()" class="w-full text-left px-4 py-3 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors">
                                <i class="fas fa-syringe mr-2"></i>Record Vaccination
                            </button>
                            <button onclick="openMedicalRecordModal()" class="w-full text-left px-4 py-3 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 transition-colors">
                                <i class="fas fa-file-medical mr-2"></i>Add Medical Record
                            </button>
                            <button onclick="openConsultationModal()" class="w-full text-left px-4 py-3 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors">
                                <i class="fas fa-notes-medical mr-2"></i>Add Consultation Notes
                            </button>
                            <button onclick="openPrescriptionModal()" class="w-full text-left px-4 py-3 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition-colors">
                                <i class="fas fa-prescription mr-2"></i>Write Prescription
                            </button>
                            <button onclick="openVaccineNeedModal()" class="w-full text-left px-4 py-3 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition-colors">
                                <i class="fas fa-vial mr-2"></i>Manage Vaccine Needs
                            </button>
                        </div>
                    </div>

                    <!-- Vaccine Needs Display -->
                    <div class="card card-hover p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Vaccine Needs</h3>
                        <div id="patientVaccineNeedsList" class="space-y-3">
                            <p class="text-gray-500 text-center py-4">Select a patient to view vaccine needs</p>
                        </div>
                    </div>

                    <!-- Vaccination History -->
                    <div class="card card-hover p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Vaccination History</h3>
                        <div id="patientVaccinationHistory" class="space-y-3">
                            <p class="text-gray-500 text-center py-4">Select a patient to view vaccination history</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointments Section -->
        <div id="appointments-section" class="section-content p-6 hidden">
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Appointments</h1>
                        <p class="text-gray-600">Manage all patient appointments</p>
                    </div>
                    <select id="appointmentFilter" onchange="filterAppointments()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="all">All Appointments</option>
                        <option value="today">Today</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>

            <div class="card card-hover p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4">Patient</th>
                                <th class="text-left py-3 px-4">Date & Time</th>
                                <th class="text-left py-3 px-4">Type</th>
                                <th class="text-left py-3 px-4">Status</th>
                                <th class="text-left py-3 px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentsTable">
                            <?php 
                            $all_appointments = get_all_appointments($conn, $current_user['id']);
                            if (empty($all_appointments)): ?>
                                <tr>
                                    <td colspan="5" class="py-4 px-4 text-center text-gray-500">No appointments found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_appointments as $appointment): ?>
                                    <tr class="border-b border-gray-100">
                                        <td class="py-3 px-4">
                                            <div class="font-medium"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-medium"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                            <div class="text-sm text-gray-600"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></div>
                                        </td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($appointment['type'] ?? 'Checkup'); ?></td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getStatusBadge($appointment['status']); ?>">
                                                <?php echo htmlspecialchars($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-2">
                                                <?php if ($appointment['status'] === 'SCHEDULED'): ?>
                                                    <button onclick="approveAppointment(<?php echo $appointment['id']; ?>)" class="text-green-600 hover:text-green-800 text-sm font-medium">
                                                        Approve
                                                    </button>
                                                    <button onclick="rejectAppointment(<?php echo $appointment['id']; ?>)" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                                        Reject
                                                    </button>
                                                <?php elseif ($appointment['status'] === 'CONFIRMED'): ?>
                                                    <button onclick="completeAppointment(<?php echo $appointment['id']; ?>)" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                        Complete
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="viewAppointmentDetails(<?php echo $appointment['id']; ?>)" class="text-primary hover:text-primary-dark text-sm font-medium">
                                                    View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Vaccinations Section -->
        <div id="vaccinations-section" class="section-content p-6 hidden">
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Vaccinations</h1>
                        <p class="text-gray-600">Manage vaccination records and schedules</p>
                    </div>
                    <button onclick="openVaccinationModal()" class="btn-primary text-white px-6 py-3 rounded-lg font-semibold">
                        Record Vaccination
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="card card-hover p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Recent Vaccinations</h3>
                    <div class="space-y-4" data-recent-vaccinations>
                        <?php if (empty($recent_vaccinations)): ?>
                            <p class="text-gray-500">No recent vaccinations recorded</p>
                        <?php else: ?>
                            <?php foreach ($recent_vaccinations as $vaccination): ?>
                                <div class="p-4 border border-gray-200 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <div class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($vaccination['first_name'] . ' ' . $vaccination['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600"><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></div>
                                        </div>
                                        <span class="text-xs text-gray-500">
                                            <?php echo date('M j, Y', strtotime($vaccination['administration_date'])); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Dose: <?php echo htmlspecialchars($vaccination['dose_number']); ?>
                                        <?php if (!empty($vaccination['lot_number'])): ?>
                                            | Lot: <?php echo htmlspecialchars($vaccination['lot_number']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card card-hover p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Vaccination Schedule</h3>
                    <div id="vaccinationSchedule" class="space-y-4">
                        <?php if (empty($vaccination_schedule)): ?>
                            <p class="text-gray-500">No upcoming vaccinations scheduled</p>
                        <?php else: ?>
                            <?php foreach ($vaccination_schedule as $schedule): ?>
                                <div class="p-4 border border-gray-200 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <div class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600"><?php echo htmlspecialchars($schedule['recommended_vaccines']); ?></div>
                                        </div>
                                        <span class="text-xs font-medium px-2 py-1 rounded-full
                                            <?php echo $schedule['status'] === 'DUE' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $schedule['status']; ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Age: <?php echo $schedule['current_age_months']; ?> months
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile-section" class="section-content p-6 hidden">
            <div class="mb-8">
                <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">My Profile</h1>
                <p class="text-gray-600">Manage your credentials and account security.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="card card-hover p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Profile Information</h3>
                    <form id="doctorProfileForm" onsubmit="handleDoctorProfileForm(event)" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_doctor_profile">

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" required minlength="1" maxlength="100"
                                       value="<?php echo htmlspecialchars($current_user['first_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" required minlength="1" maxlength="100"
                                       value="<?php echo htmlspecialchars($current_user['last_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                                <input type="email" name="email" required maxlength="150"
                                       value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" required pattern="[0-9+\-\s()]{7,20}" maxlength="20"
                                       value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Specialization</label>
                                <input type="text" name="specialization" maxlength="120"
                                       value="<?php echo htmlspecialchars($current_user['specialization'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">License Number</label>
                                <input type="text" name="license_number" maxlength="60"
                                       value="<?php echo htmlspecialchars($current_user['license_number'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Years of Experience</label>
                                <input type="number" name="years_of_experience" min="0" max="80" step="1"
                                       value="<?php echo htmlspecialchars((string)($current_user['years_of_experience'] ?? '')); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                                <input type="date" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo htmlspecialchars((string)($current_user['date_of_birth'] ?? '')); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                <?php $cur_g = strtoupper((string)($current_user['gender'] ?? '')); ?>
                                <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Prefer not to say</option>
                                    <option value="MALE" <?php if ($cur_g === 'MALE') echo 'selected'; ?>>Male</option>
                                    <option value="FEMALE" <?php if ($cur_g === 'FEMALE') echo 'selected'; ?>>Female</option>
                                    <option value="OTHER" <?php if ($cur_g === 'OTHER') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <input type="text" name="address" maxlength="255"
                                       value="<?php echo htmlspecialchars($current_user['address'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary text-white px-6 py-3 rounded-lg font-semibold">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card card-hover p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Change Password</h3>
                    <form id="doctorPasswordForm" onsubmit="handleDoctorPasswordForm(event)" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="change_doctor_password">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password <span class="text-red-500">*</span></label>
                            <input type="password" name="current_password" required minlength="1" autocomplete="current-password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password <span class="text-red-500">*</span></label>
                            <input type="password" name="new_password" required minlength="8" autocomplete="new-password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password <span class="text-red-500">*</span></label>
                            <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <p class="text-xs text-gray-500">Password must be at least 8 characters.</p>
                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary text-white px-6 py-3 rounded-lg font-semibold">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Vaccine Needs Modal -->
    <div id="vaccineNeedModal" class="modal-container hidden">
        <div class="modal-backdrop fixed inset-0 z-40" onclick="closeModal('vaccineNeedModal')"></div>
        <div class="modal-content relative z-50">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Manage Vaccine Needs</h3>
                        <button onclick="closeModal('vaccineNeedModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left: Add/Edit Form -->
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-4">Add/Edit Vaccine Need</h4>
                            <form id="vaccineNeedForm" onsubmit="handleVaccineNeedForm(event)" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="set_vaccine_need">
                                <input type="hidden" id="vaccine_need_patient_id" name="patient_id">
                                <input type="hidden" id="vaccine_need_id" name="id">

                                <div>
                                    <label for="vaccine_patient_display" class="block text-sm font-medium text-gray-700 mb-2">Patient</label>
                                    <div id="vaccine_patient_display" class="px-3 py-2 bg-gray-100 rounded-lg text-gray-700 text-sm">
                                        Select a patient first
                                    </div>
                                </div>

                                <div>
                                    <label for="vaccine_age_display" class="block text-sm font-medium text-gray-700 mb-2">Patient Age</label>
                                    <div id="vaccine_age_display" class="px-3 py-2 bg-gray-100 rounded-lg text-gray-700 text-sm">
                                        —
                                    </div>
                                </div>

                                <div>
                                    <label for="vaccine_name_input" class="block text-sm font-medium text-gray-700 mb-2">
                                        Vaccine Name <span class="text-red-500">*</span>
                                    </label>
                                    <select id="vaccine_name_input" name="vaccine_name" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">-- Select Vaccine --</option>
                                        <?php foreach ($vaccines as $vaccine): ?>
                                            <option value="<?php echo htmlspecialchars($vaccine['name']); ?>">
                                                <?php echo htmlspecialchars($vaccine['name']); ?> (<?php echo htmlspecialchars($vaccine['disease_protected']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="recommendedVaccinesHint" class="mt-2 p-3 bg-blue-50 rounded-lg">
                                        <p class="text-sm text-blue-800"><strong>Age-appropriate vaccines:</strong></p>
                                        <div id="recommendedVaccinesList" class="text-sm text-blue-700 mt-2">
                                            Load patient to see recommendations
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="recommended_date_input" class="block text-sm font-medium text-gray-700 mb-2">
                                        Recommended Date
                                    </label>
                                    <input type="date" id="recommended_date_input" name="recommended_date" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>

                                <div>
                                    <label for="vaccine_status_input" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                    <select id="vaccine_status_input" name="status" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="RECOMMENDED">Recommended</option>
                                        <option value="SCHEDULED">Scheduled</option>
                                        <option value="GIVEN">Given</option>
                                        <option value="NOT_NEEDED">Not Needed</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="vaccine_notes_input" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                                    <textarea id="vaccine_notes_input" name="notes" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                              placeholder="Special instructions or notes..."></textarea>
                                </div>

                                <div class="flex gap-2">
                                    <button type="submit" 
                                            class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-pink-700 transition-colors font-medium">
                                        <i class="fas fa-save mr-2"></i>Save
                                    </button>
                                    <button type="reset" 
                                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                                        Clear
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Right: Vaccine Needs List -->
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-4">Patient Vaccine Needs</h4>
                            <div id="vaccineNeedsModalList" class="space-y-3 max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <p class="text-gray-500 text-center py-8">Select a patient to view vaccine needs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vaccination Modal -->
    <div id="vaccinationModal" class="modal-container hidden">
        <div class="modal-backdrop fixed inset-0 z-40" onclick="closeModal('vaccinationModal')"></div>
        <div class="modal-content relative z-50">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Record Vaccination</h3>
                        <button onclick="closeModal('vaccinationModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <form id="vaccinationForm" onsubmit="handleVaccinationForm(event)">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="record_vaccination">
                        
                        <div class="mb-4">
                            <label for="vaccine_patient_id" class="block text-sm font-medium text-gray-700 mb-2">Patient</label>
                            <select id="vaccine_patient_id" name="patient_id" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select a patient</option>
                                <?php foreach ($doctor_patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="vaccine_name" class="block text-sm font-medium text-gray-700 mb-2">Vaccine</label>
                            <select id="vaccine_name" name="vaccine_name" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select a vaccine</option>
                                <?php foreach ($vaccines as $vaccine): ?>
                                    <option value="<?php echo htmlspecialchars($vaccine['name']); ?>">
                                        <?php echo htmlspecialchars($vaccine['name']); ?> (<?php echo htmlspecialchars($vaccine['disease_protected']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="dose_number" class="block text-sm font-medium text-gray-700 mb-2">Dose Number</label>
                                <input type="number" id="dose_number" name="dose_number" min="1" max="10" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label for="administration_date" class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                <input type="date" id="administration_date" name="administration_date" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="lot_number" class="block text-sm font-medium text-gray-700 mb-2">Lot Number</label>
                            <input type="text" id="lot_number" name="lot_number" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div class="mb-6">
                            <label for="vaccination_notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea id="vaccination_notes" name="notes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('vaccinationModal')" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-pink-700 transition-colors">
                                Record Vaccination
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Vaccination Modal -->
    <div id="editVaccinationModal" class="modal-container hidden">
        <div class="modal-backdrop fixed inset-0 z-40" onclick="closeModal('editVaccinationModal')"></div>
        <div class="modal-content relative z-50">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Edit Vaccination Record</h3>
                        <button onclick="closeModal('editVaccinationModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <form id="editVaccinationForm" onsubmit="handleEditVaccinationForm(event)">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="edit_vaccination_record">
                        <input type="hidden" id="edit_vaccination_id" name="vaccination_id">
                        
                        <div class="mb-4">
                            <label for="edit_vaccine_patient_id" class="block text-sm font-medium text-gray-700 mb-2">Patient ID</label>
                            <input type="text" id="edit_vaccine_patient_id" readonly
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_vaccine_name" class="block text-sm font-medium text-gray-700 mb-2">Vaccine Name</label>
                            <input type="text" id="edit_vaccine_name" name="vaccine_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="edit_dose_number" class="block text-sm font-medium text-gray-700 mb-2">Dose Number</label>
                                <input type="number" id="edit_dose_number" name="dose_number" min="1" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label for="edit_administration_date" class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                <input type="date" id="edit_administration_date" name="administration_date" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_lot_number" class="block text-sm font-medium text-gray-700 mb-2">Lot Number</label>
                            <input type="text" id="edit_lot_number" name="lot_number"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div class="mb-6">
                            <label for="edit_vaccination_notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea id="edit_vaccination_notes" name="notes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('editVaccinationModal')" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-pink-700 transition-colors">
                                Update Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Medical Record Modal -->
    <div id="medicalRecordModal" class="modal-container hidden">
        <div class="modal-backdrop fixed inset-0 z-40" onclick="closeModal('medicalRecordModal')"></div>
        <div class="modal-content relative z-50">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Add Medical Record</h3>
                        <button onclick="closeModal('medicalRecordModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6 max-h-[80vh] overflow-y-auto">
                    <form id="medicalRecordForm" onsubmit="handleMedicalRecordForm(event)">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_medical_record">
                        <input type="hidden" id="medical_record_patient_id" name="patient_id">
                        
                        <div class="mb-4">
                            <div class="block text-sm font-medium text-gray-700 mb-2">Patient</div>
                            <div id="medicalRecordPatientName" class="px-3 py-2 bg-gray-100 rounded-lg text-gray-700">
                                Select a patient first
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="record_type" class="block text-sm font-medium text-gray-700 mb-2">Record Type <span class="text-red-500">*</span></label>
                            <select id="record_type" name="record_type" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select type</option>
                                <option value="CONSULTATION">Consultation</option>
                                <option value="CHECKUP">Checkup</option>
                                <option value="FOLLOW_UP">Follow-up</option>
                                <option value="EMERGENCY">Emergency</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="temperature" class="block text-sm font-medium text-gray-700 mb-2">Temperature (&deg;C)</label>
                                <input type="number" step="0.1" min="30" max="45" id="temperature" name="temperature" inputmode="decimal"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="e.g., 37.0">
                            </div>
                            <div>
                                <label for="blood_pressure" class="block text-sm font-medium text-gray-700 mb-2">Blood Pressure</label>
                                <input type="text" id="blood_pressure" name="blood_pressure"
                                       pattern="\d{2,3}\/\d{2,3}" maxlength="7"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="e.g., 120/80">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="heart_rate" class="block text-sm font-medium text-gray-700 mb-2">Heart Rate (BPM)</label>
                                <input type="number" id="heart_rate" name="heart_rate" min="20" max="300" step="1" inputmode="numeric"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label for="height" class="block text-sm font-medium text-gray-700 mb-2">Height (cm)</label>
                                <input type="number" step="0.1" min="10" max="250" id="height" name="height" inputmode="decimal"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="weight" class="block text-sm font-medium text-gray-700 mb-2">Weight (kg)</label>
                            <input type="number" step="0.1" min="0.5" max="300" id="weight" name="weight" inputmode="decimal"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>

                        <div class="mb-4">
                            <label for="diagnosis" class="block text-sm font-medium text-gray-700 mb-2">Diagnosis</label>
                            <input type="text" id="diagnosis" name="diagnosis" maxlength="255"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>

                        <div class="mb-4">
                            <label for="symptoms" class="block text-sm font-medium text-gray-700 mb-2">Symptoms</label>
                            <textarea id="symptoms" name="symptoms" rows="3" maxlength="2000"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="treatment_plan" class="block text-sm font-medium text-gray-700 mb-2">Treatment Plan</label>
                            <textarea id="treatment_plan" name="treatment_plan" rows="3" maxlength="2000"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label for="medical_notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea id="medical_notes" name="notes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('medicalRecordModal')" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-pink-700 transition-colors">
                                Save Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Notes Modal -->
    <div id="consultationModal" class="modal-container hidden">
        <div class="modal-backdrop fixed inset-0 z-40" onclick="closeModal('consultationModal')"></div>
        <div class="modal-content relative z-50">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Add Consultation Notes</h3>
                        <button onclick="closeModal('consultationModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <form id="consultationForm" onsubmit="handleConsultationForm(event)">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_consultation_notes">
                        <input type="hidden" id="consultation_patient_id" name="patient_id">
                        
                        <div class="mb-4">
                            <div class="block text-sm font-medium text-gray-700 mb-2">Patient</div>
                            <div id="consultationPatientName" class="px-3 py-2 bg-gray-100 rounded-lg text-gray-700">
                                Select a patient first
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="consultation_diagnosis" class="block text-sm font-medium text-gray-700 mb-2">Diagnosis</label>
                            <input type="text" id="consultation_diagnosis" name="diagnosis" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter diagnosis...">
                        </div>
                        
                        <div class="mb-4">
                            <label for="consultation_notes" class="block text-sm font-medium text-gray-700 mb-2">Consultation Notes</label>
                            <textarea id="consultation_notes" name="notes" rows="4" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="Enter detailed consultation notes..."></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label for="treatment_plan_consultation" class="block text-sm font-medium text-gray-700 mb-2">Treatment Plan</label>
                            <textarea id="treatment_plan_consultation" name="treatment_plan" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="Enter treatment plan..."></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('consultationModal')" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-pink-700 transition-colors">
                                Save Notes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Prescription Modal -->
    <div id="prescriptionModal" class="modal-container hidden">
        <div class="modal-backdrop fixed inset-0 z-40" onclick="closeModal('prescriptionModal')"></div>
        <div class="modal-content relative z-50">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Write Prescription</h3>
                        <button onclick="closeModal('prescriptionModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>                      
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <form id="prescriptionForm" onsubmit="handlePrescriptionForm(event)">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_prescription">
                        <input type="hidden" id="prescription_patient_id" name="patient_id">
                        
                        <div class="mb-4">
                            <div class="block text-sm font-medium text-gray-700 mb-2">Patient</div>
                            <div id="prescriptionPatientName" class="px-3 py-2 bg-gray-100 rounded-lg text-gray-700">
                                Select a patient first
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="medication_name" class="block text-sm font-medium text-gray-700 mb-2">Medication Name</label>
                            <input type="text" id="medication_name" name="medication_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter medication name...">
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="dosage" class="block text-sm font-medium text-gray-700 mb-2">Dosage</label>
                                <input type="text" id="dosage" name="dosage" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="e.g., 5mg">
                            </div>
                            <div>
                                <label for="frequency" class="block text-sm font-medium text-gray-700 mb-2">Frequency</label>
                                <input type="text" id="frequency" name="frequency" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="e.g., Twice daily">
                            </div>
                            <div>
                                <label for="duration" class="block text-sm font-medium text-gray-700 mb-2">Duration</label>
                                <input type="text" id="duration" name="duration" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="e.g., 7 days">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="instructions" class="block text-sm font-medium text-gray-700 mb-2">Instructions</label>
                            <textarea id="instructions" name="instructions" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="Enter special instructions..."></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('prescriptionModal')" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-pink-700 transition-colors">
                                Save Prescription
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global Variables
        let currentPatient = null;
        let currentPatientForVaccine = null;

        // Navigation and Section Management
        function showSection(sectionName) {
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.remove('hidden');
                targetSection.classList.add('fade-in');
                setActiveNav(sectionName);
                
                if (sectionName === 'dashboard') {
                    setTimeout(initializeCharts, 100);
                }
            }
        }
        
        function setActiveNav(sectionName) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active', 'bg-white/20');
            });
            
            const activeNav = document.querySelector(`.nav-item[data-section="${sectionName}"]`);
            if (activeNav) {
                activeNav.classList.add('active', 'bg-white/20');
            }
        }

        // Modal Management
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function closeAllModals() {
            document.querySelectorAll('.modal-container').forEach(modal => {
                modal.classList.add('hidden');
            });
        }

        // Notification System
        function showNotification(message, type = 'success', duration = 4000) {
            const notification = document.getElementById('notification');
            if (!notification) return;
            
            notification.textContent = message;
            notification.className = `notification ${type} hidden`;
            notification.classList.remove('hidden');
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, duration);
        }

        // Initialize Dashboard
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nav-item[data-section]').forEach(navItem => {
                navItem.addEventListener('click', function(e) {
                    e.preventDefault();
                    const sectionName = this.getAttribute('data-section');
                    showSection(sectionName);
                });
            });
            
            setActiveNav('dashboard');
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('administration_date').value = today;
            initializeCharts();
        });

        // Chart Initialization
        function initializeCharts() {
            const appointmentsCtx = document.getElementById('appointmentsChart');
            if (appointmentsCtx) {
                const appointmentLabels = <?php echo json_encode($appointment_chart_data['dates'] ?? []); ?>;
                const appointmentData = <?php echo json_encode($appointment_chart_data['counts'] ?? []); ?>;
                
                if (appointmentLabels && appointmentLabels.length > 0) {
                    new Chart(appointmentsCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: appointmentLabels,
                            datasets: [{
                                label: 'Daily Appointments',
                                data: appointmentData,
                                borderColor: '#FF6B9A',
                                backgroundColor: 'rgba(255, 107, 154, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top' }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                }
            }
            
            const vaccinationsCtx = document.getElementById('vaccinationsChart');
            if (vaccinationsCtx) {
                const vaccinationLabels = <?php echo json_encode($vaccination_chart_data['months'] ?? []); ?>;
                const vaccinationData = <?php echo json_encode($vaccination_chart_data['vaccination_counts'] ?? []); ?>;
                
                if (vaccinationLabels && vaccinationLabels.length > 0) {
                    new Chart(vaccinationsCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: vaccinationLabels,
                            datasets: [{
                                label: 'Vaccinations Administered',
                                data: vaccinationData,
                                backgroundColor: '#4F46E5',
                                borderColor: '#3730A3',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top' }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                }
            }
        }

        // Patient Management
        function selectPatient(patientId, element) {
            document.querySelectorAll('.patient-item').forEach(item => {
                item.classList.remove('bg-primary/10', 'border-primary', 'selected');
            });
            
            if (element) {
                element.classList.add('bg-primary/10', 'border-primary', 'selected');
            }
            
            currentPatient = patientId;
            loadPatientDetails(patientId);
            loadPatientVaccineNeeds(patientId);
            loadPatientVaccinationHistory(patientId);
        }

        function loadPatientDetails(patientId) {
            const patientSummary = document.getElementById('patientSummary');
            patientSummary.innerHTML = `
                <div class="text-center">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <div class="text-sm text-gray-500">Loading patient details...</div>
                </div>
            `;

            const csrf = '<?php echo $_SESSION['csrf_token']; ?>';

            const detailsData = new FormData();
            detailsData.append('action', 'get_patient_details');
            detailsData.append('patient_id', patientId);
            detailsData.append('ajax', 'true');
            detailsData.append('csrf_token', csrf);

            const recordsData = new FormData();
            recordsData.append('action', 'get_medical_records');
            recordsData.append('patient_id', patientId);
            recordsData.append('ajax', 'true');
            recordsData.append('csrf_token', csrf);

            Promise.all([
                fetch('', { method: 'POST', body: detailsData }).then(r => r.json()),
                fetch('', { method: 'POST', body: recordsData }).then(r => r.json()).catch(() => ({ success: false, records: [] }))
            ])
            .then(([detailsResp, recordsResp]) => {
                if (detailsResp && detailsResp.success) {
                    const records = (recordsResp && recordsResp.success && Array.isArray(recordsResp.records))
                        ? recordsResp.records : [];
                    displayPatientDetails(detailsResp.patient, records);
                } else {
                    patientSummary.innerHTML = `<p class="text-red-500">${(detailsResp && detailsResp.message) || 'Patient not found'}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                patientSummary.innerHTML = '<p class="text-red-500">Failed to load patient details</p>';
            });
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function displayPatientDetails(patient, records) {
            const patientSummary = document.getElementById('patientSummary');
            const age = patient.age_years > 0
                ? `${patient.age_years} years`
                : `${patient.age_months} months`;

            records = Array.isArray(records) ? records : [];

            // Find most recent vitals from records
            let latestVitals = null;
            for (const r of records) {
                if (r.temperature || r.heart_rate || r.blood_pressure || r.height || r.weight) {
                    latestVitals = r;
                    break;
                }
            }

            const vitalsHtml = latestVitals ? `
                <div class="bg-gradient-to-r from-primary/5 to-blue-50 border border-primary/20 rounded-lg p-3 mt-3">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-semibold text-gray-800 text-sm"><i class="fas fa-heartbeat text-primary mr-1"></i>Recent Vitals</h5>
                        <span class="text-xs text-gray-500">${latestVitals.record_date ? new Date(latestVitals.record_date).toLocaleDateString() : ''}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        ${latestVitals.temperature ? `<div><span class="text-gray-600">Temp:</span> <span class="font-medium">${latestVitals.temperature}&deg;C</span></div>` : ''}
                        ${latestVitals.heart_rate ? `<div><span class="text-gray-600">HR:</span> <span class="font-medium">${latestVitals.heart_rate} bpm</span></div>` : ''}
                        ${latestVitals.blood_pressure ? `<div><span class="text-gray-600">BP:</span> <span class="font-medium">${escapeHtml(latestVitals.blood_pressure)}</span></div>` : ''}
                        ${latestVitals.height ? `<div><span class="text-gray-600">Height:</span> <span class="font-medium">${latestVitals.height} cm</span></div>` : ''}
                        ${latestVitals.weight ? `<div><span class="text-gray-600">Weight:</span> <span class="font-medium">${latestVitals.weight} kg</span></div>` : ''}
                    </div>
                </div>
            ` : `
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-3 text-center">
                    <p class="text-xs text-gray-500"><i class="fas fa-heartbeat mr-1"></i>No recent vitals recorded</p>
                </div>
            `;

            let historyHtml = '';
            if (records.length === 0) {
                historyHtml = `
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                        <i class="fas fa-folder-open text-gray-400 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-500">No visit history yet</p>
                    </div>
                `;
            } else {
                const historyItems = records.slice(0, 5).map(r => {
                    const vitalsBits = [];
                    if (r.temperature) vitalsBits.push(`T ${r.temperature}&deg;C`);
                    if (r.heart_rate) vitalsBits.push(`HR ${r.heart_rate}`);
                    if (r.blood_pressure) vitalsBits.push(`BP ${escapeHtml(r.blood_pressure)}`);
                    if (r.height) vitalsBits.push(`Ht ${r.height}cm`);
                    if (r.weight) vitalsBits.push(`Wt ${r.weight}kg`);
                    const vitalsLine = vitalsBits.length
                        ? `<div class="text-xs text-gray-600 mt-1">${vitalsBits.join(' &middot; ')}</div>` : '';
                    const dateStr = r.record_date ? new Date(r.record_date).toLocaleDateString() : '';
                    const typeLabel = escapeHtml((r.record_type || 'VISIT').replace(/_/g, ' '));
                    return `
                        <div class="p-3 border border-gray-200 rounded-lg bg-white">
                            <div class="flex justify-between items-start">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-primary/10 text-primary">${typeLabel}</span>
                                        <span class="text-xs text-gray-500">${dateStr}</span>
                                    </div>
                                    ${r.diagnosis ? `<div class="text-sm font-medium text-gray-800">${escapeHtml(r.diagnosis)}</div>` : ''}
                                    ${r.symptoms ? `<div class="text-xs text-gray-600 mt-1"><span class="font-semibold">Symptoms:</span> ${escapeHtml(r.symptoms)}</div>` : ''}
                                    ${vitalsLine}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                const more = records.length > 5
                    ? `<div class="text-xs text-center text-gray-500 pt-2">Showing 5 of ${records.length} records</div>` : '';
                historyHtml = `<div class="space-y-2">${historyItems}${more}</div>`;
            }

            patientSummary.innerHTML = `
                <div class="text-left space-y-4">
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-user-circle text-primary mr-1"></i>Patient Information</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-gray-600">Name:</div>
                            <div class="font-medium">${escapeHtml(patient.first_name)} ${escapeHtml(patient.last_name)}</div>
                            <div class="text-gray-600">DOB:</div>
                            <div class="font-medium">${patient.date_of_birth ? new Date(patient.date_of_birth).toLocaleDateString() : 'N/A'}</div>
                            <div class="text-gray-600">Age:</div>
                            <div class="font-medium">${age}</div>
                            <div class="text-gray-600">Gender:</div>
                            <div class="font-medium">${escapeHtml(patient.gender || 'N/A')}</div>
                            ${patient.height ? `<div class="text-gray-600">Height:</div><div class="font-medium">${patient.height} cm</div>` : ''}
                            ${patient.weight ? `<div class="text-gray-600">Weight:</div><div class="font-medium">${patient.weight} kg</div>` : ''}
                            ${patient.blood_type ? `<div class="text-gray-600">Blood Type:</div><div class="font-medium">${escapeHtml(patient.blood_type)}</div>` : ''}
                        </div>
                        ${patient.allergies ? `<div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs"><span class="font-semibold text-red-700"><i class="fas fa-exclamation-triangle mr-1"></i>Allergies:</span> <span class="text-red-700">${escapeHtml(patient.allergies)}</span></div>` : ''}
                        ${patient.medical_conditions ? `<div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs"><span class="font-semibold text-yellow-700"><i class="fas fa-notes-medical mr-1"></i>Conditions:</span> <span class="text-yellow-700">${escapeHtml(patient.medical_conditions)}</span></div>` : ''}
                    </div>
                    ${vitalsHtml}
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2 flex items-center justify-between">
                            <span><i class="fas fa-history text-primary mr-1"></i>Patient History</span>
                            <span class="text-xs text-gray-500 font-normal">${records.length} record${records.length === 1 ? '' : 's'}</span>
                        </h4>
                        ${historyHtml}
                    </div>
                </div>
            `;
        }

        function loadPatientVaccinationHistory(patientId) {
            const formData = new FormData();
            formData.append('action', 'get_patient_vaccination_records');
            formData.append('patient_id', patientId);
            formData.append('ajax', 'true');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderPatientVaccinationHistory(data.data);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function renderPatientVaccinationHistory(records) {
            const container = document.getElementById('patientVaccinationHistory');
            if (!container) return;
            
            if (!records || records.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4">No vaccination history</p>';
                return;
            }
            
            let html = '<div class="space-y-3">';
            records.forEach(record => {
                html += `
                    <div class="p-3 border border-gray-200 rounded-lg bg-white hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="font-semibold text-gray-800 text-sm">${record.vaccine_name}</div>
                                <div class="text-xs text-gray-600">
                                    ${new Date(record.administration_date).toLocaleDateString()}
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">
                                Dose #${record.dose_number}
                            </span>
                        </div>
                        ${record.lot_number ? `<div class="text-xs text-gray-600 mb-2">Lot: ${record.lot_number}</div>` : ''}
                        ${record.notes ? `<div class="text-xs text-gray-600 mb-2"><strong>Notes:</strong> ${record.notes}</div>` : ''}
                        <div class="flex gap-2 mt-3">
                            <button onclick="editVaccinationModal(${record.id})" 
                                    class="flex-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 transition-colors">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="deleteVaccinationFromRecord(${record.id})" 
                                    class="flex-1 px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        // Vaccine Needs Management
        function openVaccineNeedModal() {
            if (!currentPatient) {
                showNotification('Please select a patient first', 'error');
                return;
            }
            
            currentPatientForVaccine = currentPatient;
            
            document.getElementById('vaccineNeedForm').reset();
            document.getElementById('vaccine_need_id').value = '';
            document.getElementById('vaccine_need_patient_id').value = currentPatient;
            
            const selectedPatient = document.querySelector(`.patient-item.selected`);
            if (selectedPatient) {
                const patientName = selectedPatient.querySelector('.font-semibold').textContent;
                document.getElementById('vaccine_patient_display').textContent = patientName;
            }
            
            loadRecommendedVaccines(currentPatient);
            loadVaccineNeedsForModal(currentPatient);
            
            document.getElementById('vaccineNeedModal').classList.remove('hidden');
        }

        function loadRecommendedVaccines(patientId) {
            const formData = new FormData();
            formData.append('action', 'get_recommended_vaccines');
            formData.append('patient_id', patientId);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const ageMonths = data.age_months;
                    let ageDisplay = '';
                    if (ageMonths < 12) {
                        ageDisplay = `${ageMonths} months`;
                    } else {
                        const years = Math.floor(ageMonths / 12);
                        const months = ageMonths % 12;
                        ageDisplay = `${years} year${years > 1 ? 's' : ''} ${months} month${months !== 1 ? 's' : ''}`;
                    }
                    document.getElementById('vaccine_age_display').textContent = ageDisplay;
                    
                    const vaccinesList = document.getElementById('recommendedVaccinesList');
                    if (data.data && data.data.length > 0) {
                        let html = '<ul class="list-disc list-inside space-y-1">';
                        data.data.forEach(vaccine => {
                            html += `<li>${vaccine.vaccine_name} (${vaccine.disease_protected})</li>`;
                        });
                        html += '</ul>';
                        vaccinesList.innerHTML = html;
                    } else {
                        vaccinesList.innerHTML = '<p class="text-gray-700">No vaccines recommended for this age</p>';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function loadVaccineNeedsForModal(patientId) {
            const formData = new FormData();
            formData.append('action', 'get_vaccine_needs');
            formData.append('patient_id', patientId);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderVaccineNeedsForModal(data.data);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function renderVaccineNeedsForModal(vaccines) {
            const container = document.getElementById('vaccineNeedsModalList');
            
            if (!vaccines || vaccines.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-8">No vaccine needs recorded</p>';
                return;
            }
            
            let html = '';
            vaccines.forEach(vaccine => {
                const statusColor = {
                    'RECOMMENDED': 'bg-yellow-100 text-yellow-800',
                    'SCHEDULED': 'bg-blue-100 text-blue-800',
                    'GIVEN': 'bg-green-100 text-green-800',
                    'NOT_NEEDED': 'bg-gray-100 text-gray-800'
                }[vaccine.status] || 'bg-gray-100 text-gray-800';
                
                const dateDisplay = vaccine.recommended_date 
                    ? new Date(vaccine.recommended_date).toLocaleDateString() 
                    : 'Not set';
                
                html += `
                    <div class="p-4 bg-white border border-gray-200 rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="font-semibold text-gray-800">${vaccine.vaccine_name}</div>
                                <div class="text-sm text-gray-600">Due: ${dateDisplay}</div>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded ${statusColor}">
                                ${vaccine.status}
                            </span>
                        </div>
                        ${vaccine.notes ? `<div class="text-sm text-gray-600 mb-2"><strong>Notes:</strong> ${vaccine.notes}</div>` : ''}
                        <div class="flex gap-2 mt-3">
                            <button onclick='editVaccineNeed(${JSON.stringify(vaccine)})' 
                                    class="flex-1 px-3 py-1 text-sm bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 transition-colors">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="deleteVaccineNeed(${vaccine.id})" 
                                    class="flex-1 px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function editVaccineNeed(vaccine) {
            document.getElementById('vaccine_need_id').value = vaccine.id;
            document.getElementById('vaccine_name_input').value = vaccine.vaccine_name;
            document.getElementById('recommended_date_input').value = vaccine.recommended_date || '';
            document.getElementById('vaccine_status_input').value = vaccine.status;
            document.getElementById('vaccine_notes_input').value = vaccine.notes || '';
        }

        async function handleVaccineNeedForm(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', 'true');
            
            showNotification('Saving vaccine need...', 'info');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    form.reset();
                    document.getElementById('vaccine_need_id').value = '';
                    loadVaccineNeedsForModal(currentPatientForVaccine);
                    loadPatientVaccineNeeds(currentPatientForVaccine);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to save vaccine need', 'error');
            }
        }

        function deleteVaccineNeed(vaccineNeedId) {
            if (!confirm('Are you sure you want to delete this vaccine need?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_vaccine_need');
            formData.append('vaccine_need_id', vaccineNeedId);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            showNotification('Deleting vaccine need...', 'info');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadVaccineNeedsForModal(currentPatientForVaccine);
                    loadPatientVaccineNeeds(currentPatientForVaccine);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to delete vaccine need', 'error');
            });
        }

        function loadPatientVaccineNeeds(patientId) {
            const formData = new FormData();
            formData.append('action', 'get_vaccine_needs');
            formData.append('patient_id', patientId);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderPatientVaccineNeeds(data.data);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function renderPatientVaccineNeeds(vaccines) {
            const container = document.getElementById('patientVaccineNeedsList');
            
            if (!vaccines || vaccines.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4">No vaccine needs recorded</p>';
                return;
            }

            let html = '<div class="space-y-3">';
            vaccines.forEach(vaccine => {
                const statusColor = {
                    'RECOMMENDED': 'bg-yellow-100 text-yellow-800',
                    'SCHEDULED': 'bg-blue-100 text-blue-800',
                    'GIVEN': 'bg-green-100 text-green-800',
                    'NOT_NEEDED': 'bg-gray-100 text-gray-800'
                }[vaccine.status] || 'bg-gray-100 text-gray-800';

                const dateDisplay = vaccine.recommended_date 
                    ? new Date(vaccine.recommended_date).toLocaleDateString() 
                    : 'Not set';

                html += `
                    <div class="p-3 border border-gray-200 rounded-lg bg-white hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="font-semibold text-gray-800 text-sm">${vaccine.vaccine_name}</div>
                                <div class="text-xs text-gray-600">Due: ${dateDisplay}</div>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded ${statusColor}">
                                ${vaccine.status}
                            </span>
                        </div>
                        ${vaccine.notes ? `<div class="text-xs text-gray-600 mb-2">${vaccine.notes}</div>` : ''}
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        // Vaccination Record Management
        function openVaccinationModal() {
            if (!currentPatient) {
                showNotification('Please select a patient first', 'error');
                return;
            }
            document.getElementById('vaccine_patient_id').value = currentPatient;
            document.getElementById('vaccinationModal').classList.remove('hidden');
        }

        async function handleVaccinationForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', 'true');
            
            showNotification('Recording vaccination...', 'info');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('vaccinationModal');
                    form.reset();
                    document.getElementById('administration_date').value = new Date().toISOString().split('T')[0];
                    if (currentPatient) {
                        loadPatientVaccinationHistory(currentPatient);
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to record vaccination', 'error');
                console.error('Error:', error);
            }
        }

        function editVaccinationModal(vaccinationId) {
            const formData = new FormData();
            formData.append('action', 'get_vaccination_record');
            formData.append('vaccination_id', vaccinationId);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.record) {
                    showVaccinationEditModal(data.record);
                } else {
                    showNotification('Failed to load vaccination record', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to load vaccination record', 'error');
            });
        }

        function showVaccinationEditModal(record) {
            document.getElementById('edit_vaccination_id').value = record.id;
            document.getElementById('edit_vaccine_patient_id').value = record.patient_id;
            document.getElementById('edit_vaccine_name').value = record.vaccine_name;
            document.getElementById('edit_dose_number').value = record.dose_number;
            document.getElementById('edit_administration_date').value = record.administration_date;
            document.getElementById('edit_lot_number').value = record.lot_number || '';
            document.getElementById('edit_vaccination_notes').value = record.notes || '';
            
            document.getElementById('editVaccinationModal').classList.remove('hidden');
        }

        async function handleEditVaccinationForm(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', 'true');
            
            showNotification('Updating vaccination record...', 'info');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('editVaccinationModal');
                    if (currentPatient) {
                        loadPatientVaccinationHistory(currentPatient);
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to update vaccination record', 'error');
                console.error('Error:', error);
            }
        }

        function deleteVaccinationFromRecord(vaccinationId) {
            if (!confirm('Are you sure you want to delete this vaccination record?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_vaccination_record');
            formData.append('vaccination_id', vaccinationId);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            showNotification('Deleting vaccination record...', 'info');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    if (currentPatient) {
                        loadPatientVaccinationHistory(currentPatient);
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to delete vaccination record', 'error');
            });
        }

        // Modal Openers
        function openConsultationModal() {
            if (!currentPatient) {
                showNotification('Please select a patient first', 'error');
                return;
            }
            
            const selectedPatient = document.querySelector(`.patient-item.selected`);
            if (selectedPatient) {
                const patientName = selectedPatient.querySelector('.font-semibold').textContent;
                document.getElementById('consultationPatientName').textContent = patientName;
            }
            
            document.getElementById('consultation_patient_id').value = currentPatient;
            document.getElementById('consultationModal').classList.remove('hidden');
        }

        function openPrescriptionModal() {
            if (!currentPatient) {
                showNotification('Please select a patient first', 'error');
                return;
            }
            
            const selectedPatient = document.querySelector(`.patient-item.selected`);
            if (selectedPatient) {
                const patientName = selectedPatient.querySelector('.font-semibold').textContent;
                document.getElementById('prescriptionPatientName').textContent = patientName;
            }
            
            document.getElementById('prescription_patient_id').value = currentPatient;
            document.getElementById('prescriptionModal').classList.remove('hidden');
        }

        function openMedicalRecordModal() {
            if (!currentPatient) {
                showNotification('Please select a patient first', 'error');
                return;
            }
            
            const selectedPatient = document.querySelector(`.patient-item.selected`);
            if (selectedPatient) {
                const patientName = selectedPatient.querySelector('.font-semibold').textContent;
                document.getElementById('medicalRecordPatientName').textContent = patientName;
            }
            
            document.getElementById('medical_record_patient_id').value = currentPatient;
            document.getElementById('medicalRecordModal').classList.remove('hidden');
        }

        // Form Handlers
        async function handleVaccinationForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', 'true');
            
            showNotification('Recording vaccination...', 'info');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('vaccinationModal');
                    form.reset();
                    document.getElementById('administration_date').value = new Date().toISOString().split('T')[0];
                    if (currentPatient) {
                        loadPatientVaccinationHistory(currentPatient);
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to record vaccination', 'error');
                console.error('Error:', error);
            }
        }

        async function handleConsultationForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', 'true');
            
            showNotification('Saving consultation notes...', 'info');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('consultationModal');
                    form.reset();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to save consultation notes', 'error');
                console.error('Error:', error);
            }
        }

        async function handlePrescriptionForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('ajax', 'true');
            
            showNotification('Saving prescription...', 'info');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('prescriptionModal');
                    form.reset();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to save prescription', 'error');
                console.error('Error:', error);
            }
        }

        async function handleMedicalRecordForm(event) {
            event.preventDefault();
            const form = event.target;
            const pid = (form.patient_id && form.patient_id.value) || '';
            if (!pid || pid === '0') {
                showNotification('Please select a patient before adding a medical record.', 'warning');
                return;
            }
            if (!form.record_type.value) {
                showNotification('Please select a record type.', 'warning');
                return;
            }
            // Client-side vitals validation
            const numFields = [
                { name: 'temperature', min: 30, max: 45, label: 'Temperature' },
                { name: 'heart_rate',  min: 20, max: 300, label: 'Heart rate' },
                { name: 'height',      min: 10, max: 250, label: 'Height' },
                { name: 'weight',      min: 0.5, max: 300, label: 'Weight' }
            ];
            for (const f of numFields) {
                const el = form[f.name];
                if (el && el.value !== '') {
                    const v = Number(el.value);
                    if (!Number.isFinite(v) || v < f.min || v > f.max) {
                        showNotification(f.label + ' must be between ' + f.min + ' and ' + f.max + '.', 'warning');
                        el.focus();
                        return;
                    }
                }
            }

            const formData = new FormData(form);
            formData.append('ajax', 'true');

            showNotification('Saving medical record...', 'info');

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('medicalRecordModal');
                    form.reset();
                    if (typeof loadPatientDetails === 'function' && currentPatient) {
                        loadPatientDetails(currentPatient);
                    }
                } else {
                    showNotification(data.message || 'Failed to save medical record', 'error');
                }
            } catch (error) {
                showNotification('Failed to save medical record', 'error');
                console.error('Error:', error);
            }
        }

        // Doctor Profile Form Handlers
        async function handleDoctorProfileForm(event) {
            event.preventDefault();
            const form = event.target;

            // Basic client-side validation
            const email = (form.email && form.email.value || '').trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showNotification('Please enter a valid email address.', 'warning');
                form.email.focus();
                return;
            }
            const phone = (form.phone && form.phone.value || '').trim();
            if (phone && !/^[\d +()\-]{7,20}$/.test(phone)) {
                showNotification('Please enter a valid phone number.', 'warning');
                form.phone.focus();
                return;
            }
            if (form.years_of_experience && form.years_of_experience.value !== '') {
                const yrs = Number(form.years_of_experience.value);
                if (!Number.isFinite(yrs) || yrs < 0 || yrs > 80) {
                    showNotification('Years of experience must be between 0 and 80.', 'warning');
                    form.years_of_experience.focus();
                    return;
                }
            }
            if (form.date_of_birth && form.date_of_birth.value) {
                const dob = new Date(form.date_of_birth.value);
                if (dob > new Date()) {
                    showNotification('Date of birth cannot be in the future.', 'warning');
                    form.date_of_birth.focus();
                    return;
                }
            }

            const formData = new FormData(form);
            formData.append('action', 'update_doctor_profile');
            formData.append('ajax', 'true');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            showNotification('Saving profile...', 'info');

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message || 'Profile updated successfully', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showNotification(data.message || 'Failed to update profile', 'error');
                }
            } catch (error) {
                showNotification('Failed to update profile', 'error');
                console.error('Error:', error);
            }
        }

        async function handleDoctorPasswordForm(event) {
            event.preventDefault();
            const form = event.target;
            const current = (form.current_password && form.current_password.value) || '';
            const next = (form.new_password && form.new_password.value) || '';
            const confirm = (form.confirm_password && form.confirm_password.value) || '';

            if (!current || !next || !confirm) {
                showNotification('All password fields are required.', 'warning');
                return;
            }
            if (next.length < 8) {
                showNotification('New password must be at least 8 characters long.', 'warning');
                form.new_password.focus();
                return;
            }
            if (next !== confirm) {
                showNotification('New password and confirmation do not match.', 'warning');
                form.confirm_password.focus();
                return;
            }
            if (next === current) {
                showNotification('New password must be different from the current password.', 'warning');
                form.new_password.focus();
                return;
            }

            const formData = new FormData(form);
            formData.append('action', 'change_doctor_password');
            formData.append('ajax', 'true');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            showNotification('Updating password...', 'info');

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message || 'Password updated successfully', 'success');
                    form.reset();
                } else {
                    showNotification(data.message || 'Failed to update password', 'error');
                }
            } catch (error) {
                showNotification('Failed to update password', 'error');
                console.error('Error:', error);
            }
        }

        // Appointment Actions
        function approveAppointment(appointmentId) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'approve_appointment');
            formData.append('appointment_id', appointmentId);
            formData.append('ajax', 'true');
            
            showNotification('Approving appointment...', 'info');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to approve appointment', 'error');
                console.error('Error:', error);
            });
        }

        function rejectAppointment(appointmentId) {
            const reason = prompt('Enter reason for rejection:');
            if (!reason) return;
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'reject_appointment');
            formData.append('appointment_id', appointmentId);
            formData.append('reason', reason);
            formData.append('ajax', 'true');
            
            showNotification('Rejecting appointment...', 'info');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to reject appointment', 'error');
                console.error('Error:', error);
            });
        }

        function completeAppointment(appointmentId) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'complete_appointment');
            formData.append('appointment_id', appointmentId);
            formData.append('ajax', 'true');
            
            showNotification('Completing appointment...', 'info');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to complete appointment', 'error');
                console.error('Error:', error);
            });
        }

        function filterAppointments() {
            const filter = document.getElementById('appointmentFilter').value;
            showNotification(`Filtering appointments: ${filter}`, 'info');
        }

        function viewAppointmentDetails(appointmentId) {
            const formData = new FormData();
            formData.append('action', 'get_appointment_details');
            formData.append('appointment_id', appointmentId);
            formData.append('ajax', 'true');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Appointment loaded', 'success');
                    console.log('Appointment Details:', data.appointment);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load appointment details', 'error');
                console.error('Error:', error);
            });
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                closeAllModals();
            }
        });

        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllModals();
            }
        });
    </script>
</body>
</html>