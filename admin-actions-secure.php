<?php
// admin-actions-secure.php
require_once 'db_connect.php';

header('Content-Type: application/json');

// 1. Check authentication FIRST
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 2. CSRF Protection
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_user':
            handleAddUser($conn);
            break;
        case 'toggle_user_status':
            handleToggleUserStatus($conn);
            break;
        case 'add_schedule':
            handleAddSchedule($conn);
            break;
        case 'delete_schedule':
            handleDeleteSchedule($conn);
            break;
        case 'add_service':
            handleAddService($conn);
            break;
        case 'toggle_service_status':
            handleToggleServiceStatus($conn);
            break;
        case 'update_clinic_settings':
            handleUpdateClinicSettings($conn);
            break;
        case 'export_patient_data':
            handleExportPatientData($conn);
            break;
        case 'get_doctors':
            handleGetDoctors($conn);
            break;
        case 'add_announcement':
            handleAddAnnouncement($conn);
            break;
        case 'toggle_announcement':
            handleToggleAnnouncement($conn);
            break;
        case 'delete_announcement':
            handleDeleteAnnouncement($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
            exit;
    }
} catch (Exception $e) {
    error_log("Admin action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

function handleAddUser($conn) {
    // Validate required fields
    $required = ['first_name', 'last_name', 'email', 'user_type', 'password'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $user_type = sanitize_input($_POST['user_type']);
    
    // Validate input lengths
    if (strlen($first_name) > 50 || strlen($last_name) > 50) {
        echo json_encode(['success' => false, 'message' => 'Name too long (max 50 characters)']);
        return;
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    if (strlen($email) > 100) {
        echo json_encode(['success' => false, 'message' => 'Email too long (max 100 characters)']);
        return;
    }
    
    // Validate user type
    $allowed_types = ['PARENT', 'DOCTOR', 'ADMIN'];
    if (!in_array($user_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid user type']);
        return;
    }
    
    // Password strength check
    $password = $_POST['password'];
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if email exists using prepared statement
    $check_query = "SELECT id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    // Insert user with prepared statement
    $query = "INSERT INTO users (first_name, last_name, email, phone, password, user_type, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssss", $first_name, $last_name, $email, $phone, $hashed_password, $user_type);
    
    if (mysqli_stmt_execute($stmt)) {
        $new_user_id = mysqli_insert_id($conn);
        
        // Log activity with actual user ID
        $user_id = $_SESSION['user_id'];
        $details = "Created new user: $first_name $last_name ($user_type) - ID: $new_user_id";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'CREATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $new_user_id]);
    } else {
        error_log("User creation error: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }
}

function handleToggleUserStatus($conn) {
    if (!isset($_POST['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    $current_user_id = $_SESSION['user_id'];
    
    // Prevent self-deactivation
    if ($user_id == $current_user_id) {
        echo json_encode(['success' => false, 'message' => 'Cannot change your own status']);
        return;
    }
    
    // Get current status
    $query = "SELECT status, first_name, last_name, user_type FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
    
    // Update status
    $update_query = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "si", $new_status, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $details = "Changed user status: {$user['first_name']} {$user['last_name']} ({$user['user_type']}) to $new_status";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'UPDATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $current_user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode(['success' => true, 'message' => 'User status updated', 'new_status' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
}

function handleAddSchedule($conn) {
    $required = ['doctor_id', 'day_of_week', 'start_time', 'end_time'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    $doctor_id = intval($_POST['doctor_id']);
    $day_of_week = sanitize_input($_POST['day_of_week']);
    $start_time = sanitize_input($_POST['start_time']);
    $end_time = sanitize_input($_POST['end_time']);
    $slot_duration = intval($_POST['slot_duration'] ?? 30);
    $max_patients = intval($_POST['max_patients'] ?? 10);
    
    // Validate day of week
    $allowed_days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
    if (!in_array($day_of_week, $allowed_days)) {
        echo json_encode(['success' => false, 'message' => 'Invalid day of week']);
        return;
    }
    
    // Validate time format
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $start_time) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $end_time)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        return;
    }
    
    // Validate time range
    if (strtotime($start_time) >= strtotime($end_time)) {
        echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
        return;
    }
    
    // Validate doctor exists
    $check_query = "SELECT id FROM users WHERE id = ? AND user_type IN ('DOCTOR', 'DOCTOR_OWNER')";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $doctor_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) === 0) {
        echo json_encode(['success' => false, 'message' => 'Doctor not found']);
        return;
    }
    
    // Check for overlapping schedules
    $overlap_query = "SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND active = 1 AND (
        (start_time <= ? AND end_time > ?) OR
        (start_time < ? AND end_time >= ?) OR
        (start_time >= ? AND end_time <= ?)
    )";
    $overlap_stmt = mysqli_prepare($conn, $overlap_query);
    mysqli_stmt_bind_param($overlap_stmt, "isssssss", $doctor_id, $day_of_week, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
    mysqli_stmt_execute($overlap_stmt);
    mysqli_stmt_store_result($overlap_stmt);
    
    if (mysqli_stmt_num_rows($overlap_stmt) > 0) {
        echo json_encode(['success' => false, 'message' => 'Schedule overlaps with existing schedule']);
        return;
    }
    
    $query = "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_patients) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isssii", $doctor_id, $day_of_week, $start_time, $end_time, $slot_duration, $max_patients);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $user_id = $_SESSION['user_id'];
        $details = "Added schedule for doctor ID: $doctor_id on $day_of_week from $start_time to $end_time";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'CREATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode(['success' => true, 'message' => 'Schedule added successfully']);
    } else {
        error_log("Schedule add error: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Failed to add schedule']);
    }
}

function handleDeleteSchedule($conn) {
    if (!isset($_POST['schedule_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing schedule ID']);
        return;
    }
    
    $schedule_id = intval($_POST['schedule_id']);
    
    // Get schedule details for logging
    $query = "SELECT ds.*, u.first_name, u.last_name FROM doctor_schedules ds 
              JOIN users u ON ds.doctor_id = u.id 
              WHERE ds.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $schedule_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $schedule = mysqli_fetch_assoc($result);
    
    if (!$schedule) {
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
        return;
    }
    
    $delete_query = "DELETE FROM doctor_schedules WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $schedule_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $user_id = $_SESSION['user_id'];
        $details = "Deleted schedule for Dr. {$schedule['first_name']} {$schedule['last_name']} on {$schedule['day_of_week']} ({$schedule['start_time']} - {$schedule['end_time']})";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'DELETE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete schedule']);
    }
}

function handleAddService($conn) {
    $required = ['name', 'description', 'duration', 'cost'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim((string)$_POST[$field]) === '') {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    // Strict type validation for numeric fields
    if (!is_numeric($_POST['duration']) || intval($_POST['duration']) != $_POST['duration']) {
        echo json_encode(['success' => false, 'message' => 'Duration must be a whole number']);
        return;
    }
    if (!is_numeric($_POST['cost'])) {
        echo json_encode(['success' => false, 'message' => 'Cost must be a number']);
        return;
    }
    
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description'] ?? '');
    $duration = intval($_POST['duration']);
    $cost = floatval($_POST['cost']);
    
    // Validate name length
    if (strlen($name) > 100) {
        echo json_encode(['success' => false, 'message' => 'Service name too long (max 100 characters)']);
        return;
    }
    
    // Validate duration
    if ($duration <= 0 || $duration > 480) {
        echo json_encode(['success' => false, 'message' => 'Duration must be between 1 and 480 minutes']);
        return;
    }
    
    // Validate cost
    if ($cost < 0 || $cost > 999999.99) {
        echo json_encode(['success' => false, 'message' => 'Invalid cost amount']);
        return;
    }
    
    // Check if service already exists
    $check_query = "SELECT id FROM services WHERE name = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "s", $name);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        echo json_encode(['success' => false, 'message' => 'Service with this name already exists']);
        return;
    }
    
    $query = "INSERT INTO services (name, description, duration, cost) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssid", $name, $description, $duration, $cost);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $user_id = $_SESSION['user_id'];
        $details = "Added new service: $name (₱$cost, {$duration}min)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'CREATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode(['success' => true, 'message' => 'Service added successfully']);
    } else {
        error_log("Service add error: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Failed to add service']);
    }
}

function handleToggleServiceStatus($conn) {
    if (!isset($_POST['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing service ID']);
        return;
    }
    
    $service_id = intval($_POST['service_id']);
    
    $query = "SELECT name, active FROM services WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $service_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $service = mysqli_fetch_assoc($result);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        return;
    }
    
    $new_status = $service['active'] ? 0 : 1;
    $status_text = $new_status ? 'Active' : 'Inactive';
    
    $update_query = "UPDATE services SET active = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ii", $new_status, $service_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $user_id = $_SESSION['user_id'];
        $details = "Changed service status: {$service['name']} to $status_text";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'UPDATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode(['success' => true, 'message' => 'Service status updated', 'new_status' => $status_text]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update service status']);
    }
}

function handleUpdateClinicSettings($conn) {
    $allowed_settings = [
        'clinic_name', 'clinic_phone', 'clinic_email', 
        'clinic_address', 'appointment_reminder_hours'
    ];
    
    $success = true;
    $errors = [];
    $updated_settings = [];
    
    foreach ($allowed_settings as $key) {
        if (isset($_POST[$key])) {
            $value = sanitize_input($_POST[$key]);
            
            // Special validation for specific fields
            if ($key === 'appointment_reminder_hours') {
                if (!is_numeric($value) || $value < 1 || $value > 168) {
                    $errors[] = "Reminder hours must be between 1-168";
                    continue;
                }
            }
            
            if ($key === 'clinic_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
                continue;
            }
            
            $query = "UPDATE clinic_settings SET setting_value = ? WHERE setting_key = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $value, $key);
            
            if (mysqli_stmt_execute($stmt)) {
                $updated_settings[] = $key;
            } else {
                $success = false;
                $errors[] = "Failed to update $key";
            }
        }
    }
    
    if ($success && empty($errors)) {
        // Log activity
        $user_id = $_SESSION['user_id'];
        $details = "Updated clinic settings: " . implode(', ', $updated_settings);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'UPDATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Some settings failed to update', 
            'errors' => $errors
        ]);
    }
}

function handleExportPatientData($conn) {
    if (!isset($_POST['patient_id']) || !isset($_POST['doctor_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing patient or doctor ID']);
        return;
    }
    
    $patient_id = intval($_POST['patient_id']);
    $doctor_id = intval($_POST['doctor_id']);
    $format = sanitize_input($_POST['format'] ?? 'json');
    
    $result = exportPatientData($conn, $patient_id, $doctor_id, $format);
    
    if ($result['success']) {
        // For JSON format, return the data
        if ($format === 'json') {
            echo json_encode([
                'success' => true,
                'message' => 'Data exported successfully',
                'data' => $result['data']
            ]);
        } else {
            // For CSV or other formats, you would generate a file here
            echo json_encode([
                'success' => true,
                'message' => 'Export initiated. Download will start shortly.'
            ]);
        }
    } else {
        echo json_encode($result);
    }
}

function handleGetDoctors($conn) {
    $doctors = get_all_doctors($conn);

    if (!empty($doctors)) {
        echo json_encode(['success' => true, 'doctors' => $doctors]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No doctors found']);
    }
}

function handleAddAnnouncement($conn) {
    $required = ['title', 'content'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }

    $title = sanitize_input($_POST['title']);
    $content = $_POST['content']; // Allow HTML content
    $category = sanitize_input($_POST['category'] ?? 'GENERAL');
    $priority = sanitize_input($_POST['priority'] ?? 'NORMAL');
    $created_by = $_SESSION['user_id'];

    // Validate category
    $allowed_categories = ['GENERAL', 'MAINTENANCE', 'HEALTH_ADVISORY', 'EVENT', 'PROMOTION'];
    if (!in_array($category, $allowed_categories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        return;
    }

    // Validate priority
    $allowed_priorities = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];
    if (!in_array($priority, $allowed_priorities)) {
        echo json_encode(['success' => false, 'message' => 'Invalid priority']);
        return;
    }

    if (strlen($title) > 200) {
        echo json_encode(['success' => false, 'message' => 'Title too long (max 200 characters)']);
        return;
    }

    $query = "INSERT INTO announcements (title, content, category, priority, is_active, published_at, created_by)
              VALUES (?, ?, ?, ?, 1, NOW(), ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssi", $title, $content, $category, $priority, $created_by);

    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $details = "Created announcement: $title";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'CREATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $created_by, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);

        echo json_encode(['success' => true, 'message' => 'Announcement published successfully']);
    } else {
        error_log("Announcement add error: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Failed to create announcement']);
    }
}

function handleToggleAnnouncement($conn) {
    if (!isset($_POST['announcement_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing announcement ID']);
        return;
    }

    $announcement_id = intval($_POST['announcement_id']);
    $is_active = intval($_POST['is_active'] ?? 0);

    $query = "UPDATE announcements SET is_active = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $is_active, $announcement_id);

    if (mysqli_stmt_execute($stmt)) {
        $status_text = $is_active ? 'activated' : 'deactivated';
        // Log activity
        $user_id = $_SESSION['user_id'];
        $details = "Announcement $status_text (ID: $announcement_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'UPDATE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);

        echo json_encode(['success' => true, 'message' => "Announcement $status_text"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update announcement']);
    }
}

function handleDeleteAnnouncement($conn) {
    if (!isset($_POST['announcement_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing announcement ID']);
        return;
    }

    $announcement_id = intval($_POST['announcement_id']);

    // Get title for logging
    $check_query = "SELECT title FROM announcements WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $announcement_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $announcement = mysqli_fetch_assoc($result);

    $delete_query = "DELETE FROM announcements WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $announcement_id);

    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $user_id = $_SESSION['user_id'];
        $title = $announcement ? $announcement['title'] : 'Unknown';
        $details = "Deleted announcement: $title (ID: $announcement_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'DELETE', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $details, $ip_address);
        mysqli_stmt_execute($log_stmt);

        echo json_encode(['success' => true, 'message' => 'Announcement deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete announcement']);
    }
}
?>