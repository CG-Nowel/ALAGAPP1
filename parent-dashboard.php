<?php
require 'db_connect.php';



// AJAX endpoint to return medical records for a child (used by the Medical Records modal).
// Request: parent-dashboard.php?action=get_medical_records&child_id=NN
if (isset($_GET['action']) && $_GET['action'] === 'get_medical_records' && isset($_GET['child_id'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Ensure user is logged in and is the parent
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'PARENT') {
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit();
    }

    $parent_id = intval($_SESSION['user_id']);
    $child_id = intval($_GET['child_id']);

    // Verify child belongs to parent
    $check_query = "SELECT id FROM patients WHERE id = ? AND parent_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $child_id, $parent_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Child not found or access denied']);
        exit();
    }

    // Load real data using helper functions from db_connect.php
    // Inside AJAX branch handling get_medical_records (do NOT reference $children)
    $consultations = get_patient_consultation_notes($conn, $child_id);
    $prescriptions = get_patient_prescriptions($conn, $child_id);
    $vaccinations = get_patient_vaccination_history($conn, $child_id);

    echo json_encode([
        'success' => true,
        'consultations' => $consultations,
        'prescriptions' => $prescriptions,
        'vaccinations' => $vaccinations
    ]);
    exit();

    echo json_encode([
        'success' => true,
        'consultations' => $consultations,
        'prescriptions' => $prescriptions,
        'vaccinations' => $vaccinations
    ]);
    exit();
}

// -- Page logic starts here --

// Check if user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'PARENT') {
    header("Location: index.php");
    exit();
}

// Flash messages rendered as toasts after toast.js loads
$__pendingToasts = [];
if (isset($_SESSION['success_message'])) {
    $__pendingToasts[] = ['message' => (string)$_SESSION['success_message'], 'type' => 'success'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $__pendingToasts[] = ['message' => (string)$_SESSION['error_message'], 'type' => 'error'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['info_message'])) {
    $__pendingToasts[] = ['message' => (string)$_SESSION['info_message'], 'type' => 'info'];
    unset($_SESSION['info_message']);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Get user details
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Get user's children
$children_query = "SELECT * FROM patients WHERE parent_id = ?";
$stmt = mysqli_prepare($conn, $children_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$children_result = mysqli_stmt_get_result($stmt);
$children = [];
while ($row = mysqli_fetch_assoc($children_result)) {
    $children[] = $row;
}
// === Load files for each child (add after you fetch $children) ===
foreach ($children as &$child) {
    $files_q = "SELECT id, original_filename, mime_type, file_size, created_at FROM patient_files WHERE patient_id = ? ORDER BY created_at DESC";
    $fstmt = mysqli_prepare($conn, $files_q);
    mysqli_stmt_bind_param($fstmt, "i", $child['id']);
    mysqli_stmt_execute($fstmt);
    $fres = mysqli_stmt_get_result($fstmt);
    $child_files = [];
    while ($frow = mysqli_fetch_assoc($fres)) {
        $child_files[] = $frow;
    }
    $child['files'] = $child_files;
}
unset($child);
// --- Populate vaccine needs grouped by patient_id (place this AFTER you populate $children) ---
$vaccine_needs_by_child = [];

$child_ids = array_map(function($c){ return intval($c['id']); }, $children ?? []);
$child_ids = array_filter($child_ids); // remove zeros / falsy

if (!empty($child_ids)) {
    // safe because we've cast IDs to integers
    $in_list = implode(',', $child_ids);

    $q = "SELECT id, patient_id, vaccine_name, recommended_date, status, notes, created_by, created_at, updated_at
          FROM patient_vaccine_needs
          WHERE patient_id IN ($in_list)
          ORDER BY COALESCE(recommended_date, created_at) ASC";
    $res = mysqli_query($conn, $q);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $pid = intval($row['patient_id']);
            if (!isset($vaccine_needs_by_child[$pid])) $vaccine_needs_by_child[$pid] = [];
            $vaccine_needs_by_child[$pid][] = $row;
        }
        mysqli_free_result($res);
    } else {
        error_log('patient_vaccine_needs query failed: ' . mysqli_error($conn));
    }
}
// Get appointments
$appointments_query = "SELECT a.*, p.first_name as child_first_name, p.last_name as child_last_name, 
                              u.first_name as doctor_first_name, u.last_name as doctor_last_name
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       JOIN users u ON a.doctor_id = u.id 
                       WHERE p.parent_id = ? 
                       ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$stmt = mysqli_prepare($conn, $appointments_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$appointments_result = mysqli_stmt_get_result($stmt);
$appointments = [];
while ($row = mysqli_fetch_assoc($appointments_result)) {
    $appointments[] = $row;
}

// Get vaccination records - Enhanced query with better data
$vaccinations_query = "SELECT vr.*, p.first_name as child_first_name, p.last_name as child_last_name,
                              u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                              p.date_of_birth as child_dob
                       FROM vaccination_records vr 
                       JOIN patients p ON vr.patient_id = p.id 
                       LEFT JOIN users u ON vr.administered_by = u.id 
                       WHERE p.parent_id = ? 
                       ORDER BY vr.administration_date DESC, vr.vaccine_name ASC";
$stmt = mysqli_prepare($conn, $vaccinations_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$vaccinations_result = mysqli_stmt_get_result($stmt);
$vaccinations = [];
while ($row = mysqli_fetch_assoc($vaccinations_result)) {
    $vaccinations[] = $row;
}

// Get available doctors
$doctors_query = "SELECT id, first_name, last_name, specialization 
                  FROM users 
                  WHERE user_type IN ('DOCTOR','DOCTOR_OWNER') AND status = 'active'
                  ORDER BY last_name, first_name";
$doctors_result = mysqli_query($conn, $doctors_query);
$doctors = [];
while ($row = mysqli_fetch_assoc($doctors_result)) {
    $doctors[] = $row;
}

// Common vaccine schedules for reference
$common_vaccines = [
    'BCG' => ['doses' => 1, 'description' => 'Tuberculosis vaccine'],
    'Hepatitis B' => ['doses' => 3, 'description' => 'Hepatitis B prevention'],
    'DPT' => ['doses' => 5, 'description' => 'Diphtheria, Pertussis, Tetanus'],
    'Polio' => ['doses' => 4, 'description' => 'Polio vaccine'],
    'MMR' => ['doses' => 2, 'description' => 'Measles, Mumps, Rubella'],
    'Varicella' => ['doses' => 2, 'description' => 'Chickenpox vaccine'],
    'Hepatitis A' => ['doses' => 2, 'description' => 'Hepatitis A prevention'],
    'Pneumococcal' => ['doses' => 4, 'description' => 'Pneumonia prevention'],
    'Rotavirus' => ['doses' => 3, 'description' => 'Rotavirus gastroenteritis prevention'],
    'Influenza' => ['doses' => 'Annual', 'description' => 'Seasonal flu vaccine']
];

// Get growth records for each child
$growthRecords = [];
foreach ($children as $child) {
    $growthQuery = "SELECT height, weight, created_at 
                    FROM growth_records 
                    WHERE patient_id = ? 
                    AND height IS NOT NULL 
                    AND weight IS NOT NULL
                    ORDER BY created_at";
    $stmt = mysqli_prepare($conn, $growthQuery);
    mysqli_stmt_bind_param($stmt, "i", $child['id']);
    mysqli_stmt_execute($stmt);
    $growthResult = mysqli_stmt_get_result($stmt);
    $growthRecords[$child['id']] = [];
    while ($row = mysqli_fetch_assoc($growthResult)) {
        $growthRecords[$child['id']][] = $row;
    }
}

// Handle form submissions (Add/Edit child, Book/Cancel appointment)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_child'])) {
        if (handleAddChild($conn, $user_id)) {
            $_SESSION['success_message'] = 'Child added successfully!';
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['book_appointment'])) {
        if (handleBookAppointment($conn, $user_id)) {
            $_SESSION['success_message'] = 'Appointment booked successfully!';
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['cancel_appointment'])) {
        if (handleCancelAppointment($conn, $user_id)) {
            $_SESSION['success_message'] = 'Appointment cancelled successfully!';
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['update_profile'])) {
        if (handleUpdateProfile($conn, $user_id)) {
            $_SESSION['success_message'] = 'Profile updated successfully!';
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "#settings-section");
        exit();
    } elseif (isset($_POST['change_password'])) {
        if (handleChangePassword($conn, $user_id)) {
            $_SESSION['success_message'] = 'Password changed successfully!';
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "#settings-section");
        exit();
    }
}

// === File upload handler (add near other POST handlers) ===
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ... existing POST branches ...

    // File upload - field name: patient_file
    if (isset($_POST['upload_patient_file'])) {
        if (handleUploadPatientFile($conn, $user_id)) {
            $_SESSION['success_message'] = 'File uploaded successfully!';
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Secure file upload function
function handleUploadPatientFile($conn, $u_id) {
    if (!isset($_POST['upload_patient_file']) || !isset($_POST['upload_patient_id'])) {
        $_SESSION['error_message'] = 'Invalid upload request.';
        return false;
    }

    $patient_id = intval($_POST['upload_patient_id']);
    $notes = mysqli_real_escape_string($conn, trim($_POST['upload_notes'] ?? ''));

    // Verify child belongs to parent
    $check_query = "SELECT id FROM patients WHERE id = ? AND parent_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $patient_id, $u_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) == 0) {
        $_SESSION['error_message'] = 'Child not found or you do not have permission.';
        return false;
    }

    if (!isset($_FILES['patient_file']) || $_FILES['patient_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'No file uploaded or upload error.';
        return false;
    }

    $file = $_FILES['patient_file'];

    // Config: allowed types + max size (adjust as needed)
    $maxBytes = 5 * 1024 * 1024; // 5 MB
    $allowedExtensions = ['pdf','jpg','jpeg','png','doc','docx'];
    $allowedMime = [
        'application/pdf',
        'image/jpeg','image/png',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    // Basic checks
    if ($file['size'] > $maxBytes) {
        $_SESSION['error_message'] = 'File too large. Maximum is 5 MB.';
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if (!in_array($mime, $allowedMime)) {
        $_SESSION['error_message'] = 'File type not allowed.';
        return false;
    }

    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        $_SESSION['error_message'] = 'File extension not allowed.';
        return false;
    }

    // Prepare safe storage
    $upload_base = __DIR__ . '/uploads/patient_files';
    if (!is_dir($upload_base)) {
        if (!mkdir($upload_base, 0750, true)) {
            $_SESSION['error_message'] = 'Failed to create upload directory.';
            return false;
        }
    }

    // Create unique stored filename (avoid collisions)
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $upload_base . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $_SESSION['error_message'] = 'Failed to move uploaded file.';
        return false;
    }

    // Store metadata in DB
    $insert = "INSERT INTO patient_files (patient_id, uploaded_by, original_filename, stored_filename, mime_type, file_size, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert);
    mysqli_stmt_bind_param($stmt, "iisssis", $patient_id, $u_id, $origName, $storedFilename, $mime, $file['size'], $notes);
    if (!mysqli_stmt_execute($stmt)) {
        // cleanup file on DB error
        @unlink($destination);
        $_SESSION['error_message'] = 'DB error saving file: ' . mysqli_error($conn);
        return false;
    }

    return true;
}

// Functions (using safe escaping; prepared statements used where straightforward)

function handleAddChild($conn, $parent_id) {
    $first_name = trim($_POST['child_first_name'] ?? '');
    $last_name = trim($_POST['child_last_name'] ?? '');
    $date_of_birth = trim($_POST['child_dob'] ?? '');
    $gender = trim($_POST['child_gender'] ?? '');
    $blood_type = trim($_POST['child_blood_type'] ?? '');
    $height_raw = isset($_POST['child_height']) ? trim($_POST['child_height']) : '';
    $weight_raw = isset($_POST['child_weight']) ? trim($_POST['child_weight']) : '';
    $allergies = trim($_POST['child_allergies'] ?? '');
    $medical_conditions = trim($_POST['child_medical_conditions'] ?? '');

    // Required field validation
    if ($first_name === '' || $last_name === '' || $date_of_birth === '' || $gender === '') {
        $_SESSION['error_message'] = 'First name, last name, date of birth and gender are required.';
        return false;
    }
    // Date of birth must not be in the future
    if (strtotime($date_of_birth) === false || strtotime($date_of_birth) > time()) {
        $_SESSION['error_message'] = 'Please enter a valid date of birth.';
        return false;
    }
    // Strict numeric validation for height/weight (reject strings)
    $height = null;
    if ($height_raw !== '') {
        if (!is_numeric($height_raw)) {
            $_SESSION['error_message'] = 'Height must be a number.';
            return false;
        }
        $height = (float)$height_raw;
        if ($height < 10 || $height > 250) {
            $_SESSION['error_message'] = 'Height must be between 10 and 250 cm.';
            return false;
        }
    }
    $weight = null;
    if ($weight_raw !== '') {
        if (!is_numeric($weight_raw)) {
            $_SESSION['error_message'] = 'Weight must be a number.';
            return false;
        }
        $weight = (float)$weight_raw;
        if ($weight < 0.5 || $weight > 300) {
            $_SESSION['error_message'] = 'Weight must be between 0.5 and 300 kg.';
            return false;
        }
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO patients (parent_id, first_name, last_name, date_of_birth, gender, blood_type, height, weight, allergies, medical_conditions)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        $_SESSION['error_message'] = 'Error adding child: ' . mysqli_error($conn);
        return false;
    }
    $pid = intval($parent_id);
    mysqli_stmt_bind_param(
        $stmt, "isssssddss",
        $pid, $first_name, $last_name, $date_of_birth, $gender, $blood_type,
        $height, $weight, $allergies, $medical_conditions
    );

    if (mysqli_stmt_execute($stmt)) {
        return true;
    }
    $_SESSION['error_message'] = 'Error adding child: ' . mysqli_stmt_error($stmt);
    return false;
}



function handleBookAppointment($conn, $user_id) {
    $patient_id = intval($_POST['appointment_child'] ?? 0);
    $doctor_id = intval($_POST['appointment_doctor'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $appointment_type = trim($_POST['appointment_service'] ?? 'CONSULTATION');
    $reason = trim($_POST['appointment_notes'] ?? '');

    // Required field validation
    if ($patient_id <= 0 || $doctor_id <= 0 || $appointment_date === '' || $appointment_time === '' || $appointment_type === '') {
        $_SESSION['error_message'] = 'Please fill in all required booking fields (child, doctor, date, time, service).';
        return false;
    }
    // Date validity & not in the past
    $date_ts = strtotime($appointment_date);
    if ($date_ts === false) {
        $_SESSION['error_message'] = 'Invalid appointment date.';
        return false;
    }
    if ($date_ts < strtotime(date('Y-m-d'))) {
        $_SESSION['error_message'] = 'Appointment date cannot be in the past.';
        return false;
    }
    // Time validity HH:MM or HH:MM:SS
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $appointment_time)) {
        $_SESSION['error_message'] = 'Invalid appointment time format.';
        return false;
    }

    // Verify child belongs to parent
    $check_query = "SELECT id FROM patients WHERE id = ? AND parent_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $patient_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) == 0) {
        $_SESSION['error_message'] = 'Child not found or you do not have permission.';
        return false;
    }

    // Basic conflict check: same doctor, same date & time
    $conflict_query = "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('SCHEDULED', 'CONFIRMED')";
    $conflict_stmt = mysqli_prepare($conn, $conflict_query);
    mysqli_stmt_bind_param($conflict_stmt, "iss", $doctor_id, $appointment_date, $appointment_time);
    mysqli_stmt_execute($conflict_stmt);
    $conflict_result = mysqli_stmt_get_result($conflict_stmt);
    if (mysqli_num_rows($conflict_result) > 0) {
        $_SESSION['error_message'] = 'Selected time is already booked. Please choose another time.';
        return false;
    }

    $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, type, reason, status)
              VALUES (?, ?, ?, ?, ?, ?, 'SCHEDULED')";
    $stmt = mysqli_prepare($conn, $query);
    $reason_db = $reason !== '' ? $reason : null;
    mysqli_stmt_bind_param($stmt, "iissss", $patient_id, $doctor_id, $appointment_date, $appointment_time, $appointment_type, $reason_db);

    if (mysqli_stmt_execute($stmt)) {
        return true;
    } else {
        $_SESSION['error_message'] = 'Error booking appointment: ' . mysqli_error($conn);
        return false;
    }
}

function handleCancelAppointment($conn, $user_id) {
    $appointment_id = intval($_POST['cancel_appointment_id'] ?? 0);

    // Verify appointment belongs to parent's child
    $check_query = "SELECT a.id FROM appointments a 
                    JOIN patients p ON a.patient_id = p.id 
                    WHERE a.id = ? AND p.parent_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $appointment_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) == 0) {
        $_SESSION['error_message'] = 'Appointment not found or you do not have permission to cancel.';
        return false;
    }

    $query = "UPDATE appointments SET status = 'CANCELLED' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $appointment_id);

    if (mysqli_stmt_execute($stmt)) {
        return true;
    } else {
        $_SESSION['error_message'] = 'Error cancelling appointment: ' . mysqli_error($conn);
        return false;
    }
}

function handleUpdateProfile($conn, $user_id) {
    $first_name = trim($_POST['profile_first_name'] ?? '');
    $last_name  = trim($_POST['profile_last_name']  ?? '');
    $email      = trim($_POST['profile_email']      ?? '');
    $phone      = trim($_POST['profile_phone']      ?? '');
    $dob        = trim($_POST['profile_dob']        ?? '');
    $gender     = trim($_POST['profile_gender']     ?? '');
    $address    = trim($_POST['profile_address']    ?? '');
    $ec_name    = trim($_POST['profile_emergency_contact_name']  ?? '');
    $ec_phone   = trim($_POST['profile_emergency_contact_phone'] ?? '');

    if ($first_name === '' || $last_name === '' || $email === '' || $phone === '') {
        $_SESSION['error_message'] = 'First name, last name, email and phone are required.';
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Please enter a valid email address.';
        return false;
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $_SESSION['error_message'] = 'Please enter a valid phone number.';
        return false;
    }
    if ($ec_phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $ec_phone)) {
        $_SESSION['error_message'] = 'Please enter a valid emergency contact phone.';
        return false;
    }
    if ($dob !== '' && strtotime($dob) > time()) {
        $_SESSION['error_message'] = 'Date of birth cannot be in the future.';
        return false;
    }

    // Ensure email uniqueness across other users
    $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    mysqli_stmt_bind_param($chk, "si", $email, $user_id);
    mysqli_stmt_execute($chk);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk))) {
        $_SESSION['error_message'] = 'That email is already used by another account.';
        return false;
    }

    $dob_value = $dob !== '' ? $dob : null;
    $gender_value = $gender !== '' ? $gender : null;

    $query = "UPDATE users
              SET first_name = ?, last_name = ?, email = ?, phone = ?,
                  date_of_birth = ?, gender = ?, address = ?,
                  emergency_contact_name = ?, emergency_contact_phone = ?
              WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        // Fallback for legacy schemas without the extra columns
        $query_fb = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
        $stmt_fb = mysqli_prepare($conn, $query_fb);
        mysqli_stmt_bind_param($stmt_fb, "ssssi", $first_name, $last_name, $email, $phone, $user_id);
        if (mysqli_stmt_execute($stmt_fb)) {
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $_SESSION['user_email'] = $email;
            return true;
        }
        $_SESSION['error_message'] = 'Error updating profile: ' . mysqli_error($conn);
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt, "sssssssssi",
        $first_name, $last_name, $email, $phone,
        $dob_value, $gender_value, $address,
        $ec_name, $ec_phone, $user_id
    );

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['user_name']  = $first_name . ' ' . $last_name;
        $_SESSION['user_email'] = $email;
        return true;
    }
    // If the column list doesn't exist, try the fallback
    $err = mysqli_error($conn);
    if (stripos($err, 'Unknown column') !== false) {
        $query_fb = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
        $stmt_fb = mysqli_prepare($conn, $query_fb);
        mysqli_stmt_bind_param($stmt_fb, "ssssi", $first_name, $last_name, $email, $phone, $user_id);
        if (mysqli_stmt_execute($stmt_fb)) {
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $_SESSION['user_email'] = $email;
            return true;
        }
    }
    $_SESSION['error_message'] = 'Error updating profile: ' . $err;
    return false;
}

function handleChangePassword($conn, $user_id) {
    $current  = (string)($_POST['current_password']  ?? '');
    $new      = (string)($_POST['new_password']      ?? '');
    $confirm  = (string)($_POST['confirm_password']  ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $_SESSION['error_message'] = 'All password fields are required.';
        return false;
    }
    if (strlen($new) < 8) {
        $_SESSION['error_message'] = 'New password must be at least 8 characters.';
        return false;
    }
    if ($new !== $confirm) {
        $_SESSION['error_message'] = 'New password and confirmation do not match.';
        return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$row) {
        $_SESSION['error_message'] = 'User not found.';
        return false;
    }
    $stored = (string)$row['password'];
    $ok = false;
    if (password_verify($current, $stored)) {
        $ok = true;
    } elseif (hash_equals($stored, md5($current))) {
        $ok = true; // legacy support
    } elseif (hash_equals($stored, $current)) {
        $ok = true; // legacy plaintext (shouldn't happen but guard)
    }
    if (!$ok) {
        $_SESSION['error_message'] = 'Current password is incorrect.';
        return false;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "si", $hash, $user_id);
    if (mysqli_stmt_execute($upd)) {
        return true;
    }
    $_SESSION['error_message'] = 'Error changing password: ' . mysqli_error($conn);
    return false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - AlagApp Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
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
            margin: 0;
            padding: 0;
        }
        
        .font-inter { font-family: 'Inter', sans-serif; }
        
        .text-primary { color: var(--primary-pink); }
        .bg-primary { background-color: var(--primary-pink); }
        .bg-light-pink { background-color: var(--light-pink); }
        
        .sidebar {
            background: linear-gradient(180deg, #FF6B9A 0%, #FFBCD9 100%);
            min-height: 100vh;
            transition: transform 0.3s ease;
            z-index: 40;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(255, 107, 154, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink), var(--light-pink));
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 154, 0.3);
        }
        
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.4);
        }
        
        /* Mobile menu styles */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .mobile-menu-btn {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .sidebar {
                position: fixed;
                width: 16rem;
                transform: translateX(0);
            }
            
            .mobile-menu-btn {
                display: none;
            }
            
            .main-content {
                margin-left: 16rem;
                width: calc(100% - 16rem);
            }
        }
        
        /* Table responsive styles */
        .table-container {
            overflow-x: auto;
        }
        
        /* Form responsive styles */
        .form-grid {
            display: grid;
            gap: 1rem;
        }
        
        @media (min-width: 640px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Stats grid responsive */
        .stats-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        @media (min-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Children grid responsive */
        .children-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        @media (min-width: 640px) {
            .children-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .children-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Content sections responsive */
        .content-grid {
            display: grid;
            gap: 2rem;
        }
        
        @media (min-width: 1024px) {
            .content-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Modal responsive */
        .modal-content {
            width: 95%;
            margin: 1rem auto;
        }
        
        @media (min-width: 640px) {
            .modal-content {
                width: 90%;
                max-width: 42rem;
            }
        }
        
        @media (min-width: 1024px) {
            .modal-content {
                max-width: 56rem;
            }
        }
        
        /* Navigation icons responsive */
        .nav-icon {
            width: 1.25rem;
            height: 1.25rem;
        }
        
        /* Text sizing responsive */
        .responsive-text {
            font-size: 0.875rem;
        }
        
        @media (min-width: 768px) {
            .responsive-text {
                font-size: 1rem;
            }
        }
        
        /* Button sizing responsive */
        .btn-responsive {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        @media (min-width: 768px) {
            .btn-responsive {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }
        }

        /* Add smooth transitions for section changes */
        .section-content {
            transition: opacity 0.3s ease;
        }

        .section-content.hidden {
            display: none;
        }

        /* Medical records tab styles */
        .medical-records-tab.active {
            border-color: var(--primary-pink);
            color: var(--primary-pink);
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-pink);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Medical record cards styling */
        .medical-record-card {
            transition: all 0.3s ease;
        }

        .medical-record-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        /* calendar-styles.css */

        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-month-year {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }

        .calendar-nav-btn {
            background: transparent;
            border: none;
            color: #6366f1;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .calendar-nav-btn:hover {
            background: #e0e7ff;
        }

        .calendar-nav-btn svg {
            width: 20px;
            height: 20px;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 8px;
        }

        .calendar-weekdays div {
            text-align: center;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.875rem;
            padding: 8px 0;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            position: relative;
            background: #f9fafb;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .calendar-day .day-number {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .calendar-day.prev-month,
        .calendar-day.next-month {
            opacity: 0.3;
            pointer-events: none;
        }

        .calendar-day.today {
            background: #eff6ff;
            border-color: #3b82f6;
        }

        .calendar-day.today .day-number {
            color: #3b82f6;
            font-weight: 700;
        }

        .calendar-day.clickable {
            cursor: pointer;
            background: white;
            border-color: #e5e7eb;
        }

        .calendar-day.clickable:hover {
            background: #f3f4f6;
            border-color: #6366f1;
            transform: scale(1.05);
        }

        .calendar-day.selected {
            background: #6366f1 !important;
            border-color: #4f46e5 !important;
        }

        .calendar-day.selected .day-number {
            color: white !important;
        }

        .calendar-day.past-date {
            opacity: 0.5;
            pointer-events: none;
        }

        .calendar-day.unavailable-date {
            background: #fee2e2 !important;
            border-color: #fecaca;
            pointer-events: none;
        }

        .calendar-day.unavailable-date .day-number {
            color: #dc2626;
        }

        .calendar-day.non-working-day {
            background: #f3f4f6;
            opacity: 0.6;
        }

        .calendar-day.has-appointments {
            border-color: #10b981;
        }

        .appointment-indicator {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .unavailable-badge {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #dc2626;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 700;
        }

        .calendar-tooltip {
            position: fixed;
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.875rem;
            z-index: 1000;
            pointer-events: none;
            transform: translateX(-50%);
            white-space: nowrap;
        }

        .calendar-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #1f2937;
        }

        /* Appointment List Styles */
        .appointment-list-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            height: 100%;
            overflow-y: auto;
        }

        .appointment-list-header {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
        }

        .appointment-item {
            background: #f9fafb;
            border-left: 4px solid #6366f1;
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .appointment-item:hover {
            background: #f3f4f6;
            transform: translateX(4px);
        }

        .appointment-item-date {
            font-weight: 600;
            color: #6366f1;
            font-size: 0.875rem;
            margin-bottom: 4px;
        }

        .appointment-item-time {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }

        .appointment-item-patient {
            font-weight: 500;
            color: #374151;
            margin-bottom: 4px;
        }

        .appointment-item-type {
            display: inline-block;
            background: #e0e7ff;
            color: #4338ca;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 8px;
        }

        .appointment-item-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .appointment-item-status.scheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .appointment-item-status.confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .appointment-item-status.completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .appointment-item-status.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .no-appointments {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .no-appointments svg {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .calendar-container {
                padding: 12px;
            }
            
            .calendar-month-year {
                font-size: 1rem;
            }
            
            .calendar-days {
                gap: 4px;
            }
            
            .calendar-day .day-number {
                font-size: 0.75rem;
            }
            
            .appointment-indicator {
                width: 16px;
                height: 16px;
                font-size: 0.625rem;
            }
        }
    </style>
    <script src="assets/toast.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Button -->
    <div class="md:hidden fixed top-4 left-4 z-50">
        <button id="mobileMenuButton" class="bg-primary text-white p-3 rounded-lg shadow-lg">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>

    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar w-64 text-white">
            <div class="p-4 md:p-6">
                <div class="flex justify-between items-center mb-6 md:mb-8">
                    <h1 class="text-xl md:text-2xl font-inter font-bold">AlagApp</h1>
                    <button id="closeSidebar" class="md:hidden text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="mb-6 md:mb-8">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 md:w-12 md:h-12 bg-white/20 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 md:w-6 md:h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm md:text-base truncate"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="text-xs md:text-sm text-white/80 truncate"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                    </div>
                </div>
                
                <nav class="space-y-1 md:space-y-2">
                    <a href="#dashboard" onclick="showSection('dashboard')" class="flex items-center space-x-3 px-3 py-2 md:px-4 md:py-3 rounded-lg bg-white/20 text-sm md:text-base">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="#children" onclick="showSection('children')" class="flex items-center space-x-3 px-3 py-2 md:px-4 md:py-3 rounded-lg hover:bg-white/20 transition-colors text-sm md:text-base">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        <span>My Children</span>
                    </a>
                    
                    <a href="#appointments" onclick="showSection('appointments')" class="flex items-center space-x-3 px-3 py-2 md:px-4 md:py-3 rounded-lg hover:bg-white/20 transition-colors text-sm md:text-base">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Appointments</span>
                    </a>
                    
                    <a href="#vaccinations" onclick="showSection('vaccinations')" class="flex items-center space-x-3 px-3 py-2 md:px-4 md:py-3 rounded-lg hover:bg-white/20 transition-colors text-sm md:text-base">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Vaccinations</span>
                    </a>
                    
                    <a href="#settings" onclick="showSection('settings')" class="flex items-center space-x-3 px-3 py-2 md:px-4 md:py-3 rounded-lg hover:bg-white/20 transition-colors text-sm md:text-base">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Settings</span>
                    </a>
                </nav>

                <div class="mt-8 pt-8 border-t border-white/20">
                    <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H3zm10.293 9.707a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 101.414 1.414L9 10.414V16a1 1 0 102 0v-5.586l1.293 1.293z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Logout</span>
                    </a>
            </div>
        </div>
        
        
    </div>
        
        <!-- Main Content -->
        <div class="main-content flex-1 overflow-auto min-h-screen">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="section-content p-4 md:p-6 lg:p-8">
                <div class="mb-6 md:mb-8">
                    <h1 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">Dashboard</h1>
                    <p class="text-gray-600 responsive-text">Welcome to your pediatric care dashboard</p>
                </div>
                
                <!-- Quick Stats (3 clickable cards) -->
                <div class="stats-grid mb-6 md:mb-8">
                    <a href="#children" onclick="showSection('children')" class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6 block">
                        <div class="flex items-center">
                            <div class="p-2 md:p-3 rounded-full bg-primary/10">
                                <svg class="w-6 h-6 md:w-8 md:h-8 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3 md:ml-4">
                                <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo count($children); ?></div>
                                <div class="text-xs md:text-sm text-gray-600">Children</div>
                            </div>
                        </div>
                    </a>
                    
                    <a href="#appointments" onclick="showSection('appointments')" class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6 block">
                        <div class="flex items-center">
                            <div class="p-2 md:p-3 rounded-full bg-orange-100">
                                <svg class="w-6 h-6 md:w-8 md:h-8 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3 md:ml-4">
                                <?php
                                $upcoming_count = 0;
                                foreach ($appointments as $apt) {
                                    if (in_array($apt['status'], ['SCHEDULED', 'CONFIRMED'])) {
                                        $upcoming_count++;
                                    }
                                }
                                ?>
                                <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $upcoming_count; ?></div>
                                <div class="text-xs md:text-sm text-gray-600">Upcoming Appointments</div>
                            </div>
                        </div>
                    </a>
                    
                    <a href="#vaccinations" onclick="showSection('vaccinations')" class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6 block">
                        <div class="flex items-center">
                            <div class="p-2 md:p-3 rounded-full bg-green-100">
                                <svg class="w-6 h-6 md:w-8 md:h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3 md:ml-4">
                                <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo count($vaccinations); ?></div>
                                <div class="text-xs md:text-sm text-gray-600">Vaccinations</div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="content-grid">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Recent Appointments</h3>
                        <div class="space-y-3 md:space-y-4">
                            <?php if (count($appointments) > 0): ?>
                                <?php foreach (array_slice($appointments, 0, 5) as $appointment): ?>
                                    <div class="flex items-center justify-between p-3 md:p-4 bg-gray-50 rounded-lg">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-gray-800 text-sm md:text-base truncate">
                                                <?php echo htmlspecialchars($appointment['child_first_name'] . ' ' . $appointment['child_last_name']); ?>
                                            </div>
                                            <div class="text-xs md:text-sm text-gray-600 truncate"><?php echo htmlspecialchars($appointment['type']); ?></div>
                                            <div class="text-xs md:text-sm text-gray-500 truncate">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                            </div>
                                        </div>
                                        <div class="text-right ml-2">
                                            <div class="text-xs md:text-sm font-medium <?php echo getStatusColor($appointment['status']); ?>">
                                                <?php echo htmlspecialchars($appointment['status']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm md:text-base">No recent appointments</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Recent Vaccinations</h3>
                        <div class="space-y-3 md:space-y-4">
                            <?php if (count($vaccinations) > 0): ?>
                                <?php foreach (array_slice($vaccinations, 0, 5) as $vaccination): ?>
                                    <div class="flex items-center justify-between p-3 md:p-4 bg-green-50 rounded-lg">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-gray-800 text-sm md:text-base truncate">
                                                <?php echo htmlspecialchars($vaccination['child_first_name'] . ' ' . $vaccination['child_last_name']); ?>
                                            </div>
                                            <div class="text-xs md:text-sm text-gray-600 truncate"><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></div>
                                            <div class="text-xs md:text-sm text-gray-500 truncate">
                                                Dose <?php echo htmlspecialchars($vaccination['dose_number']); ?>
                                            </div>
                                        </div>
                                        <div class="text-right ml-2">
                                            <div class="text-xs md:text-sm font-medium text-green-600">Completed</div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('M j, Y', strtotime($vaccination['administration_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm md:text-base">No vaccination records</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Children Section -->
            <div id="children-section" class="section-content p-4 md:p-6 lg:p-8 hidden">
                <div class="mb-6 md:mb-8">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">My Children</h1>
                            <p class="text-gray-600 responsive-text">Manage your children's health profiles</p>
                        </div>
                        <button onclick="openAddChildModal()" class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive w-full sm:w-auto">
                            Add Child
                        </button>
                    </div>
                </div>
                
                <div class="children-grid">
                    <?php if (count($children) > 0): ?>
                        <?php foreach ($children as $child): ?>
                            <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 md:w-16 md:h-16 bg-primary/10 rounded-full flex items-center justify-center mr-3 md:mr-4">
                                        <svg class="w-6 h-6 md:w-8 md:h-8 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 truncate">
                                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                        </h3>
                                        <p class="text-gray-600 text-sm md:text-base">
                                            Born <?php echo date('M j, Y', strtotime($child['date_of_birth'])); ?>
                                        </p>
                                    </div>
                                    
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 text-sm md:text-base">Gender:</span>
                                        <span class="font-medium text-sm md:text-base"><?php echo htmlspecialchars($child['gender']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 text-sm md:text-base">Blood Type:</span>
                                        <span class="font-medium text-sm md:text-base"><?php echo htmlspecialchars($child['blood_type'] ?: 'Not specified'); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 text-sm md:text-base">Height:</span>
                                        <span class="font-medium text-sm md:text-base"><?php echo $child['height'] ? $child['height'] . ' cm' : 'Not recorded'; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 text-sm md:text-base">Weight:</span>
                                        <span class="font-medium text-sm md:text-base"><?php echo $child['weight'] ? $child['weight'] . ' kg' : 'Not recorded'; ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($child['allergies']): ?>
                                    <div class="mb-4">
                                        <div class="text-sm font-medium text-gray-700 mb-1">Allergies:</div>
                                        <div class="text-sm text-red-600"><?php echo htmlspecialchars($child['allergies']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($child['medical_conditions']): ?>
                                    <div class="mb-4">
                                        <div class="text-sm font-medium text-gray-700 mb-1">Medical Conditions:</div>
                                        <div class="text-sm text-orange-600"><?php echo htmlspecialchars($child['medical_conditions']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <!-- Files list (place inside each child card) -->
                                <div class="mt-4">
                                    <div class="text-sm font-medium text-gray-700 mb-2">Files</div>
                                    <?php if (!empty($child['files'])): ?>
                                        <ul class="space-y-2 text-sm">
                                            <?php foreach ($child['files'] as $cf): ?>
                                                <li class="flex items-center justify-between bg-gray-50 p-2 rounded">
                                                    <div class="truncate max-w-xs">
                                                        <strong><?php echo htmlspecialchars($cf['original_filename']); ?></strong>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($cf['mime_type']); ?> • <?php echo round($cf['file_size']/1024,1) . ' KB'; ?></div>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <a href="download_file.php?file_id=<?php echo $cf['id']; ?>" class="text-sm text-blue-600 hover:underline">Download</a>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <div class="text-xs text-gray-500">No files uploaded</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Upload button -->
                                <div class="mt-3">
                                    <button onclick="openUploadModal(<?php echo $child['id']; ?>, '<?php echo htmlspecialchars(addslashes($child['first_name'] . ' ' . $child['last_name'])); ?>')" 
                                        class="w-full bg-indigo-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-indigo-700">
                                        Upload File
                                    </button>
                                </div>
                                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                    <button onclick="openMedicalRecords(<?php echo $child['id']; ?>, '<?php echo htmlspecialchars(addslashes($child['first_name'] . ' ' . $child['last_name'])); ?>')" 
                                            class="flex-1 bg-blue-100 text-blue-700 px-3 py-2 md:px-4 md:py-2 rounded-lg font-medium hover:bg-blue-200 transition-colors text-sm md:text-base">
                                        Medical Records
                                    </button>
                                    <button onclick="viewChildVaccinations(<?php echo $child['id']; ?>)" 
                                            class="flex-1 bg-green-100 text-green-700 px-3 py-2 md:px-4 md:py-2 rounded-lg font-medium hover:bg-green-200 transition-colors text-sm md:text-base">
                                        Vaccinations
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-8 md:py-12">
                            <div class="text-gray-500 mb-4 text-sm md:text-base">No children added yet</div>
                            <button onclick="openAddChildModal()" class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive">
                                Add Your First Child
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
           <!-- Appointments Section -->
            <div id="appointments-section" class="section-content p-4 md:p-6 lg:p-8 hidden">
                <div class="mb-6 md:mb-8">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">Appointments</h1>
                            <p class="text-gray-600 responsive-text">Schedule and manage your children's appointments</p>
                        </div>
                        <button onclick="openBookAppointmentModal()" 
                                class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive w-full sm:w-auto">
                            Book New Appointment
                        </button>
                    </div>
                </div>

                <!-- Calendar and Appointments Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Left Side: Appointment List -->
                    <div class="lg:col-span-1">
                        <div class="appointment-list-container">
                            <div class="appointment-list-header">
                                <h3>Upcoming Appointments</h3>
                            </div>
                            <div id="appointmentsList">
                                <?php if (count($appointments) > 0): ?>
                                    <?php 
                                    $upcoming_count = 0;
                                    foreach ($appointments as $appointment): 
                                        if (in_array($appointment['status'], ['SCHEDULED', 'CONFIRMED'])): 
                                            $upcoming_count++;
                                    ?>
                                        <div class="appointment-item">
                                            <div class="appointment-item-date">
                                                <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                            </div>
                                            <div class="appointment-item-time">
                                                ⏰ <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                            </div>
                                            <div class="appointment-item-patient">
                                                <?php echo htmlspecialchars($appointment['child_first_name'] . ' ' . $appointment['child_last_name']); ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="appointment-item-type"><?php echo htmlspecialchars($appointment['type']); ?></span>
                                                <span class="appointment-item-status <?php echo strtolower($appointment['status']); ?>">
                                                    <?php echo htmlspecialchars($appointment['status']); ?>
                                                </span>
                                            </div>
                                            <div class="text-sm text-gray-600 mt-2">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                            </div>
                                            <?php if ($appointment['status'] === 'SCHEDULED'): ?>
                                                <form method="POST" class="mt-3">
                                                    <input type="hidden" name="cancel_appointment" value="1">
                                                    <input type="hidden" name="cancel_appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <button type="submit" 
                                                            onclick="return confirm('Are you sure you want to cancel this appointment?')"
                                                            class="text-xs text-red-600 hover:text-red-800 font-medium">
                                                        Cancel Appointment
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    
                                    if ($upcoming_count == 0): ?>
                                        <div class="no-appointments">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <p>No upcoming appointments</p>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="no-appointments">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <p>No appointments scheduled</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Calendar -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Doctor</label>
                                <select id="doctorSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <option value="">-- Select a Doctor --</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            <?php if ($doctor['specialization']): ?>
                                                - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="appointmentCalendar"></div>
                            
                            <div class="mt-6">
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 rounded bg-blue-100 border-2 border-blue-500 mr-2"></div>
                                        <span class="text-gray-700">Today</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 rounded bg-green-50 border-2 border-green-500 mr-2"></div>
                                        <span class="text-gray-700">Has Appointments</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 rounded bg-red-100 border-red-200 mr-2"></div>
                                        <span class="text-gray-700">Doctor Unavailable</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 rounded bg-gray-100 mr-2"></div>
                                        <span class="text-gray-700">Non-Working Day</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Appointment History -->
                <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                    <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Appointment History</h3>
                    <?php if (count($appointments) > 0): ?>
                        <div class="table-container">
                            <table class="w-full min-w-max">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Date & Time</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Child</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Doctor</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Type</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($appointments, 0, 10) as $appointment): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <div class="font-medium text-gray-800 text-xs md:text-sm">
                                                    <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <div class="font-medium text-gray-800 text-xs md:text-sm">
                                                    <?php echo htmlspecialchars($appointment['child_first_name'] . ' ' . $appointment['child_last_name']); ?>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <div class="text-xs md:text-sm text-gray-700">
                                                    Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <span class="text-xs md:text-sm"><?php echo htmlspecialchars($appointment['type']); ?></span>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getStatusBadge($appointment['status']); ?>">
                                                    <?php echo htmlspecialchars($appointment['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 md:py-12">
                            <div class="text-gray-500 mb-4 text-sm md:text-base">No appointment history</div>
                            <button onclick="openBookAppointmentModal()" class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive">
                                Schedule Your First Appointment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Vaccinations Section -->
            <div id="vaccinations-section" class="section-content p-4 md:p-6 lg:p-8 hidden">
                <div class="mb-6 md:mb-8">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">Vaccinations</h1>
                            <p class="text-gray-600 responsive-text">Track your children's immunization records</p>
                        </div>
                        <button onclick="openVaccinationInfoModal()" class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive w-full sm:w-auto">
                            Vaccine Schedule Info
                        </button>
                    </div>
                </div>

                <!-- Vaccination Statistics -->
                <div class="stats-grid mb-6 md:mb-8">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <div class="flex items-center">
                            <div class="p-2 md:p-3 rounded-full bg-green-100">
                                <svg class="w-6 h-6 md:w-8 md:h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3 md:ml-4">
                                <?php
                                $completed_count = 0;
                                foreach ($vaccinations as $vax) {
                                    if ($vax['status'] === 'COMPLETED') {
                                        $completed_count++;
                                    }
                                }
                                ?>
                                <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $completed_count; ?></div>
                                <div class="text-xs md:text-sm text-gray-600">Completed Vaccinations</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <div class="flex items-center">
                            <div class="p-2 md:p-3 rounded-full bg-blue-100">
                                <svg class="w-6 h-6 md:w-8 md:h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3 md:ml-4">
                                <?php
                                $scheduled_count = 0;
                                foreach ($vaccinations as $vax) {
                                    if ($vax['status'] === 'SCHEDULED') {
                                        $scheduled_count++;
                                    }
                                }
                                ?>
                                <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $scheduled_count; ?></div>
                                <div class="text-xs md:text-sm text-gray-600">Scheduled Vaccinations</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <div class="flex items-center">
                            <div class="p-2 md:p-3 rounded-full bg-orange-100">
                                <svg class="w-6 h-6 md:w-8 md:h-8 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3 md:ml-4">
                                <?php
                                $overdue_count = 0;
                                foreach ($vaccinations as $vax) {
                                    if ($vax['status'] === 'OVERDUE') {
                                        $overdue_count++;
                                    }
                                }
                                ?>
                                <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $overdue_count; ?></div>
                                <div class="text-xs md:text-sm text-gray-600">Overdue Vaccinations</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vaccination Records -->
                <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6 mb-6 md:mb-8">
                    <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Vaccination Records</h3>
                    
                    <?php if (count($vaccinations) > 0): ?>
                        <div class="table-container">
                            <table class="w-full min-w-max">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Child Name</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Vaccine</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Dose</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Date</th>
                                        <th class="text-left py-2 px-2 md:py-3 md:px-4 font-semibold text-gray-700 text-xs md:text-sm">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vaccinations as $vaccination): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <div class="font-medium text-gray-800 text-xs md:text-sm truncate max-w-[100px] md:max-w-none">
                                                    <?php echo htmlspecialchars($vaccination['child_first_name'] . ' ' . $vaccination['child_last_name']); ?>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <div class="font-medium text-gray-800 text-xs md:text-sm truncate max-w-[100px] md:max-w-none"><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></div>
                                                <div class="text-xs text-gray-500">
                                                    <span class="px-1 py-0.5 text-xs rounded-full <?php echo getVaccineTypeBadge($vaccination['vaccine_type'] ?? 'ROUTINE'); ?>">
                                                        <?php echo htmlspecialchars($vaccination['vaccine_type'] ?? 'ROUTINE'); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <?php if ($vaccination['dose_number']): ?>
                                                    <span class="font-medium text-xs md:text-sm">Dose <?php echo htmlspecialchars($vaccination['dose_number']); ?></span>
                                                    <?php if ($vaccination['total_doses']): ?>
                                                        <span class="text-xs text-gray-500">of <?php echo htmlspecialchars($vaccination['total_doses']); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-gray-500 text-xs md:text-sm">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <?php if ($vaccination['administration_date']): ?>
                                                    <div class="text-xs md:text-sm font-medium text-gray-800">
                                                        <?php echo date('M j, Y', strtotime($vaccination['administration_date'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-500 text-xs md:text-sm">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2 px-2 md:py-3 md:px-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getVaccinationStatusBadge($vaccination['status'] ?? 'COMPLETED'); ?>">
                                                    <?php echo htmlspecialchars($vaccination['status'] ?? 'COMPLETED'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 md:py-12">
                            <div class="text-gray-500 mb-4 text-sm md:text-base">No vaccination records found</div>
                            <p class="text-gray-600 mb-6 text-sm md:text-base">Your children's vaccination records will appear here once they are added by healthcare providers.</p>
                            <button onclick="openBookAppointmentModal()" class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive">
                                Schedule Vaccination Appointment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Vaccine Schedule Information -->
                <div class="content-grid">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Common Childhood Vaccines</h3>
                        <div class="space-y-2 md:space-y-3">
                            <?php foreach ($common_vaccines as $name => $details): ?>
                                <div class="flex justify-between items-center p-2 md:p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-800 text-sm md:text-base truncate"><?php echo htmlspecialchars($name); ?></div>
                                        <div class="text-xs md:text-sm text-gray-600 truncate"><?php echo htmlspecialchars($details['description']); ?></div>
                                    </div>
                                    <div class="text-xs md:text-sm font-medium text-gray-700 ml-2">
                                        <?php echo is_numeric($details['doses']) ? $details['doses'] . ' doses' : $details['doses']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Vaccine Needs by Child</h3>
                        <div class="space-y-3 md:space-y-4">
                            <?php if (count($children) > 0): ?>
                                <?php foreach ($children as $child): ?>
                                    <?php
                                    $needs = $vaccine_needs_by_child[$child['id']] ?? [];
                                    ?>
                                    <div class="p-3 md:p-4 border border-gray-200 rounded-lg">
                                        <div class="flex justify-between items-center mb-3">
                                            <h4 class="font-semibold text-gray-800 text-sm md:text-base truncate flex-1 mr-2">
                                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                            </h4>
                                            <div class="text-xs md:text-sm text-gray-600">
                                                Born <?php echo date('M j, Y', strtotime($child['date_of_birth'])); ?>
                                            </div>
                                        </div>

                                        <?php if (empty($needs)): ?>
                                            <div class="text-sm text-gray-500">No vaccine needs recorded for this child.</div>
                                        <?php else: ?>
                                            <div class="space-y-2">
                                                <?php foreach ($needs as $n): ?>
                                                    <div class="flex items-start justify-between bg-gray-50 p-2 md:p-3 rounded">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-medium text-gray-800 text-sm md:text-base truncate">
                                                                <?php echo htmlspecialchars($n['vaccine_name']); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <?php
                                                                    $rec = $n['recommended_date'] && $n['recommended_date'] !== '0000-00-00'
                                                                        ? date('M j, Y', strtotime($n['recommended_date']))
                                                                        : ($n['created_at'] ? date('M j, Y', strtotime($n['created_at'])) : '-');
                                                                    echo 'Recommended: ' . $rec;
                                                                ?>
                                                            </div>
                                                            <?php if (!empty($n['notes'])): ?>
                                                                <div class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($n['notes']); ?></div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="ml-3 flex flex-col items-end">
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getVaccineNeedBadge($n['status'] ?? 'RECOMMENDED'); ?>">
                                                                <?php echo htmlspecialchars($n['status'] ?? 'RECOMMENDED'); ?>
                                                            </span>
                                                            <?php if (!empty($n['updated_at'])): ?>
                                                                <div class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($n['updated_at'])); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm md:text-base">No children registered</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Section -->
            <div id="settings-section" class="section-content p-4 md:p-6 lg:p-8 hidden">
                <div class="mb-6 md:mb-8">
                    <h1 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">Settings</h1>
                    <p class="text-gray-600 responsive-text">View your account information</p>
                </div>
                
                <div class="content-grid">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Profile Information</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="form-grid">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="profile_first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required minlength="1" maxlength="100"
                                        class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="profile_last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required minlength="1" maxlength="100"
                                        class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                                    <input type="email" name="profile_email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required maxlength="150"
                                        class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number <span class="text-red-500">*</span></label>
                                    <input type="tel" name="profile_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required pattern="[0-9+\-\s()]{7,20}" maxlength="20"
                                        class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                                    <input type="date" name="profile_dob" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>"
                                        class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                    <select name="profile_gender"
                                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                        <?php $cur_gender = strtoupper((string)($user['gender'] ?? '')); ?>
                                        <option value="">Prefer not to say</option>
                                        <option value="MALE"   <?php if ($cur_gender === 'MALE')   echo 'selected'; ?>>Male</option>
                                        <option value="FEMALE" <?php if ($cur_gender === 'FEMALE') echo 'selected'; ?>>Female</option>
                                        <option value="OTHER"  <?php if ($cur_gender === 'OTHER')  echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea name="profile_address" rows="2" maxlength="255"
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="pt-2 border-t border-gray-200">
                                <h4 class="text-md font-inter font-semibold text-gray-800 mb-3 mt-2">Emergency Contact</h4>
                                <div class="form-grid">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Name</label>
                                        <input type="text" name="profile_emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>" maxlength="100"
                                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                                        <input type="tel" name="profile_emergency_contact_phone" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>" pattern="[0-9+\-\s()]{0,20}" maxlength="20"
                                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive w-full">
                                Save Changes
                            </button>
                        </form>
                    </div>

                    <div class="card-hover bg-white rounded-xl shadow-lg p-4 md:p-6">
                        <h3 class="text-lg md:text-xl font-inter font-semibold text-gray-800 mb-4">Change Password</h3>
                        <form method="POST" class="space-y-4" onsubmit="return validateParentPasswordForm(this);">
                            <input type="hidden" name="change_password" value="1">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password <span class="text-red-500">*</span></label>
                                <input type="password" name="current_password" required minlength="1" autocomplete="current-password"
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                            </div>
                            <div class="form-grid">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password <span class="text-red-500">*</span></label>
                                    <input type="password" name="new_password" required minlength="8" autocomplete="new-password"
                                        class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password <span class="text-red-500">*</span></label>
                                    <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"
                                        class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">Password must be at least 8 characters.</p>
                            <button type="submit" class="btn-primary text-white px-4 py-2 md:px-6 md:py-3 rounded-lg font-semibold btn-responsive w-full">
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Child Modal -->
    <div id="addChildModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl max-h-screen overflow-y-auto">
            <div class="p-4 md:p-6 lg:p-8">
                <div class="text-center mb-6 md:mb-8">
                    <h2 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">Add Child</h2>
                    <p class="text-gray-600 responsive-text">Add your child's health profile</p>
                </div>
                
                <form method="POST" class="space-y-4 md:space-y-6">
                    <input type="hidden" name="add_child" value="1">
                    
                    <div class="form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="child_first_name" required 
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="child_last_name" required 
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                            <input type="date" name="child_dob" required 
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                            <select name="child_gender" required 
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                <option value="">Select gender</option>
                                <option value="MALE">Male</option>
                                <option value="FEMALE">Female</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Blood Type</label>
                            <select name="child_blood_type" 
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                <option value="">Select blood type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Height (cm)</label>
                            <input type="number" step="0.1" min="10" max="250" name="child_height" inputmode="decimal"
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base"
                                placeholder="e.g., 120.5">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weight (kg)</label>
                            <input type="number" step="0.1" min="0.5" max="300" name="child_weight" inputmode="decimal"
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base"
                                placeholder="e.g., 25.5">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Known Allergies</label>
                        <textarea name="child_allergies" rows="3" 
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base"
                                placeholder="List any known allergies..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Medical Conditions</label>
                        <textarea name="child_medical_conditions" rows="3" 
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base"
                                placeholder="Any relevant medical conditions..."></textarea>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                        <button type="submit" class="flex-1 btn-primary text-white py-2 md:py-3 rounded-lg font-semibold text-sm md:text-base">
                            Add Child
                        </button>
                        <button type="button" onclick="closeAddChildModal()" 
                                class="flex-1 border border-gray-300 text-gray-700 py-2 md:py-3 rounded-lg font-semibold hover:bg-gray-50 text-sm md:text-base">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    

    <!-- Book Appointment Modal -->
    <div id="bookAppointmentModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl max-h-screen overflow-y-auto">
            <div class="p-4 md:p-6 lg:p-8">
                <div class="text-center mb-6 md:mb-8">
                    <h2 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">Book Appointment</h2>
                    <p class="text-gray-600 responsive-text">Schedule a new appointment for your child</p>
                </div>
                
                <form method="POST" id="bookAppointmentForm" class="space-y-4 md:space-y-6">
                    <input type="hidden" name="book_appointment" value="1">
                    
                    <div class="form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Child <span class="text-red-500">*</span></label>
                            <select name="appointment_child" id="modalAppointmentChild" required 
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                <option value="">-- Select Child --</option>
                                <?php foreach ($children as $child): ?>
                                    <option value="<?php echo $child['id']; ?>">
                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Doctor <span class="text-red-500">*</span></label>
                            <select name="appointment_doctor" id="modalAppointmentDoctor" required 
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                <option value="">-- Select Doctor --</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                        <?php if ($doctor['specialization']): ?>
                                            (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Date <span class="text-red-500">*</span></label>
                            <input type="date" id="modalAppointmentDate" name="appointment_date" required 
                                min="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Time <span class="text-red-500">*</span></label>
                            <select name="appointment_time" id="modalAppointmentTime" required 
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                <option value="">-- Select Time --</option>
                                <option value="09:00:00">09:00 AM</option>
                                <option value="09:30:00">09:30 AM</option>
                                <option value="10:00:00">10:00 AM</option>
                                <option value="10:30:00">10:30 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="11:30:00">11:30 AM</option>
                                <option value="13:00:00">01:00 PM</option>
                                <option value="13:30:00">01:30 PM</option>
                                <option value="14:00:00">02:00 PM</option>
                                <option value="14:30:00">02:30 PM</option>
                                <option value="15:00:00">03:00 PM</option>
                                <option value="15:30:00">03:30 PM</option>
                                <option value="16:00:00">04:00 PM</option>
                                <option value="16:30:00">04:30 PM</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Service Type <span class="text-red-500">*</span></label>
                            <select name="appointment_service" required 
                                    class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base">
                                <option value="CONSULTATION">General Consultation</option>
                                <option value="VACCINATION">Vaccination</option>
                                <option value="CHECKUP">Regular Checkup</option>
                                <option value="FOLLOW_UP">Follow-up Visit</option>
                                <option value="URGENT">Urgent Care</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes / Symptoms</label>
                        <textarea name="appointment_notes" rows="3" 
                                class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm md:text-base"
                                placeholder="Please describe any symptoms or specific concerns..."></textarea>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                        <button type="submit" class="flex-1 btn-primary text-white py-2 md:py-3 rounded-lg font-semibold text-sm md:text-base">
                            Book Appointment
                        </button>
                        <button type="button" onclick="closeBookAppointmentModal()" 
                                class="flex-1 border border-gray-300 text-gray-700 py-2 md:py-3 rounded-lg font-semibold hover:bg-gray-50 text-sm md:text-base">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Vaccination Info Modal -->
    <div id="vaccinationInfoModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl max-h-screen overflow-y-auto">
            <div class="p-4 md:p-6 lg:p-8">
                <div class="text-center mb-6 md:mb-8">
                    <h2 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2">Childhood Vaccination Schedule</h2>
                    <p class="text-gray-600 responsive-text">Recommended immunization schedule for children</p>
                </div>
                
                <div class="space-y-4 md:space-y-6">
                    <!-- Vaccine schedule content (same as earlier) -->
                    <div class="border-l-4 border-green-500 pl-3 md:pl-4">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2">At Birth</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center p-2 md:p-3 bg-green-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">BCG</div>
                                    <div class="text-xs md:text-sm text-gray-600">Tuberculosis prevention</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Required</span>
                            </div>
                            <div class="flex justify-between items-center p-2 md:p-3 bg-green-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">Hepatitis B</div>
                                    <div class="text-xs md:text-sm text-gray-600">First dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Required</span>
                            </div>
                        </div>
                    </div>

                    <!-- 6 Weeks -->
                    <div class="border-l-4 border-blue-500 pl-3 md:pl-4">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2">6 Weeks</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center p-2 md:p-3 bg-blue-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">DPT</div>
                                    <div class="text-xs md:text-sm text-gray-600">First dose - Diphtheria, Pertussis, Tetanus</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Required</span>
                            </div>
                            <div class="flex justify-between items-center p-2 md:p-3 bg-blue-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">Polio</div>
                                    <div class="text-xs md:text-sm text-gray-600">First dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Required</span>
                            </div>
                        </div>
                    </div>

                    <!-- 10 Weeks -->
                    <div class="border-l-4 border-purple-500 pl-3 md:pl-4">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2">10 Weeks</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center p-2 md:p-3 bg-purple-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">DPT</div>
                                    <div class="text-xs md:text-sm text-gray-600">Second dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">Required</span>
                            </div>
                            <div class="flex justify-between items-center p-2 md:p-3 bg-purple-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">Polio</div>
                                    <div class="text-xs md:text-sm text-gray-600">Second dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">Required</span>
                            </div>
                        </div>
                    </div>

                    <!-- 14 Weeks -->
                    <div class="border-l-4 border-orange-500 pl-3 md:pl-4">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2">14 Weeks</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center p-2 md:p-3 bg-orange-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">DPT</div>
                                    <div class="text-xs md:text-sm text-gray-600">Third dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-full">Required</span>
                            </div>
                            <div class="flex justify-between items-center p-2 md:p-3 bg-orange-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">Polio</div>
                                    <div class="text-xs md:text-sm text-gray-600">Third dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-full">Required</span>
                            </div>
                        </div>
                    </div>

                    <!-- 6-9 Months -->
                    <div class="border-l-4 border-red-500 pl-3 md:pl-4">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2">6-9 Months</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center p-2 md:p-3 bg-red-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">Vitamin A</div>
                                    <div class="text-xs md:text-sm text-gray-600">First dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Recommended</span>
                            </div>
                        </div>
                    </div>

                    <!-- 9-12 Months -->
                    <div class="border-l-4 border-indigo-500 pl-3 md:pl-4">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2">9-12 Months</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center p-2 md:p-3 bg-indigo-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm md:text-base">Measles</div>
                                    <div class="text-xs md:text-sm text-gray-600">First dose</div>
                                </div>
                                <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full">Required</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 md:mt-8 p-3 md:p-4 bg-yellow-50 rounded-lg">
                    <h4 class="font-semibold text-yellow-800 mb-2 text-sm md:text-base">Important Notes:</h4>
                    <ul class="text-xs md:text-sm text-yellow-700 list-disc list-inside space-y-1">
                        <li>This is a general guideline - actual schedule may vary based on health conditions</li>
                        <li>Consult with your pediatrician for personalized vaccination schedule</li>
                        <li>Keep vaccination records safe for school admission and travel</li>
                        <li>Report any adverse reactions to your healthcare provider immediately</li>
                    </ul>
                </div>

                <div class="flex justify-center mt-6 md:mt-8">
                    <button type="button" onclick="closeVaccinationInfoModal()" 
                            class="btn-primary text-white px-6 md:px-8 py-2 md:py-3 rounded-lg font-semibold text-sm md:text-base">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div id="uploadFileModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl max-h-screen overflow-y-auto">
            <div class="p-4 md:p-6 lg:p-8">
                <div class="text-center mb-6">
                    <h2 id="uploadModalTitle" class="text-2xl font-bold text-gray-800">Upload File</h2>
                    <p id="uploadModalChildInfo" class="text-gray-600 text-sm">Upload a file for the child</p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="upload_patient_file" value="1">
                    <input type="hidden" name="upload_patient_id" id="upload_patient_id" value="">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select file</label>
                        <input type="file" name="patient_file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full">
                        <p class="text-xs text-gray-500 mt-1">Allowed: pdf, jpg, png, docx. Max 5 MB.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                        <textarea name="upload_notes" rows="3" class="w-full border rounded p-2 text-sm" placeholder="Describe the document (e.g., lab result, referral)..."></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded">Upload</button>
                        <button type="button" onclick="closeUploadModal()" class="flex-1 border border-gray-300 px-4 py-2 rounded">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Medical Records Modal -->
    <div id="medicalRecordsModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl max-h-screen overflow-y-auto w-full max-w-4xl">
            <div class="p-4 md:p-6 lg:p-8">
                <div class="flex justify-between items-center mb-6 md:mb-8">
                    <div>
                        <h2 class="text-2xl md:text-3xl font-inter font-bold text-gray-800 mb-2" id="medicalRecordsTitle">Medical Records</h2>
                        <p class="text-gray-600 responsive-text" id="medicalRecordsChildInfo"></p>
                    </div>
                    <button type="button" onclick="closeMedicalRecordsModal()" 
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Tabs for different record types -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="flex space-x-8">
                        <button onclick="showMedicalRecordsTab('consultations')" 
                                class="py-2 px-1 border-b-2 font-medium text-sm md:text-base medical-records-tab active" data-tab="consultations">
                            Consultations
                        </button>
                        <button onclick="showMedicalRecordsTab('prescriptions')" 
                                class="py-2 px-1 border-b-2 font-medium text-sm md:text-base text-gray-500 hover:text-gray-700 hover:border-gray-300 medical-records-tab" data-tab="prescriptions">
                            Prescriptions
                        </button>
                        <button onclick="showMedicalRecordsTab('vaccinations')" 
                                class="py-2 px-1 border-b-2 font-medium text-sm md:text-base text-gray-500 hover:text-gray-700 hover:border-gray-300 medical-records-tab" data-tab="vaccinations">
                            Vaccinations
                        </button>
                    </nav>
                </div>
                
                <!-- Consultations Tab Content -->
                <div id="consultations-tab" class="medical-records-content">
                    <div class="space-y-4" id="consultations-content">
                        <!-- Consultations will be loaded here -->
                    </div>
                </div>
                
                <!-- Prescriptions Tab Content -->
                <div id="prescriptions-tab" class="medical-records-content hidden">
                    <div class="space-y-4" id="prescriptions-content">
                        <!-- Prescriptions will be loaded here -->
                    </div>
                </div>
                
                <!-- Vaccinations Tab Content -->
                <div id="vaccinations-tab" class="medical-records-content hidden">
                    <div class="space-y-4" id="vaccinations-content">
                        <!-- Vaccinations will be loaded here -->
                    </div>
                </div>
                
                <div class="mt-6 md:mt-8 text-center">
                    <button type="button" onclick="closeMedicalRecordsModal()" 
                            class="btn-primary text-white px-6 md:px-8 py-2 md:py-3 rounded-lg font-semibold text-sm md:text-base">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let parentAppointmentCalendar = null;
        let currentDoctorId = null;
        // appointmentsCache stores appointments loaded for the current doctor/month
        let appointmentsCache = [];

        document.addEventListener('DOMContentLoaded', function() {
            const doctorSelect = document.getElementById('doctorSelect');
            const modalDoctorSelect = document.getElementById('modalAppointmentDoctor');

            if (doctorSelect) {
                doctorSelect.addEventListener('change', function() {
                    currentDoctorId = this.value;
                    // Sync modal doctor select to the main doctor select if modal is open
                    if (modalDoctorSelect) modalDoctorSelect.value = currentDoctorId;
                    if (currentDoctorId) {
                        loadDoctorAvailability(currentDoctorId);
                    } else {
                        // If no doctor selected, destroy calendar
                        if (parentAppointmentCalendar) {
                            parentAppointmentCalendar.setUnavailableDates([]);
                            parentAppointmentCalendar.setWorkingDays([]);
                            parentAppointmentCalendar.setAppointments([]);
                        }
                    }
                });
            }

            // Also sync modal doctor change to the calendar
            const modalDoctor = document.getElementById('modalAppointmentDoctor');
            if (modalDoctor) {
                modalDoctor.addEventListener('change', function() {
                    const id = this.value;
                    if (id) {
                        // set main doctor select too
                        const mainSelect = document.getElementById('doctorSelect');
                        if (mainSelect) mainSelect.value = id;
                        currentDoctorId = id;
                        loadDoctorAvailability(id);
                    }
                });
            }
            
            // Load initial appointments for the current month (parent's appointments)
            loadParentAppointments();
        });

        /**
         * Load doctor availability and appointments for the month/year.
         * Expected response from get_availability.php (not implemented here).
         */
        /* Replace the existing loadDoctorAvailability function in parent-dashboard.php with this version */
        function loadDoctorAvailability(doctorId) {
            if (!doctorId) return;
            const month = parentAppointmentCalendar ? parentAppointmentCalendar.currentDate.getMonth() + 1 : new Date().getMonth() + 1;
            const year = parentAppointmentCalendar ? parentAppointmentCalendar.currentDate.getFullYear() : new Date().getFullYear();
            
            fetch(`get_availability.php?doctor_id=${encodeURIComponent(doctorId)}&month=${month}&year=${year}`, {
                method: 'GET',
                credentials: 'same-origin' // ensure session cookie is sent
            })
            .then(async response => {
                const text = await response.text();
                // Try to parse JSON, but log raw text if it fails
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        appointmentsCache = data.appointments || [];
                        if (!parentAppointmentCalendar) {
                            parentAppointmentCalendar = new AppointmentCalendar('appointmentCalendar', {
                                onDateSelect: handleDateSelect,
                                unavailableDates: data.unavailable_dates || [],
                                workingDays: data.working_days || [],
                                appointments: appointmentsCache,
                                userType: 'parent',
                                doctorId: doctorId,
                                onMonthChange: (m, y) => {
                                    fetch(`get_availability.php?doctor_id=${encodeURIComponent(doctorId)}&month=${m}&year=${y}`, { credentials: 'same-origin' })
                                        .then(async res => {
                                            const t = await res.text();
                                            try {
                                                const r = JSON.parse(t);
                                                if (r.success) {
                                                    appointmentsCache = r.appointments || [];
                                                    parentAppointmentCalendar.setUnavailableDates(r.unavailable_dates || []);
                                                    parentAppointmentCalendar.setWorkingDays(r.working_days || []);
                                                    parentAppointmentCalendar.setAppointments(appointmentsCache);
                                                } else {
                                                    console.error('Availability error:', r);
                                                }
                                            } catch (err) {
                                                console.error('Invalid JSON on month change (get_availability.php):', t);
                                            }
                                        })
                                        .catch(err => console.error('Error loading availability month change:', err));
                                }
                            });
                        } else {
                            parentAppointmentCalendar.setUnavailableDates(data.unavailable_dates || []);
                            parentAppointmentCalendar.setWorkingDays(data.working_days || []);
                            parentAppointmentCalendar.setAppointments(appointmentsCache);
                        }
                    } else {
                        console.error('get_availability returned success=false:', data);
                    }
                } catch (err) {
                    // Log raw response so you can see the PHP error/HTML
                    console.error('Invalid JSON from get_availability.php:', text);
                    throw err;
                }
            })
            .catch(error => console.error('Error loading availability:', error));
        }

        function loadParentAppointments() {
            const month = new Date().getMonth() + 1;
            const year = new Date().getFullYear();
            
            fetch(`get_appointments.php?month=${month}&year=${year}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAppointmentsList(data.appointments || []);
                        if (parentAppointmentCalendar && currentDoctorId) {
                            parentAppointmentCalendar.setAppointments(appointmentsCache.length ? appointmentsCache : (data.appointments || []));
                        }
                    }
                })
                .catch(error => console.error('Error loading appointments:', error));
        }

        function displayAppointmentsList(appointments) {
            const listContainer = document.getElementById('appointmentsList');
            if (!listContainer) return;
            if (!appointments || appointments.length === 0) {
                listContainer.innerHTML = `
                    <div class="no-appointments">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p>No appointments scheduled</p>
                    </div>
                `;
                return;
            }
            let html = '';
            appointments.forEach(appointment => {
                const date = new Date(appointment.appointment_date);
                const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const time = appointment.appointment_time ? appointment.appointment_time.substring(0, 5) : '';
                
                html += `
                    <div class="appointment-item">
                        <div class="appointment-item-date">${formattedDate}</div>
                        <div class="appointment-item-time">⏰ ${time}</div>
                        <div class="appointment-item-patient">${appointment.patient_first_name || appointment.child_first_name || ''} ${appointment.patient_last_name || appointment.child_last_name || ''}</div>
                        <div class="mt-2">
                            <span class="appointment-item-type">${appointment.type || ''}</span>
                            <span class="appointment-item-status ${appointment.status ? appointment.status.toLowerCase() : ''}">${appointment.status || ''}</span>
                        </div>
                        <div class="text-sm text-gray-600 mt-2">Dr. ${appointment.doctor_first_name || ''} ${appointment.doctor_last_name || ''}</div>
                    </div>
                `;
            });
            listContainer.innerHTML = html;
        }

        function handleDateSelect(date) {
            if (!currentDoctorId) {
                (window.showToast || alert)('Please select a doctor first.', 'warning');
                return;
            }
            
            const modal = document.getElementById('bookAppointmentModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            const dateInput = document.getElementById('modalAppointmentDate');
            const doctorSelect = document.getElementById('modalAppointmentDoctor');
            const timeSelect = document.getElementById('modalAppointmentTime');
            const childSelect = document.getElementById('modalAppointmentChild');
            
            if (dateInput) dateInput.value = date;
            if (doctorSelect) doctorSelect.value = currentDoctorId;
            
            if (childSelect && !childSelect.value && childSelect.options.length > 1) {
                childSelect.selectedIndex = 1;
            }
            
            updateTimeOptionsForDate(date, currentDoctorId);
        }

        function updateTimeOptionsForDate(date, doctorId) {
            const timeSelect = document.getElementById('modalAppointmentTime');
            if (!timeSelect) return;
            const selectedDate = date;
            const bookedTimes = new Set();
            const sourceAppointments = Array.isArray(appointmentsCache) && appointmentsCache.length ? appointmentsCache : [];
            const fallbackAppointments = (parentAppointmentCalendar && parentAppointmentCalendar.options && parentAppointmentCalendar.options.appointments) ? parentAppointmentCalendar.options.appointments : [];
            const allAppointments = sourceAppointments.length ? sourceAppointments : fallbackAppointments;

            allAppointments.forEach(a => {
                const aDate = a.appointment_date || a.date || '';
                const aTime = a.appointment_time || a.time || '';
                const aDoctor = a.doctor_id ? String(a.doctor_id) : (a.doctorId ? String(a.doctorId) : '');
                if (!aDate || !aTime) return;
                if (String(aDate) === String(selectedDate) && String(aDoctor) === String(doctorId)) {
                    bookedTimes.add(aTime.substring(0,5) === ':' ? aTime : aTime);
                }
            });

            Array.from(timeSelect.options).forEach(opt => {
                if (!opt.value) {
                    opt.disabled = false;
                    return;
                }
                const optHHMM = opt.value.substring(0,5);
                let isBooked = false;
                bookedTimes.forEach(bt => {
                    const btHHMM = bt.substring(0,5);
                    if (btHHMM === optHHMM) isBooked = true;
                });
                opt.disabled = isBooked;
            });

            const available = Array.from(timeSelect.options).some(o => o.value && !o.disabled);
            if (!available) {
                setTimeout(() => {
                    (window.showToast || alert)('No available times on this date for the selected doctor. Please choose another date or doctor.', 'warning');
                }, 10);
            }
        }

        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');

        function openMobileMenu() {
            sidebar.classList.add('open');
            mobileOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            sidebar.classList.remove('open');
            mobileOverlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        if (mobileMenuButton) mobileMenuButton.addEventListener('click', openMobileMenu);
        if (closeSidebar) closeSidebar.addEventListener('click', closeMobileMenu);
        if (mobileOverlay) mobileOverlay.addEventListener('click', closeMobileMenu);

        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    closeMobileMenu();
                }
            });
        });

        // Section management
        function showSection(sectionName) {
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.remove('hidden');
                document.querySelector('.main-content').scrollTop = 0;
            }
            document.querySelectorAll('nav a').forEach(link => {
                link.classList.remove('bg-white/20');
            });
            const activeLink = document.querySelector(`a[href="#${sectionName}"]`);
            if (activeLink) {
                activeLink.classList.add('bg-white/20');
            }
            if (window.innerWidth < 768) {
                closeMobileMenu();
            }
        }

        // Modal helpers
        function openAddChildModal() {
            document.getElementById('addChildModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeAddChildModal() {
            document.getElementById('addChildModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

       

        function openBookAppointmentModal() {
            document.getElementById('bookAppointmentModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function openVaccinationInfoModal() {
            document.getElementById('vaccinationInfoModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeVaccinationInfoModal() {
            document.getElementById('vaccinationInfoModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        

        function viewChildVaccinations(childId) {
            showSection('vaccinations');
        }

        // Medical records tab functions and loader
        function showMedicalRecordsTab(tabName) {
            document.querySelectorAll('.medical-records-tab').forEach(tab => {
                tab.classList.remove('active', 'border-primary', 'text-primary');
                tab.classList.add('text-gray-500');
            });
            const activeTab = document.querySelector(`.medical-records-tab[data-tab="${tabName}"]`);
            if (activeTab) {
                activeTab.classList.add('active', 'border-primary', 'text-primary');
                activeTab.classList.remove('text-gray-500');
            }
            document.querySelectorAll('.medical-records-content').forEach(content => {
                content.classList.add('hidden');
            });
            const activeContent = document.getElementById(`${tabName}-tab`);
            if (activeContent) {
                activeContent.classList.remove('hidden');
            }
            loadMedicalRecordsData(tabName);
        }

        function loadMedicalRecordsData(tabName) {
            const childId = window.currentMedicalRecordsChildId;
            if (!childId) return;
            const contentDiv = document.getElementById(`${tabName}-content`);
            contentDiv.innerHTML = '<div class="text-center py-8"><div class="spinner"></div><p class="mt-2 text-gray-600">Loading...</p></div>';
            
            fetch(`<?php echo basename(__FILE__); ?>?action=get_medical_records&child_id=${encodeURIComponent(childId)}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        contentDiv.innerHTML = '<p class="text-center text-gray-500 py-8">Unable to load records.</p>';
                        return;
                    }
                    
                    let html = '';
                    if (tabName === 'consultations') {
                        const records = data.consultations || [];
                        if (records.length === 0) {
                            html = '<p class="text-center text-gray-500 py-8">No consultation records found for this child.</p>';
                        } else {
                            records.forEach(r => {
                                const date = r.created_at || r.record_date || '';
                                html += `
                                    <div class="bg-blue-50 p-4 rounded-lg mb-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-semibold text-blue-800">${escapeHtml(r.diagnosis || r.record_type || 'Consultation')}</h4>
                                                <p class="text-sm text-blue-700">Dr. ${escapeHtml(r.doctor_first || '')} ${escapeHtml(r.doctor_last || '')}</p>
                                            </div>
                                            <span class="text-xs text-blue-600">${escapeHtml(date)}</span>
                                        </div>
                                        <div class="mt-2 text-sm text-blue-900">
                                            <p><strong>Notes:</strong> ${escapeHtml(r.notes || r.treatment_plan || r.symptoms || '')}</p>
                                            ${r.treatment_plan ? `<p><strong>Treatment:</strong> ${escapeHtml(r.treatment_plan)}</p>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    } else if (tabName === 'prescriptions') {
                        const records = data.prescriptions || [];
                        if (records.length === 0) {
                            html = '<p class="text-center text-gray-500 py-8">No prescriptions found for this child.</p>';
                        } else {
                            records.forEach(p => {
                                html += `
                                    <div class="bg-purple-50 p-4 rounded-lg mb-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-semibold text-purple-800">${escapeHtml(p.medication_name || 'Prescription')}</h4>
                                                <p class="text-sm text-purple-700">Dr. ${escapeHtml(p.doctor_first || '')} ${escapeHtml(p.doctor_last || '')}</p>
                                            </div>
                                            <span class="text-xs text-purple-600">${escapeHtml(p.prescription_date || p.created_at || '')}</span>
                                        </div>
                                        <div class="mt-2 text-sm text-purple-900">
                                            <p><strong>Dosage:</strong> ${escapeHtml(p.dosage || '')}</p>
                                            <p><strong>Frequency:</strong> ${escapeHtml(p.frequency || '')}</p>
                                            <p><strong>Duration:</strong> ${escapeHtml(p.duration || '')}</p>
                                            ${p.instructions ? `<p><strong>Instructions:</strong> ${escapeHtml(p.instructions)}</p>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    } else if (tabName === 'vaccinations') {
                        const records = data.vaccinations || [];
                        if (records.length === 0) {
                            html = '<p class="text-center text-gray-500 py-8">No vaccination records found for this child.</p>';
                        } else {
                            records.forEach(v => {
                                html += `
                                    <div class="bg-green-50 p-4 rounded-lg mb-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-semibold text-green-800">${escapeHtml(v.vaccine_name || 'Vaccine')}</h4>
                                                <p class="text-sm text-green-700">Dose ${escapeHtml(v.dose_number || '')} of ${escapeHtml(v.total_doses || '')}</p>
                                            </div>
                                            <span class="text-xs text-green-600">${escapeHtml(v.administration_date || v.created_at || '')}</span>
                                        </div>
                                        <div class="mt-2 text-sm text-green-900">
                                            ${v.next_due_date ? `<p><strong>Next Due:</strong> ${escapeHtml(v.next_due_date)}</p>` : ''}
                                            ${v.lot_number ? `<p><strong>Lot Number:</strong> ${escapeHtml(v.lot_number)}</p>` : ''}
                                            ${v.manufacturer ? `<p><strong>Manufacturer:</strong> ${escapeHtml(v.manufacturer)}</p>` : ''}
                                            ${v.site ? `<p><strong>Site:</strong> ${escapeHtml(v.site)}</p>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    }

                    contentDiv.innerHTML = html;
                })
                .catch(err => {
                    console.error('Error loading medical records:', err);
                    contentDiv.innerHTML = '<p class="text-center text-gray-500 py-8">Failed to load records.</p>';
                });
        }

        // Utility to escape HTML in inserted strings
        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return String(unsafe)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function closeBookAppointmentModal() {
            const modal = document.getElementById('bookAppointmentModal');
            if (modal) modal.classList.add('hidden');
            document.body.style.overflow = 'auto';

            // Optionally reset the booking form so fields don't persist
            const form = document.getElementById('bookAppointmentForm');
            if (form) form.reset();

            // If you want to also clear any time-option disabling state:
            const timeSelect = document.getElementById('modalAppointmentTime');
            if (timeSelect) {
                Array.from(timeSelect.options).forEach(opt => opt.disabled = false);
            }
        }

        // Open medical records modal and set current child
        function openMedicalRecords(childId, childName) {
            document.getElementById('medicalRecordsTitle').textContent = 'Medical Records';
            document.getElementById('medicalRecordsChildInfo').textContent = `For ${childName}`;
            
            // Set the current child ID for AJAX calls
            window.currentMedicalRecordsChildId = childId;
            
            // Load initial tab
            showMedicalRecordsTab('consultations');
            
            document.getElementById('medicalRecordsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMedicalRecordsModal() {
            document.getElementById('medicalRecordsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            window.currentMedicalRecordsChildId = null;
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', function(e) {
                if (e.target === this) {
                    const modal = this.querySelector('.modal-content').closest('.modal-backdrop');
                    if (modal.id === 'addChildModal') closeAddChildModal();
                    if (modal.id === 'bookAppointmentModal') closeBookAppointmentModal();
                    if (modal.id === 'vaccinationInfoModal') closeVaccinationInfoModal();
                    if (modal.id === 'medicalRecordsModal') closeMedicalRecordsModal();
                }
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddChildModal();
                closeBookAppointmentModal();
                closeVaccinationInfoModal();
                closeMedicalRecordsModal();
            }
        });

        // Initialize first section as active
        document.addEventListener('DOMContentLoaded', function() {
            const initialNav = document.querySelector('a[href="#dashboard"]');
            if (initialNav) {
                initialNav.classList.add('bg-white/20');
            }
            showSection('dashboard');
        });

        // Minimal appointment calendar component included below (same as original file's component)
        class AppointmentCalendar {
            constructor(containerId, options = {}) {
                this.container = document.getElementById(containerId);
                this.currentDate = new Date();
                this.selectedDate = null;
                this.options = {
                    onDateSelect: options.onDateSelect || null,
                    unavailableDates: options.unavailableDates || [],
                    appointments: options.appointments || [],
                    workingDays: options.workingDays || ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY'],
                    userType: options.userType || 'parent',
                    doctorId: options.doctorId || null,
                    ...options
                };
                
                this.init();
            }
            
            init() {
                this.render();
                this.attachEventListeners();
            }
            
            render() {
                if (!this.container) return;
                const month = this.currentDate.getMonth();
                const year = this.currentDate.getFullYear();
                
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const prevLastDay = new Date(year, month, 0);
                
                const firstDayIndex = firstDay.getDay();
                const lastDayIndex = lastDay.getDay();
                const nextDays = 7 - lastDayIndex - 1;
                
                const months = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                
                let calendarHTML = `
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button class="calendar-nav-btn" id="prevMonth">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <h3 class="calendar-month-year">${months[month]} ${year}</h3>
                            <button class="calendar-nav-btn" id="nextMonth">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                        <div class="calendar-weekdays">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>
                        <div class="calendar-days">
                `;
                
                for (let x = firstDayIndex; x > 0; x--) {
                    calendarHTML += `<div class="calendar-day prev-month">${prevLastDay.getDate() - x + 1}</div>`;
                }
                
                for (let day = 1; day <= lastDay.getDate(); day++) {
                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const dayOfWeek = new Date(year, month, day).getDay();
                    const dayName = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'][dayOfWeek];
                    
                    let dayClass = 'calendar-day';
                    let dayData = '';
                    let isUnavailable = false;
                    let isPast = false;
                    let isWorkingDay = true;
                    
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const currentDay = new Date(year, month, day);
                    if (currentDay < today) {
                        dayClass += ' past-date';
                        isPast = true;
                    }
                    
                    if (day === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear()) {
                        dayClass += ' today';
                    }
                    
                    if (this.options.workingDays && !this.options.workingDays.includes(dayName)) {
                        isWorkingDay = false;
                        dayClass += ' non-working-day';
                    }
                    
                    const unavailable = (this.options.unavailableDates || []).find(d => d.date === dateStr);
                    if (unavailable) {
                        isUnavailable = true;
                        dayClass += ' unavailable-date';
                        dayData = ` data-reason="${unavailable.reason || 'Unavailable'}"`;
                    }
                    
                    const dayAppointments = (this.options.appointments || []).filter(a => {
                        const aDate = a.appointment_date || a.date || '';
                        return String(aDate) === String(dateStr);
                    });
                    if (dayAppointments.length > 0) {
                        dayClass += ' has-appointments';
                        dayData += ` data-appointment-count="${dayAppointments.length}"`;
                    }
                    
                    const clickable = !isPast && isWorkingDay && !isUnavailable && this.options.onDateSelect;
                    if (clickable) {
                        dayClass += ' clickable';
                    }
                    
                    calendarHTML += `
                        <div class="${dayClass}" data-date="${dateStr}"${dayData}>
                            <span class="day-number">${day}</span>
                            ${dayAppointments.length > 0 ? `<span class="appointment-indicator">${dayAppointments.length}</span>` : ''}
                            ${isUnavailable ? '<span class="unavailable-badge">X</span>' : ''}
                        </div>
                    `;
                }
                
                for (let j = 1; j <= nextDays; j++) {
                    calendarHTML += `<div class="calendar-day next-month">${j}</div>`;
                }
                
                calendarHTML += `
                        </div>
                    </div>
                `;
                
                this.container.innerHTML = calendarHTML;
            }
            
            attachEventListeners() {
                const prevBtn = this.container.querySelector('#prevMonth');
                const nextBtn = this.container.querySelector('#nextMonth');
                
                if (prevBtn) {
                    prevBtn.addEventListener('click', () => this.previousMonth());
                }
                
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => this.nextMonth());
                }
                
                const days = this.container.querySelectorAll('.calendar-day.clickable');
                days.forEach(day => {
                    day.addEventListener('click', (e) => {
                        const date = e.currentTarget.dataset.date;
                        if (date && this.options.onDateSelect) {
                            this.container.querySelectorAll('.calendar-day.selected').forEach(d => {
                                d.classList.remove('selected');
                            });
                            
                            e.currentTarget.classList.add('selected');
                            this.selectedDate = date;
                            this.options.onDateSelect(date);
                        }
                    });
                });
                
                const unavailableDays = this.container.querySelectorAll('.calendar-day.unavailable-date');
                unavailableDays.forEach(day => {
                    day.addEventListener('mouseenter', (e) => {
                        const reason = e.currentTarget.dataset.reason;
                        if (reason) {
                            this.showTooltip(e.currentTarget, reason);
                        }
                    });
                    
                    day.addEventListener('mouseleave', () => {
                        this.hideTooltip();
                    });
                });
            }
            
            showTooltip(element, text) {
                const existing = document.getElementById('calendar-tooltip');
                if (existing) existing.remove();

                const tooltip = document.createElement('div');
                tooltip.className = 'calendar-tooltip';
                tooltip.textContent = text;
                tooltip.id = 'calendar-tooltip';
                
                const rect = element.getBoundingClientRect();
                tooltip.style.top = (rect.top - 40) + 'px';
                tooltip.style.left = (rect.left + rect.width / 2) + 'px';
                
                document.body.appendChild(tooltip);
            }
            
            hideTooltip() {
                const tooltip = document.getElementById('calendar-tooltip');
                if (tooltip) {
                    tooltip.remove();
                }
            }
            
            previousMonth() {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.updateCalendar();
            }
            
            nextMonth() {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.updateCalendar();
            }
            
            updateCalendar() {
                if (this.options.onMonthChange) {
                    this.options.onMonthChange(this.currentDate.getMonth() + 1, this.currentDate.getFullYear());
                }
                this.render();
                this.attachEventListeners();
            }
            
            setUnavailableDates(dates) {
                this.options.unavailableDates = dates;
                this.render();
                this.attachEventListeners();
            }
            
            setAppointments(appointments) {
                this.options.appointments = appointments;
                this.render();
                this.attachEventListeners();
            }
            
            setWorkingDays(days) {
                this.options.workingDays = days;
                this.render();
                this.attachEventListeners();
            }
        }
        // === upload modal helpers ===
        function openUploadModal(patientId, patientName) {
            document.getElementById('upload_patient_id').value = patientId;
            document.getElementById('uploadModalTitle').textContent = 'Upload File';
            document.getElementById('uploadModalChildInfo').textContent = `For ${patientName}`;
            document.getElementById('uploadFileModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeUploadModal() {
            document.getElementById('uploadFileModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

    </script>
    <script>
    function validateParentPasswordForm(form) {
        var cur = form.current_password.value;
        var n   = form.new_password.value;
        var c   = form.confirm_password.value;
        if (!cur || !n || !c) {
            (window.showToast || alert)('All password fields are required.', 'warning');
            return false;
        }
        if (n.length < 8) {
            (window.showToast || alert)('New password must be at least 8 characters.', 'warning');
            return false;
        }
        if (n !== c) {
            (window.showToast || alert)('New password and confirmation do not match.', 'warning');
            return false;
        }
        return true;
    }
    </script>
    <?php if (!empty($__pendingToasts)): ?>
    <script>
    (function () {
        function run() {
            <?php foreach ($__pendingToasts as $t): ?>
            if (typeof window.showToast === 'function') {
                window.showToast(<?php echo json_encode($t['message']); ?>, <?php echo json_encode($t['type']); ?>);
            }
            <?php endforeach; ?>
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>

<?php
// Helper functions for status display (unchanged)
function getStatusColor($status) {
    switch ($status) {
        case 'SCHEDULED': return 'text-orange-600';
        case 'CONFIRMED': return 'text-green-600';
        case 'COMPLETED': return 'text-blue-600';
        case 'CANCELLED': return 'text-red-600';
        default: return 'text-gray-600';
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'SCHEDULED': return 'bg-orange-100 text-orange-800';
        case 'CONFIRMED': return 'bg-green-100 text-green-800';
        case 'COMPLETED': return 'bg-blue-100 text-blue-800';
        case 'CANCELLED': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getVaccineNeedBadge($status) {
    switch ($status) {
        case 'GIVEN': return 'bg-green-100 text-green-800';
        case 'SCHEDULED': return 'bg-blue-100 text-blue-800';
        case 'RECOMMENDED': return 'bg-purple-100 text-purple-800';
        case 'NOT_NEEDED': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getVaccineTypeBadge($type) {
    switch ($type) {
        case 'ROUTINE': return 'bg-purple-100 text-purple-800';
        case 'OPTIONAL': return 'bg-yellow-100 text-yellow-800';
        case 'SPECIAL': return 'bg-pink-100 text-pink-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getVaccinationStatusBadge($status) {
    $s = strtoupper(trim((string)$status));
    switch ($s) {
        case 'COMPLETED':
        case 'GIVEN':
            return 'bg-green-100 text-green-800';
        case 'SCHEDULED':
        case 'PENDING':
            return 'bg-blue-100 text-blue-800';
        case 'OVERDUE':
        case 'MISSED':
            return 'bg-red-100 text-red-800';
        case 'PARTIAL':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}


?>