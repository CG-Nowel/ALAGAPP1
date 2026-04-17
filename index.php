<?php
require 'db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Flash messages queued while processing the request; rendered as toasts after
// toast.js loads in the page body. Populated by handleLogin/handleRegister and
// any legacy $_SESSION['message'] value.
$flashMessages = [];
// Optional post-login actions that should run on the client once toast.js is
// ready (e.g. re-opening the login modal after successful registration).
$flashActions = [];

function queue_flash(&$store, $message, $type = 'info') {
    if ($message === null || $message === '') return;
    $store[] = ['message' => (string)$message, 'type' => $type];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        handleLogin($conn, $flashMessages);
    } elseif (isset($_POST['register'])) {
        handleRegister($conn, $flashMessages, $flashActions);
    }
}


function handleLogin($conn, &$flashMessages) {
    $email = sanitize_input($_POST['loginEmail']);
    $password = $_POST['loginPassword'];

    // Check if user exists (use prepared statement to avoid SQL injection)
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        queue_flash($flashMessages, 'Database error. Please try again.', 'error');
        return;
    }
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        queue_flash($flashMessages, 'Database error. Please try again.', 'error');
        return;
    }

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        // Check if user is active
        $is_active = true;
        if (isset($user['status'])) {
            $is_active = ($user['status'] == 'active');
        }

        if (!$is_active) {
            queue_flash($flashMessages, 'Account is deactivated. Please contact administrator.', 'error');
            return;
        }

        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];

            // Log login activity - SIMPLIFIED VERSION without details column
            $logQuery = "INSERT INTO activity_logs (user_id, action, ip_address) VALUES ('{$user['id']}', 'LOGIN', '{$_SERVER['REMOTE_ADDR']}')";

            if (!mysqli_query($conn, $logQuery)) {
                // If still failing, try without ip_address too
                $logQuery = "INSERT INTO activity_logs (user_id, action) VALUES ('{$user['id']}', 'LOGIN')";
                mysqli_query($conn, $logQuery);
            }

            // Redirect based on user type
            switch ($user['user_type']) {
                case 'PARENT':
                    header("Location: parent-dashboard.php");
                    break;
                case 'DOCTOR':
                case 'DOCTOR_OWNER':
                    header("Location: doctor-dashboard.php");
                    break;
                case 'ADMIN':
                    header("Location: admin-dashboard.php");
                    break;
                default:
                    header("Location: parent-dashboard.php");
            }
            exit();
        } else {
            queue_flash($flashMessages, 'Invalid email or password', 'error');
        }
    } else {
        queue_flash($flashMessages, 'User not found', 'error');
    }
}

function handleRegister($conn, &$flashMessages, &$flashActions) {
    $firstName = sanitize_input($_POST['firstName'] ?? '');
    $lastName = sanitize_input($_POST['lastName'] ?? '');
    $email = sanitize_input($_POST['registerEmail'] ?? '');
    $phone = sanitize_input($_POST['phoneNumber'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $dateOfBirth = sanitize_input($_POST['dateOfBirth'] ?? '');
    $gender = sanitize_input($_POST['gender'] ?? '');
    $emergencyName = sanitize_input($_POST['emergencyContactName'] ?? '');
    $emergencyPhone = sanitize_input($_POST['emergencyContactPhone'] ?? '');
    $password = $_POST['registerPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $userType = 'PARENT'; // All new users are PARENT by default

    // Required fields (server-side guard to match client validation)
    $required = [
        'First name' => $firstName,
        'Last name' => $lastName,
        'Email' => $email,
        'Phone number' => $phone,
        'Date of birth' => $dateOfBirth,
        'Gender' => $gender,
    ];
    foreach ($required as $label => $value) {
        if ($value === '') {
            queue_flash($flashMessages, "$label is required.", 'error');
            return;
        }
    }

    // Validate passwords match
    if ($password !== $confirmPassword) {
        queue_flash($flashMessages, 'Passwords do not match', 'error');
        return;
    }

    // Validate password strength
    if (strlen($password) < 6) {
        queue_flash($flashMessages, 'Password must be at least 6 characters long', 'error');
        return;
    }

    if (!preg_match('/[A-Z]/', $password)) {
        queue_flash($flashMessages, 'Password must contain at least one uppercase letter', 'error');
        return;
    }

    if (!preg_match('/[a-z]/', $password)) {
        queue_flash($flashMessages, 'Password must contain at least one lowercase letter', 'error');
        return;
    }

    if (!preg_match('/[0-9]/', $password)) {
        queue_flash($flashMessages, 'Password must contain at least one number', 'error');
        return;
    }

    if (!preg_match('/[!@#$%^&*]/', $password)) {
        queue_flash($flashMessages, 'Password must contain at least one special character (!@#$%^&*)', 'error');
        return;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        queue_flash($flashMessages, 'Please enter a valid email address.', 'error');
        return;
    }

    // Check if email already exists (prepared statement)
    $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($check, 's', $email);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    if (mysqli_stmt_num_rows($check) > 0) {
        queue_flash($flashMessages, 'Email already registered. Please use a different email or login.', 'error');
        return;
    }

    // Validate phone number - Philippine format (9XXXXXXXXX)
    $cleanPhone = preg_replace('/\s+/', '', $phone);
    if (!preg_match('/^9\d{9}$/', $cleanPhone)) {
        queue_flash($flashMessages, 'Please enter a valid Philippine mobile number (10 digits starting with 9)', 'error');
        return;
    }

    // Validate emergency phone if provided
    $cleanEmergencyPhone = preg_replace('/\s+/', '', $emergencyPhone);
    if ($cleanEmergencyPhone !== '' && !preg_match('/^9\d{9}$/', $cleanEmergencyPhone)) {
        queue_flash($flashMessages, 'Emergency contact phone must be a valid Philippine mobile number.', 'error');
        return;
    }

    // Validate DOB is not in the future
    if ($dateOfBirth && strtotime($dateOfBirth) > time()) {
        queue_flash($flashMessages, 'Date of birth cannot be in the future.', 'error');
        return;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user - all new users are PARENT by default (prepared)
    $insert = mysqli_prepare(
        $conn,
        "INSERT INTO users
            (first_name, last_name, email, phone, password, user_type, status,
             date_of_birth, gender, address, emergency_contact_name, emergency_contact_phone, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, NOW())"
    );
    if (!$insert) {
        queue_flash($flashMessages, 'Registration failed. Please try again.', 'error');
        return;
    }
    // Convert empty strings to NULL-compatible values
    $dobValue = $dateOfBirth !== '' ? $dateOfBirth : null;
    $genderValue = $gender !== '' ? $gender : null;
    $addressValue = $address !== '' ? $address : null;
    $ecNameValue = $emergencyName !== '' ? $emergencyName : null;
    $ecPhoneValue = $cleanEmergencyPhone !== '' ? $cleanEmergencyPhone : null;
    mysqli_stmt_bind_param(
        $insert,
        'sssssssssss',
        $firstName, $lastName, $email, $cleanPhone, $hashedPassword, $userType,
        $dobValue, $genderValue, $addressValue, $ecNameValue, $ecPhoneValue
    );

    if (mysqli_stmt_execute($insert)) {
        $newUserId = mysqli_insert_id($conn);

        // Log registration activity
        $logQuery = "INSERT INTO activity_logs (user_id, action, timestamp) VALUES ('$newUserId', 'User registered', NOW())";
        mysqli_query($conn, $logQuery);

        queue_flash($flashMessages, 'Registration successful! Please login with your credentials.', 'success');
        $flashActions[] = "if (typeof closeRegisterModal === 'function') closeRegisterModal();";
        $flashActions[] = "setTimeout(function(){ if (typeof openLoginModal === 'function') openLoginModal(); }, 600);";
    } else {
        // Graceful fallback if the column set doesn't exist yet on legacy DBs
        $fallback = mysqli_prepare(
            $conn,
            "INSERT INTO users (first_name, last_name, email, phone, password, user_type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())"
        );
        if ($fallback) {
            mysqli_stmt_bind_param($fallback, 'ssssss', $firstName, $lastName, $email, $cleanPhone, $hashedPassword, $userType);
            if (mysqli_stmt_execute($fallback)) {
                queue_flash($flashMessages, 'Registration successful! Please login with your credentials.', 'success');
                $flashActions[] = "if (typeof closeRegisterModal === 'function') closeRegisterModal();";
                $flashActions[] = "setTimeout(function(){ if (typeof openLoginModal === 'function') openLoginModal(); }, 600);";
                return;
            }
        }
        queue_flash($flashMessages, 'Registration failed. Please try again later.', 'error');
    }
}

// Display success/error messages from session
if (isset($_SESSION['message'])) {
    queue_flash($flashMessages, (string)$_SESSION['message'], $_SESSION['message_type'] ?? 'info');
    unset($_SESSION['message']);
    if (isset($_SESSION['message_type'])) unset($_SESSION['message_type']);
}

// Load active clinic services for the landing page services carousel.
$landing_services = [];
$svc_res = @mysqli_query($conn, "SELECT id, name, description, duration, cost FROM services WHERE active = 1 ORDER BY name ASC");
if ($svc_res) {
    while ($row = mysqli_fetch_assoc($svc_res)) {
        $landing_services[] = $row;
    }
    mysqli_free_result($svc_res);
}

// Curated icon mapping for common service names; falls back to a generic icon.
function alag_service_icon_path($name) {
    $n = strtolower((string)$name);
    $icons = [
        'consultation'       => 'M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19ZM17 12H7V10H17V12Z',
        'vaccin'             => 'M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z',
        'well baby'          => 'M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z',
        'checkup'            => 'M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z',
        'clearance'          => 'M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM12 6C8.69 6 6 8.69 6 12C6 15.31 8.69 18 12 18C15.31 18 18 15.31 18 12C18 8.69 15.31 6 12 6ZM12 16C9.79 16 8 14.21 8 12C8 9.79 9.79 8 12 8C14.21 8 16 9.79 16 12C16 14.21 14.21 16 12 16Z',
        'referral'           => 'M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM15 8H9V10H15V8ZM17 12H7V14H17V12ZM19 16H5V18H19V16Z',
        'ear piercing'       => 'M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 8C14.21 8 16 9.79 16 12C16 14.21 14.21 16 12 16C9.79 16 8 14.21 8 12C8 9.79 9.79 8 12 8Z',
        'certificat'         => 'M21 9V7L15 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V9H21ZM19 21H5V3H13V9H19V21Z M9 16H15V18H9V16Z M9 12H15V14H9V12Z',
    ];
    foreach ($icons as $key => $path) {
        if (strpos($n, $key) !== false) return $path;
    }
    return 'M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19Z';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlagApp Clinic - Pediatric Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pixi.js/7.3.2/pixi.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-pink: #d03664;
            --light-pink: #FFBCD9;
            --dark-text: #333333;
            --light-gray: #f8f0f4;
        }
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #fef5f8 100%);
        }
        
        .font-inter { font-family: 'Inter', sans-serif; }
        
        .text-primary { color: var(--primary-pink); }
        .bg-primary { background-color: var(--primary-pink); }
        .bg-light-pink { background-color: var(--light-pink); }
        
        .hero-bg {
            background: linear-gradient(rgba(255, 107, 154, 0.1), rgba(255, 188, 217, 0.1)), 
                        url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1953&q=80') center/cover;
            min-height: 100vh;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(255, 107, 154, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink), var(--light-pink));
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 154, 0.3);
        }
        
        .floating-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--light-pink);
            border-radius: 50%;
            opacity: 0.6;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.4);
        }
        
        .typewriter {
            overflow: hidden;
            border-right: 3px solid var(--primary-pink);
            white-space: nowrap;
            animation: typing 3.5s steps(40, end), blink-caret 0.75s step-end infinite;
        }
        
        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }
        
        @keyframes blink-caret {
            from, to { border-color: transparent; }
            50% { border-color: var(--primary-pink); }
        }
        
        .captcha-container {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .service-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-pink), var(--light-pink));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            transition: all 0.3s ease;
        }
        
        .service-icon:hover {
            transform: scale(1.1) rotate(5deg);
        }
        
        .service-icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        
        /* Custom Splide Styles for Services Carousel */
        #services-carousel .splide__slide {
            padding: 0.5rem;
        }
        
        #services-carousel .splide__arrow {
            position: static;
            transform: none;
            opacity: 1;
            background: var(--primary-pink);
        }
        
        #services-carousel .splide__arrow:disabled {
            opacity: 0.5;
        }
        
        #services-carousel .splide__arrow svg {
            fill: none;
        }
        
        #services-carousel .splide__progress {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Announcement Carousel Styles */
        .announcement-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-pink);
            background: linear-gradient(to bottom right, white, #fdf2f8);
        }

        .announcement-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(255, 107, 154, 0.15);
            background: linear-gradient(to bottom right, white, #fce7f3);
        }

        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Pink category badges */
        .category-badge {
            background: var(--light-pink);
            color: var(--primary-pink);
            border: 1px solid rgba(255, 107, 154, 0.3);
        }

        /* Splide custom styles for announcements with pink theme */
        #announcements-carousel .splide__slide {
            padding: 0.5rem;
        }

        #announcements-carousel .splide__arrow {
            position: static;
            transform: none;
            opacity: 1;
            background: linear-gradient(135deg, var(--primary-pink), #ff8fab);
            box-shadow: 0 4px 15px rgba(255, 107, 154, 0.3);
        }

        #announcements-carousel .splide__arrow:hover {
            background: linear-gradient(135deg, #ff5a8c, #ff7aa3);
            transform: scale(1.1);
        }

        #announcements-carousel .splide__arrow:disabled {
            opacity: 0.5;
        }

        #announcements-carousel .splide__arrow svg {
            fill: none;
        }

        #announcements-carousel .splide__progress {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Pink progress bar */
        #announcements-carousel .splide__progress__bar {
            background: #fce7f3;
        }

        #announcements-carousel .splide__progress__bar__fill {
            background: linear-gradient(90deg, var(--primary-pink), #ff8fab);
        }

        /* Modal pink enhancements */
        .modal-header-pink {
            background: linear-gradient(135deg, var(--primary-pink), #ff8fab);
        }

        @media (max-width: 640px) {
        #registerModal {
            padding: 1rem;
            align-items: flex-start;
            padding-top: 2rem;
        }
        
        #registerModal > div {
            max-height: calc(100vh - 2rem);
            margin-top: 2rem;
        }
        
        .grid-cols-2 {
            grid-template-columns: 1fr;
        }
        
        .modal-header-pink h2 {
            font-size: 1.5rem;
        }
        
        .modal-header-piny p {
            font-size: 0.875rem;
        }
    }

    /* Ensure form elements are readable on mobile */
    input, select, textarea {
        font-size: 16px; /* Prevents zoom on iOS */
    }

    /* Improve focus states for accessibility */
    input:focus, select:focus, textarea:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(208, 54, 100, 0.1);
    }

    /* Style for error states */
    .border-red-500 {
        border-color: #ef4444;
    }

    .ring-red-200 {
        --tw-ring-color: rgba(254, 202, 202, 0.5);
    }

    /* Border colors for validation states */
    .border-green-500 {
        border-color: #10B981 !important;
    }
    
    .border-yellow-500 {
        border-color: #F59E0B !important;
    }
    
    .border-red-500 {
        border-color: #EF4444 !important;
    }
    
    /* Ring colors for validation states */
    .ring-green-200 {
        --tw-ring-color: rgba(187, 247, 208, 0.5);
    }
    
    .ring-yellow-200 {
        --tw-ring-color: rgba(253, 230, 138, 0.5);
    }
    
    .ring-red-200 {
        --tw-ring-color: rgba(254, 202, 202, 0.5);
    }
    
    /* Progress bar colors */
    .bg-green-500 {
        background-color: #10B981;
    }
    
    .bg-yellow-500 {
        background-color: #F59E0B;
    }
    
    .bg-red-500 {
        background-color: #EF4444;
    }
    
    /* Background colors for strength indicators */
    .bg-green-50 {
        background-color: #ECFDF5;
    }
    
    .bg-yellow-50 {
        background-color: #FFFBEB;
    }
    
    .bg-red-50 {
        background-color: #FEF2F2;
    }

    @keyframes pulse-once {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .animate-pulse-once {
        animation: pulse-once 0.3s ease-in-out;
    }
    
    .requirement-met {
        background-color: rgba(34, 197, 94, 0.1);
        padding: 2px 4px;
        border-radius: 4px;
        margin: 1px 0;
    }
    
    .requirement-not-met {
        padding: 2px 4px;
        border-radius: 4px;
        margin: 1px 0;
    }
    
    /* Smooth transitions for input states */
    input, select, textarea {
        transition: all 0.3s ease;
    }
    
    input:focus, select:focus, textarea:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(208, 54, 100, 0.1);
    }
    
    /* Custom scrollbar for modal */
    #registerModal > div::-webkit-scrollbar {
        width: 6px;
    }
    
    #registerModal > div::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    #registerModal > div::-webkit-scrollbar-thumb {
        background: var(--primary-pink);
        border-radius: 4px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 640px) {
        #registerModal {
            padding: 0.5rem;
            align-items: flex-start;
            padding-top: 1rem;
        }
        
        #registerModal > div {
            max-height: calc(100vh - 1rem);
            margin-top: 1rem;
            border-radius: 0.75rem;
        }
        
        .modal-header-pink {
            padding: 1rem;
        }
        
        .modal-header-pink h2 {
            font-size: 1.25rem;
        }
        
        .modal-header-pink p {
            font-size: 0.875rem;
        }
        
        .p-8 {
            padding: 1rem;
        }
        
        .gap-6 {
            gap: 1rem;
        }
        
        .space-y-6 > * + * {
            margin-top: 1rem;
        }
        
        .mb-8 {
            margin-bottom: 1.5rem;
        }
        
        input, select, textarea {
            font-size: 16px !important; /* Prevents zoom on iOS */
        }
    }
    
    /* Progress bar animation */
    #passwordStrengthBar {
        transition: width 0.5s ease-in-out, background-color 0.5s ease;
    }
    
    /* Requirement indicator animation */
    #passwordRequirements div {
        transition: all 0.3s ease;
    }
    </style>
    <script src="assets/toast.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="fixed top-0 w-full bg-white/95 backdrop-blur-sm shadow-sm z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-inter font-bold text-primary">AlagApp</h1>
                    </div>
                </div>
                
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="#home" class="text-gray-700 hover:text-primary px-3 py-2 text-sm font-medium transition-colors">Home</a>
                        <a href="#services" class="text-gray-700 hover:text-primary px-3 py-2 text-sm font-medium transition-colors">Services</a>
                        <a href="#doctors" class="text-gray-700 hover:text-primary px-3 py-2 text-sm font-medium transition-colors">Doctors</a>
                        <a href="#about" class="text-gray-700 hover:text-primary px-3 py-2 text-sm font-medium transition-colors">About</a>
                        <button onclick="openLoginModal()" class="btn-primary text-white px-6 py-2 rounded-lg font-medium">Login</button>
                    </div>
                </div>
                
                <div class="md:hidden">
                    <button onclick="toggleMobileMenu()" class="text-gray-700 hover:text-primary">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobileMenu" class="md:hidden hidden bg-white border-t">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="#home" class="block px-3 py-2 text-gray-700 hover:text-primary">Home</a>
                <a href="#services" class="block px-3 py-2 text-gray-700 hover:text-primary">Services</a>
                <a href="#doctors" class="block px-3 py-2 text-gray-700 hover:text-primary">Doctors</a>
                <a href="#about" class="block px-3 py-2 text-gray-700 hover:text-primary">About</a>
                <button onclick="openLoginModal()" class="w-full text-left px-3 py-2 text-primary font-medium">Login</button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-bg relative flex items-center justify-center">
        <div class="floating-particles">
            <div class="particle" style="left: 10%; animation-delay: 0s;"></div>
            <div class="particle" style="left: 20%; animation-delay: 1s;"></div>
            <div class="particle" style="left: 30%; animation-delay: 2s;"></div>
            <div class="particle" style="left: 40%; animation-delay: 3s;"></div>
            <div class="particle" style="left: 50%; animation-delay: 4s;"></div>
            <div class="particle" style="left: 60%; animation-delay: 5s;"></div>
            <div class="particle" style="left: 70%; animation-delay: 1.5s;"></div>
            <div class="particle" style="left: 80%; animation-delay: 2.5s;"></div>
            <div class="particle" style="left: 90%; animation-delay: 3.5s;"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-5xl md:text-7xl font-inter font-bold text-gray-800 mb-6">
                    <span class="typewriter">Caring for Little Ones</span>
                </h1>
                <p class="text-xl md:text-2xl text-gray-600 mb-8 leading-relaxed">
                    Comprehensive pediatric clinic management system designed for modern healthcare. 
                    Streamline appointments, track vaccinations, and manage patient records with ease.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <button onclick="openRegisterModal()" class="btn-primary text-white px-8 py-4 rounded-lg text-lg font-semibold">
                        Get Started Today
                    </button>
                    <button onclick="scrollToServices()" class="border-2 border-primary text-primary px-8 py-4 rounded-lg text-lg font-semibold hover:bg-primary hover:text-white transition-all">
                        Explore Services
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-inter font-bold text-gray-800 mb-4">Our Services</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Comprehensive pediatric healthcare services designed to meet the unique needs of children and families.
                </p>
            </div>
            
            <!-- Services Carousel -->
            <div class="splide" id="services-carousel">
                <div class="splide__track">
                    <ul class="splide__list">
                        <?php if (empty($landing_services)): ?>
                        <li class="splide__slide">
                            <div class="card-hover bg-white rounded-xl shadow-lg p-8 text-center border border-gray-100 h-full">
                                <div class="service-icon">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19ZM17 12H7V10H17V12Z"/>
                                    </svg>
                                </div>
                                <h3 class="text-2xl font-inter font-semibold text-gray-800 mb-4">Services Coming Soon</h3>
                                <p class="text-gray-600 mb-6">Our clinic is updating its list of services. Please check back soon.</p>
                                <button onclick="openRegisterModal()" class="text-primary font-semibold hover:underline">Register Today &rarr;</button>
                            </div>
                        </li>
                        <?php else: foreach ($landing_services as $svc): ?>
                        <li class="splide__slide">
                            <div class="card-hover bg-white rounded-xl shadow-lg p-8 text-center border border-gray-100 h-full flex flex-col">
                                <div class="service-icon">
                                    <svg viewBox="0 0 24 24">
                                        <path d="<?php echo htmlspecialchars(alag_service_icon_path($svc['name'])); ?>"/>
                                    </svg>
                                </div>
                                <h3 class="text-2xl font-inter font-semibold text-gray-800 mb-3"><?php echo htmlspecialchars($svc['name']); ?></h3>
                                <p class="text-gray-600 mb-4 flex-grow">
                                    <?php echo htmlspecialchars($svc['description'] !== '' ? $svc['description'] : 'Learn more about this service when you book an appointment.'); ?>
                                </p>
                                <div class="text-sm text-gray-500 mb-4 space-x-3">
                                    <?php if (!empty($svc['duration'])): ?>
                                        <span><i class="far fa-clock mr-1"></i><?php echo intval($svc['duration']); ?> mins</span>
                                    <?php endif; ?>
                                    <?php if (isset($svc['cost']) && $svc['cost'] !== null && $svc['cost'] !== ''): ?>
                                        <span class="font-semibold text-gray-700">&#8369;<?php echo number_format((float)$svc['cost'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button onclick="openRegisterModal()" class="text-primary font-semibold hover:underline mt-auto">Book Service &rarr;</button>
                            </div>
                        </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
                
                <!-- Carousel Progress Bar -->
                <div class="splide__progress mt-8">
                    <div class="splide__progress__bar bg-gray-200 h-1 rounded-full">
                        <div class="splide__progress__bar__fill bg-primary h-full rounded-full"></div>
                    </div>
                </div>
                
                <!-- Custom Navigation -->
                <div class="splide__arrows flex justify-center mt-6 space-x-4">
                    <button class="splide__arrow splide__arrow--prev bg-primary text-white p-3 rounded-full hover:bg-opacity-90 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <button class="splide__arrow splide__arrow--next bg-primary text-white p-3 rounded-full hover:bg-opacity-90 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Announcements Carousel Section -->
    <section id="announcements" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-inter font-bold text-gray-800 mb-4">Latest Announcements</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Stay updated with the latest news and important updates from AlagApp Clinic.
                </p>
            </div>
            
            <!-- Announcements Carousel -->
            <div class="splide" id="announcements-carousel">
                <div class="splide__track">
                    <ul class="splide__list">
                        <?php
                        // Fetch active announcements from database
                        $announcementQuery = "SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC";
                        $announcementResult = mysqli_query($conn, $announcementQuery);
                        
                        if ($announcementResult && mysqli_num_rows($announcementResult) > 0) {
                            while ($announcement = mysqli_fetch_assoc($announcementResult)) {
                                $formattedDate = date('M j, Y', strtotime($announcement['created_at']));
                                $categoryClass = getCategoryClass($announcement['category']);
                        ?>
                        <li class="splide__slide">
                            <div class="announcement-card bg-white rounded-xl shadow-lg border border-gray-100 p-6 h-full">
                                <div class="flex items-center justify-between mb-4">
                                    <span class="<?php echo $categoryClass; ?> px-3 py-1 rounded-full text-sm font-medium">
                                        <?php echo htmlspecialchars($announcement['category']); ?>
                                    </span>
                                    <span class="text-sm text-gray-500"><?php echo $formattedDate; ?></span>
                                </div>
                                
                                <h3 class="text-xl font-inter font-semibold text-gray-800 mb-3">
                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                </h3>
                                
                                <p class="text-gray-600 mb-4 line-clamp-3">
                                    <?php echo htmlspecialchars($announcement['content']); ?>
                                </p>
                                
                                <div class="flex items-center justify-between mt-auto">
                                    <span class="text-sm text-gray-500">By: <?php echo htmlspecialchars($announcement['author']); ?></span>
                                    <button onclick="openAnnouncementModal(<?php echo $announcement['id']; ?>)" 
                                            class="text-primary hover:text-primary-dark text-sm font-medium transition-colors">
                                        Read More →
                                    </button>
                                </div>
                            </div>
                        </li>
                        <?php
                            }
                        } else {
                            // Show placeholder if no announcements
                        ?>
                        <li class="splide__slide">
                            <div class="announcement-card bg-white rounded-xl shadow-lg border border-gray-100 p-6 h-full text-center">
                                <div class="service-icon mx-auto mb-4">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.2L4 17.2V4H20V16Z"/>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-inter font-semibold text-gray-800 mb-3">No Announcements</h3>
                                <p class="text-gray-600">Check back later for updates and important information.</p>
                            </div>
                        </li>
                        <?php
                        }
                        
                        // Helper function for category styling
                        function getCategoryClass($category) {
                            switch (strtoupper($category)) {
                                case 'MAINTENANCE':
                                    return 'bg-yellow-100 text-yellow-800';
                                case 'UPDATE':
                                    return 'bg-blue-100 text-blue-800';
                                case 'EVENT':
                                    return 'bg-green-100 text-green-800';
                                case 'SECURITY':
                                    return 'bg-red-100 text-red-800';
                                case 'FINANCE':
                                    return 'bg-purple-100 text-purple-800';
                                default:
                                    return 'bg-gray-100 text-gray-800';
                            }
                        }
                        ?>
                    </ul>
                </div>
                
                <!-- Carousel Progress Bar -->
                <div class="splide__progress mt-8">
                    <div class="splide__progress__bar bg-gray-200 h-1 rounded-full">
                        <div class="splide__progress__bar__fill bg-primary h-full rounded-full"></div>
                    </div>
                </div>
                
                <!-- Custom Navigation -->
                <div class="splide__arrows flex justify-center mt-6 space-x-4">
                    <button class="splide__arrow splide__arrow--prev bg-primary text-white p-3 rounded-full hover:bg-opacity-90 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <button class="splide__arrow splide__arrow--next bg-primary text-white p-3 rounded-full hover:bg-opacity-90 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Announcement Detail Modal -->
    <div id="announcementModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border-2 border-primary">
            <div class="p-8">
                <!-- Modal Header with Pink Background -->
                <div class="bg-gradient-to-r from-primary to-pink-400 -m-8 mb-6 p-6 rounded-t-xl">
                    <div class="flex items-center justify-between text-white">
                        <div>
                            <span id="modalCategory" class="bg-white/20 px-3 py-1 rounded-full text-sm font-medium backdrop-blur-sm"></span>
                            <span id="modalDate" class="text-pink-100 text-sm ml-3"></span>
                        </div>
                        <button onclick="closeAnnouncementModal()" class="text-white hover:text-pink-100 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <h2 id="modalTitle" class="text-2xl font-inter font-bold text-white mt-4"></h2>
                    <p id="modalAuthor" class="text-pink-100 text-sm mt-2"></p>
                </div>
                
                <!-- Modal Content -->
                <div id="announcementContent" class="prose prose-lg max-w-none text-gray-600">
                    <!-- Content will be loaded here via JavaScript -->
                </div>
                
                <div class="mt-8 text-center">
                    <button onclick="closeAnnouncementModal()" class="btn-primary text-white px-8 py-3 rounded-lg font-medium hover:shadow-lg transition-all">
                        Close Announcement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Doctors Section -->
    <section id="doctors" class="py-20 bg-light-pink/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Image on the left -->
                <div class="relative">
                    <img src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                        alt="Dr. Sarah Johnson" 
                        class="rounded-xl shadow-lg w-full">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent rounded-xl"></div>
                </div>
                
                <!-- Text content on the right -->
                <div>
                    <h2 class="text-4xl font-inter font-bold text-gray-800 mb-6">Meet Our Doctor</h2>
                    <p class="text-xl text-gray-600 mb-8">
                        Our experienced pediatric specialist dedicated to providing exceptional care for your children.
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Dr. Sarah Johnson</h3>
                                <p class="text-gray-600">15+ years of experience in pediatric medicine with special focus on developmental pediatrics and newborn care.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Credentials & Education</h3>
                                <p class="text-gray-600">Board Certified Pediatrician, MD from Harvard Medical School, Fellow of American Academy of Pediatrics.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Specializations</h3>
                                <p class="text-gray-600">Developmental Pediatrics, Newborn Care, Vaccination Schedules, Childhood Nutrition.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Clinic Information</h3>
                                <p class="text-gray-600">AlagApp Main Clinic, 123 Health Street, Medical City. Available Mon-Fri: 8AM-5PM, Sat: 9AM-1PM.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ratings and Book Appointment Button -->
                    <div class="mt-8 flex flex-col sm:flex-row items-start sm:items-center justify-between pt-6 border-t border-gray-200">
                        <div class="flex items-center mb-4 sm:mb-0">
                            <div class="flex text-yellow-400 mr-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700 font-medium">4.9 Rating</span>
                            <span class="mx-2 text-gray-400">•</span>
                            <span class="text-gray-600">500+ Patients</span>
                        </div>
                        <button onclick="openRegisterModal()" class="btn-primary text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 hover:scale-105">
                            Book Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-inter font-bold text-gray-800 mb-6">Why Choose AlagApp?</h2>
                    <p class="text-xl text-gray-600 mb-8">
                        Our comprehensive clinic management system streamlines healthcare delivery, making it easier for parents, doctors, and administrators to focus on what matters most - your child's health.
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Easy Appointment Booking</h3>
                                <p class="text-gray-600">Schedule appointments online with real-time availability and instant confirmation.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Vaccination Tracking</h3>
                                <p class="text-gray-600">Keep track of your child's immunization schedule with automated reminders.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Secure Medical Records</h3>
                                <p class="text-gray-600">Access and manage your child's health information securely from anywhere.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">24/7 Access</h3>
                                <p class="text-gray-600">Manage appointments and view records anytime, anywhere with our secure platform.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="relative">
                    <img src="https://images.unsplash.com/photo-1532938911079-1b06ac7ceec7?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80" alt="Happy children in clinic" class="rounded-xl shadow-lg w-full">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent rounded-xl"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h3 class="text-2xl font-inter font-bold text-primary mb-4">AlagApp Clinic</h3>
                <p class="text-gray-300 mb-6">Comprehensive pediatric healthcare management system</p>
                <div class="flex justify-center space-x-6 mb-8">
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                        </svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/>
                        </svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046.867-.22c1.489-.107 3.045.815 3.045 3.846v6.245zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </a>
                </div>
                <p class="text-gray-400 text-sm">
                    © 2024 AlagApp Clinic. All rights reserved. | Privacy Policy | Terms of Service
                </p>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-inter font-bold text-gray-800 mb-2">Welcome Back</h2>
                    <p class="text-gray-600">Sign in to your AlagApp account</p>
                </div>
                
                <form id="loginForm" method="POST" onsubmit="return validateLoginForm()">
                    <input type="hidden" name="login" value="1">
                    
                    <div class="space-y-6">
                        <div>
                            <label for="loginEmail" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="loginEmail" name="loginEmail" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                placeholder="Enter your email"
                                value="<?php echo isset($_POST['loginEmail']) ? htmlspecialchars($_POST['loginEmail']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="loginPassword" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <input type="password" id="loginPassword" name="loginPassword" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                placeholder="Enter your password">
                            <div class="mt-1 flex justify-end">
                                <button type="button" onclick="togglePasswordVisibility('loginPassword')" class="text-sm text-primary hover:underline">
                                    Show Password
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label class="flex items-center">
                                <input type="checkbox" name="rememberMe" class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-600">Remember me</span>
                            </label>
                            <button type="button" onclick="showForgotPassword()" class="text-sm text-primary hover:underline">Forgot password?</button>
                        </div>
                        
                        <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold transition-all duration-200 hover:scale-105">
                            <span id="loginButtonText">Sign In</span>
                            <div id="loginSpinner" class="hidden inline-block ml-2">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                            </div>
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <button onclick="switchToRegister()" class="text-primary font-semibold hover:underline">Sign up</button>
                    </p>
                </div>
                
                <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Register Modal - Complete Redesign -->
    <div id="registerModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[95vh] overflow-y-auto border-2 border-primary">
            <!-- Modal Header -->
            <div class="modal-header-pink p-6 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <h2 class="text-3xl font-inter font-bold">Join AlagApp Clinic</h2>
                        <p class="text-pink-100 mt-2">Create your parent account to get started</p>
                    </div>
                    <button onclick="closeRegisterModal()" class="text-white hover:text-pink-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Progress Steps -->
            <div class="px-8 pt-4">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold">1</div>
                        <span class="text-sm font-medium text-gray-700">Personal Info</span>
                    </div>
                    <div class="flex-1 h-1 mx-4 bg-gray-200"></div>
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-semibold">2</div>
                        <span class="text-sm text-gray-500">Contact & Security</span>
                    </div>
                </div>
            </div>
            
            <!-- Registration Form -->
            <div class="p-8">
                <form id="registerForm" method="POST" onsubmit="return validateRegisterForm()">
                    <input type="hidden" name="register" value="1">
                    
                    <!-- Section 1: Personal Information -->
                    <div class="mb-8">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                            Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- First Name -->
                            <div>
                                <label for="firstName" class="block text-sm font-medium text-gray-700 mb-2">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" id="firstName" name="firstName" required 
                                        class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                        placeholder="Enter first name"
                                        value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ''; ?>">
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Last Name -->
                            <div>
                                <label for="lastName" class="block text-sm font-medium text-gray-700 mb-2">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" id="lastName" name="lastName" required 
                                        class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                        placeholder="Enter last name"
                                        value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : ''; ?>">
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Date of Birth -->
                            <div>
                                <label for="dateOfBirth" class="block text-sm font-medium text-gray-700 mb-2">
                                    Date of Birth <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="date" id="dateOfBirth" name="dateOfBirth" required 
                                        class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                        max="<?php echo date('Y-m-d'); ?>">
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gender -->
                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select id="gender" name="gender" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 appearance-none">
                                    <option value="">Select gender</option>
                                    <option value="MALE">Male</option>
                                    <option value="FEMALE">Female</option>
                                    <option value="OTHER">Other / Prefer not to say</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 2: Contact & Address -->
                    <div class="mb-8">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            Contact Information
                        </h3>
                        
                        <div class="space-y-6">
                            <!-- Email Address -->
                            <div>
                                <label for="registerEmail" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="email" id="registerEmail" name="registerEmail" required 
                                        class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                        placeholder="your.email@example.com"
                                        value="<?php echo isset($_POST['registerEmail']) ? htmlspecialchars($_POST['registerEmail']) : ''; ?>"
                                        onblur="validateEmail()">
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div id="emailError" class="mt-1 text-sm text-red-600 hidden"></div>
                            </div>
                            
                            <!-- Phone Number -->
                            <div>
                                <label for="phoneNumber" class="block text-sm font-medium text-gray-700 mb-2">
                                    Phone Number <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500">+63</span>
                                    </div>
                                    <input type="tel" id="phoneNumber" name="phoneNumber" required 
                                        class="w-full pl-16 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                        placeholder="9XX XXX XXXX"
                                        pattern="[9]\d{9}"
                                        maxlength="10"
                                        title="Philippine mobile number format: 9XXXXXXXXX (10 digits starting with 9)"
                                        value="<?php echo isset($_POST['phoneNumber']) ? htmlspecialchars($_POST['phoneNumber']) : ''; ?>"
                                        oninput="formatPhoneNumber(this)">
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Philippine mobile number format: 9XXXXXXXXX</p>
                            </div>
                            
                            <!-- Address -->
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Address
                                </label>
                                <textarea id="address" name="address" rows="2"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 resize-none"
                                    placeholder="Enter your complete address (optional)"></textarea>
                            </div>
                            
                            <!-- Emergency Contact Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="emergencyContactName" class="block text-sm font-medium text-gray-700 mb-2">
                                        Emergency Contact Name
                                    </label>
                                    <input type="text" id="emergencyContactName" name="emergencyContactName"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                        placeholder="Full name of emergency contact">
                                </div>
                                <div>
                                    <label for="emergencyContactPhone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Emergency Contact Phone
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">+63</span>
                                        </div>
                                        <input type="tel" id="emergencyContactPhone" name="emergencyContactPhone"
                                            class="w-full pl-16 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                            placeholder="9XX XXX XXXX"
                                            pattern="[9]\d{9}"
                                            maxlength="10"
                                            title="Philippine mobile number format: 9XXXXXXXXX">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 3: Account Security -->
                    <div class="mb-8">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            Account Security
                        </h3>
                        
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Password -->
                                <div>
                                    <label for="registerPassword" class="block text-sm font-medium text-gray-700 mb-2">
                                        Password <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="registerPassword" name="registerPassword" required 
                                            class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 password-strength-input"
                                            placeholder="Create secure password"
                                            oninput="checkPasswordStrength(this.value)"
                                            onfocus="showPasswordRequirements()"
                                            onblur="hidePasswordRequirements()">
                                        <button type="button" onclick="togglePasswordVisibility('registerPassword')" 
                                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <svg class="w-5 h-5 eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <!-- Password Strength Indicator -->
                                    <div class="mt-2 flex justify-between items-center">
                                        <div id="passwordStrength" class="text-sm font-medium"></div>
                                    </div>
                                </div>
                                
                                <!-- Confirm Password -->
                                <div>
                                    <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirm Password <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="confirmPassword" name="confirmPassword" required 
                                            class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 password-match-input"
                                            placeholder="Confirm your password"
                                            oninput="checkPasswordMatch()">
                                        <button type="button" onclick="togglePasswordVisibility('confirmPassword')" 
                                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <svg class="w-5 h-5 eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="mt-2 text-sm hidden"></div>
                                </div>
                            </div>
                            
                            <!-- Password Requirements Panel -->
                            <div id="passwordRequirements" class="mt-4 hidden bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                <p class="text-sm font-medium text-gray-700 mb-3">Password Requirements:</p>
                                <div class="space-y-2">
                                    <div id="reqLength" class="flex items-center transition-all duration-300">
                                        <span class="mr-2 text-gray-400">○</span>
                                        <span class="text-xs text-gray-600">At least 6 characters</span>
                                    </div>
                                    <div id="reqUppercase" class="flex items-center transition-all duration-300">
                                        <span class="mr-2 text-gray-400">○</span>
                                        <span class="text-xs text-gray-600">One uppercase letter (A-Z)</span>
                                    </div>
                                    <div id="reqLowercase" class="flex items-center transition-all duration-300">
                                        <span class="mr-2 text-gray-400">○</span>
                                        <span class="text-xs text-gray-600">One lowercase letter (a-z)</span>
                                    </div>
                                    <div id="reqNumber" class="flex items-center transition-all duration-300">
                                        <span class="mr-2 text-gray-400">○</span>
                                        <span class="text-xs text-gray-600">One number (0-9)</span>
                                    </div>
                                    <div id="reqSpecial" class="flex items-center transition-all duration-300">
                                        <span class="mr-2 text-gray-400">○</span>
                                        <span class="text-xs text-gray-600">One special character (!@#$%^&*)</span>
                                    </div>
                                </div>
                                
                                <!-- Visual Strength Meter -->
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span>Weak</span>
                                        <span>Medium</span>
                                        <span>Strong</span>
                                    </div>
                                    <div id="passwordStrengthVisual" class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div id="passwordStrengthBar" class="h-full bg-gray-400 transition-all duration-300" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms & Conditions -->
                    <div class="mb-8">
                        <div class="flex items-start p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <input type="checkbox" id="terms" name="terms" required 
                                class="rounded border-gray-300 text-primary focus:ring-primary mt-1 flex-shrink-0">
                            <label for="terms" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">I agree to the Terms of Service and Privacy Policy</span>
                                <span class="text-red-500 ml-1">*</span>
                                <p class="mt-1 text-gray-600">
                                    By creating an account, you agree to our terms and acknowledge our privacy practices. 
                                    You can manage your preferences at any time in your account settings.
                                </p>
                            </label>
                        </div>
                        <div id="termsError" class="mt-2 text-sm text-red-600 hidden">
                            You must agree to the Terms of Service and Privacy Policy to continue.
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="button" onclick="closeRegisterModal()" 
                                class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-all duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 btn-primary text-white py-3 rounded-lg font-semibold transition-all duration-200 hover:scale-[1.02] flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span id="registerButtonText">Create Account</span>
                            <div id="registerSpinner" class="hidden ml-2">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                            </div>
                        </button>
                    </div>
                </form>
                
                <!-- Login Link -->
                <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                    <p class="text-sm text-gray-600">
                        Already have an account? 
                        <button onclick="switchToLogin()" class="text-primary font-semibold hover:underline ml-1">
                            Sign in to your account
                        </button>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-inter font-bold text-gray-800 mb-2">Reset Password</h2>
                    <p class="text-gray-600">Enter your email to receive reset instructions</p>
                </div>
                
                <form id="forgotPasswordForm">
                    <div class="space-y-6">
                        <div>
                            <label for="forgotEmail" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="forgotEmail" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter your registered email">
                        </div>
                        
                        <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold">
                            Send Reset Instructions
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 text-center">
                    <button onclick="closeForgotPassword()" class="text-primary font-semibold hover:underline">Back to login</button>
                </div>
                
                <button onclick="closeForgotPassword()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <script>
    // Initialize Services Carousel
    document.addEventListener('DOMContentLoaded', function() {
        // Services Carousel
        const servicesCarousel = new Splide('#services-carousel', {
            type: 'loop',
            perPage: 3,
            perMove: 1,
            gap: '2rem',
            pagination: false,
            arrows: true,
            breakpoints: {
                1024: {
                    perPage: 2,
                },
                768: {
                    perPage: 1,
                }
            }
        });
        
        servicesCarousel.mount();
        
        // Announcements Carousel
        const announcementsCarousel = new Splide('#announcements-carousel', {
            type: 'loop',
            perPage: 3,
            perMove: 1,
            gap: '2rem',
            pagination: false,
            arrows: true,
            autoplay: true,
            interval: 5000,
            pauseOnHover: true,
            breakpoints: {
                1024: {
                    perPage: 2,
                },
                768: {
                    perPage: 1,
                }
            }
        });
        
        announcementsCarousel.mount();
        
        // Initialize form event listeners
        const passwordField = document.getElementById('registerPassword');
        const confirmField = document.getElementById('confirmPassword');
        const emailField = document.getElementById('registerEmail');
        
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                checkPasswordStrength(this.value);
            });
            
            passwordField.addEventListener('focus', function() {
                showPasswordRequirements();
            });
        }
        
        if (confirmField) {
            confirmField.addEventListener('input', function() {
                checkPasswordMatch();
            });
        }
        
        if (emailField) {
            emailField.addEventListener('blur', function() {
                validateEmail();
            });
        }
    });

    // Modal functions
    function openLoginModal() {
        document.getElementById('loginModal').classList.remove('hidden');
    }

    function closeLoginModal() {
        document.getElementById('loginModal').classList.add('hidden');
    }

    function openRegisterModal() {
        document.getElementById('registerModal').classList.remove('hidden');
    }

    function closeRegisterModal() {
        document.getElementById('registerModal').classList.add('hidden');
    }

    function switchToRegister() {
        closeLoginModal();
        setTimeout(() => openRegisterModal(), 300);
    }

    function switchToLogin() {
        closeRegisterModal();
        setTimeout(() => openLoginModal(), 300);
    }

    function showForgotPassword() {
        closeLoginModal();
        document.getElementById('forgotPasswordModal').classList.remove('hidden');
    }

    function closeForgotPassword() {
        document.getElementById('forgotPasswordModal').classList.add('hidden');
        openLoginModal();
    }

    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    }

    function scrollToServices() {
        document.getElementById('services').scrollIntoView({ behavior: 'smooth' });
    }

    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.parentNode.querySelector('button');
        
        if (field.type === 'password') {
            field.type = 'text';
            button.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
            `;
        } else {
            field.type = 'password';
            button.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            `;
        }
    }

    // Enhanced Password Validation Functions
    function showPasswordRequirements() {
        document.getElementById('passwordRequirements').classList.remove('hidden');
        // Update immediately when shown
        const password = document.getElementById('registerPassword').value;
        if (password.length > 0) {
            checkPasswordStrength(password);
        }
    }

    function hidePasswordRequirements() {
        // Don't hide if password field still has focus
        if (document.activeElement.id !== 'registerPassword') {
            document.getElementById('passwordRequirements').classList.add('hidden');
        }
    }

    function checkPasswordStrength(password) {
        const strength = document.getElementById('passwordStrength');
        const passwordField = document.getElementById('registerPassword');
        const requirements = {
            length: password.length >= 6,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*]/.test(password)
        };
        
        // Update requirement indicators with colorful feedback
        updateRequirementIndicator('reqLength', requirements.length);
        updateRequirementIndicator('reqUppercase', requirements.uppercase);
        updateRequirementIndicator('reqLowercase', requirements.lowercase);
        updateRequirementIndicator('reqNumber', requirements.number);
        updateRequirementIndicator('reqSpecial', requirements.special);
        
        // Calculate strength score
        const score = Object.values(requirements).filter(Boolean).length;
        
        let strengthText = '';
        let color = 'text-red-500';
        let bgColor = 'bg-red-50';
        let borderColor = 'border-red-400';
        let strengthBarColor = 'bg-red-500';
        
        // Update password field styling based on strength
        if (score === 5) {
            strengthText = 'Strong Password ✓';
            color = 'text-green-600';
            bgColor = 'bg-green-50';
            borderColor = 'border-green-400';
            strengthBarColor = 'bg-green-500';
            passwordField.classList.remove('border-red-500', 'border-yellow-500');
            passwordField.classList.add('border-green-500', 'ring-2', 'ring-green-200');
        } else if (score >= 3) {
            strengthText = 'Medium Password';
            color = 'text-yellow-600';
            bgColor = 'bg-yellow-50';
            borderColor = 'border-yellow-400';
            strengthBarColor = 'bg-yellow-500';
            passwordField.classList.remove('border-red-500', 'border-green-500');
            passwordField.classList.add('border-yellow-500', 'ring-2', 'ring-yellow-200');
        } else if (password.length > 0) {
            strengthText = 'Weak Password';
            color = 'text-red-600';
            bgColor = 'bg-red-50';
            borderColor = 'border-red-400';
            strengthBarColor = 'bg-red-500';
            passwordField.classList.remove('border-yellow-500', 'border-green-500');
            passwordField.classList.add('border-red-500', 'ring-2', 'ring-red-200');
        } else {
            // Reset when empty
            strengthText = '';
            passwordField.classList.remove('border-red-500', 'border-yellow-500', 'border-green-500', 'ring-2', 'ring-green-200', 'ring-yellow-200', 'ring-red-200');
        }
        
        // Update strength display with enhanced visual feedback
        if (strengthText) {
            strength.innerHTML = `
                <div class="inline-flex items-center px-3 py-1 rounded-full ${bgColor} ${color} border ${borderColor}">
                    <span class="mr-2 text-sm font-medium">${strengthText}</span>
                    ${score === 5 ? '🎉' : score >= 3 ? '⚠️' : '❌'}
                </div>
            `;
        } else {
            strength.innerHTML = '';
        }
        
        // Add visual progress bar for password strength
        updatePasswordStrengthBar(score, strengthBarColor);
        
        // Return whether all requirements are met
        return Object.values(requirements).every(Boolean);
    }

    function updateRequirementIndicator(elementId, isMet) {
        const element = document.getElementById(elementId);
        const icon = element.querySelector('span');
        const text = element.querySelector('span:last-child');
        
        if (isMet) {
            icon.innerHTML = '✓';
            icon.className = 'mr-2 text-green-500 font-bold text-sm';
            text.className = 'text-xs text-green-600 font-medium';
            element.classList.add('requirement-met');
            element.classList.remove('requirement-not-met');
            // Add subtle animation when requirement is met
            element.classList.add('animate-pulse-once');
            setTimeout(() => element.classList.remove('animate-pulse-once'), 300);
        } else {
            icon.innerHTML = '○';
            icon.className = 'mr-2 text-gray-400';
            text.className = 'text-xs text-gray-600';
            element.classList.add('requirement-not-met');
            element.classList.remove('requirement-met');
            element.classList.remove('animate-pulse-once');
        }
    }

    function updatePasswordStrengthBar(score, color) {
        let strengthBar = document.getElementById('passwordStrengthBar');
        
        if (!strengthBar) {
            // Create strength bar if it doesn't exist
            const container = document.getElementById('passwordStrengthVisual');
            strengthBar = document.createElement('div');
            strengthBar.id = 'passwordStrengthBar';
            strengthBar.className = 'h-full transition-all duration-300';
            container.appendChild(strengthBar);
        }
        
        const percentage = (score / 5) * 100;
        strengthBar.className = `h-full transition-all duration-300 ${color}`;
        strengthBar.style.width = `${percentage}%`;
    }

    function checkPasswordMatch() {
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const confirmField = document.getElementById('confirmPassword');
        const matchDiv = document.getElementById('passwordMatch');
        
        if (confirmPassword.length === 0) {
            matchDiv.classList.add('hidden');
            confirmField.classList.remove('border-green-500', 'ring-2', 'ring-green-200');
            confirmField.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
            return;
        }
        
        if (password === confirmPassword) {
            matchDiv.innerHTML = `
                <div class="inline-flex items-center px-3 py-1 rounded-full bg-green-50 text-green-600 border border-green-400">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Passwords match ✓
                </div>
            `;
            matchDiv.classList.remove('hidden');
            confirmField.classList.add('border-green-500', 'ring-2', 'ring-green-200');
            confirmField.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
        } else {
            matchDiv.innerHTML = `
                <div class="inline-flex items-center px-3 py-1 rounded-full bg-red-50 text-red-600 border border-red-400">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Passwords do not match ✗
                </div>
            `;
            matchDiv.classList.remove('hidden');
            confirmField.classList.add('border-red-500', 'ring-2', 'ring-red-200');
            confirmField.classList.remove('border-green-500', 'ring-2', 'ring-green-200');
        }
    }

    function validateEmail() {
        const email = document.getElementById('registerEmail').value;
        const emailError = document.getElementById('emailError');
        const emailField = document.getElementById('registerEmail');
        
        if (!email) {
            emailError.classList.add('hidden');
            emailField.classList.remove('border-green-500', 'ring-2', 'ring-green-200');
            emailField.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
            return true;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!emailRegex.test(email)) {
            emailError.textContent = 'Please enter a valid email address';
            emailError.classList.remove('hidden');
            emailField.classList.add('border-red-500', 'ring-2', 'ring-red-200');
            emailField.classList.remove('border-green-500', 'ring-2', 'ring-green-200');
            return false;
        }
        
        emailError.classList.add('hidden');
        emailField.classList.add('border-green-500', 'ring-2', 'ring-green-200');
        emailField.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
        return true;
    }

    // Form Validation Functions
    function validateLoginForm() {
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;
        
        if (!email || !password) {
            if (window.showToast) {
                window.showToast('Please fill in all fields.', 'warning');
            } else {
                alert('Please fill in all fields');
            }
            return false;
        }
        return true;
    }

    function validateRegisterForm() {
        const firstName = document.getElementById('firstName').value.trim();
        const lastName = document.getElementById('lastName').value.trim();
        const email = document.getElementById('registerEmail').value.trim();
        const phone = document.getElementById('phoneNumber').value.trim();
        const dateOfBirth = document.getElementById('dateOfBirth').value;
        const gender = document.getElementById('gender').value;
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const terms = document.getElementById('terms').checked;

        // Check required fields
        const requiredFields = [
            { id: 'firstName', value: firstName, name: 'First Name' },
            { id: 'lastName', value: lastName, name: 'Last Name' },
            { id: 'registerEmail', value: email, name: 'Email' },
            { id: 'phoneNumber', value: phone, name: 'Phone Number' },
            { id: 'dateOfBirth', value: dateOfBirth, name: 'Date of Birth' },
            { id: 'gender', value: gender, name: 'Gender' },
            { id: 'registerPassword', value: password, name: 'Password' },
            { id: 'confirmPassword', value: confirmPassword, name: 'Confirm Password' }
        ];

        let hasErrors = false;
        
        for (const field of requiredFields) {
            if (!field.value) {
                showFieldError(field.id, `${field.name} is required`);
                hasErrors = true;
            } else {
                clearFieldError(field.id);
            }
        }

        // Validate email
        if (email && !validateEmail()) {
            hasErrors = true;
        }

        // Validate phone number (Philippine format)
        if (phone) {
            const cleanPhone = phone.replace(/\s/g, '');
            const phoneRegex = /^9\d{9}$/;
            if (!phoneRegex.test(cleanPhone)) {
                showFieldError('phoneNumber', 'Please enter a valid Philippine mobile number (10 digits starting with 9)');
                hasErrors = true;
            }
        }

        // Validate password strength
        if (password && !checkPasswordStrength(password)) {
            showFieldError('registerPassword', 'Please ensure your password meets all requirements');
            hasErrors = true;
        }

        // Check passwords match
        if (password && confirmPassword && password !== confirmPassword) {
            showFieldError('confirmPassword', 'Passwords do not match');
            hasErrors = true;
        }

        if (!terms) {
            document.getElementById('termsError').classList.remove('hidden');
            hasErrors = true;
        } else {
            document.getElementById('termsError').classList.add('hidden');
        }

        if (hasErrors) {
            return false;
        }

        // Show loading state
        const submitBtn = document.querySelector('#registerForm button[type="submit"]');
        const originalText = submitBtn.querySelector('#registerButtonText').textContent;
        const spinner = document.getElementById('registerSpinner');
        
        submitBtn.querySelector('#registerButtonText').textContent = 'Creating Account...';
        spinner.classList.remove('hidden');
        submitBtn.disabled = true;

        return true;
    }

    // Helper functions for field validation
    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById(fieldId + 'Error');
        
        if (!errorDiv) {
            // Create error div if it doesn't exist
            const div = document.createElement('div');
            div.id = fieldId + 'Error';
            div.className = 'mt-1 text-sm text-red-600';
            field.parentNode.appendChild(div);
        } else {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
        }
        
        field.classList.add('border-red-500');
        field.classList.add('ring-2');
        field.classList.add('ring-red-200');
        
        // Scroll to error field
        setTimeout(() => {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus();
        }, 100);
    }

    function clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById(fieldId + 'Error');
        
        if (errorDiv) {
            errorDiv.classList.add('hidden');
        }
        
        field.classList.remove('border-red-500');
        field.classList.remove('ring-2');
        field.classList.remove('ring-red-200');
    }

    function formatPhoneNumber(input) {
        // Remove all non-digits
        let phone = input.value.replace(/\D/g, '');
        
        // Ensure it starts with 9 (Philippine mobile)
        if (phone.length > 0 && phone.charAt(0) !== '9') {
            phone = '9' + phone;
        }
        
        // Limit to 10 digits
        phone = phone.substring(0, 10);
        
        // Format: 9XX XXX XXXX
        if (phone.length > 3) {
            phone = phone.substring(0, 3) + ' ' + phone.substring(3);
        }
        if (phone.length > 7) {
            phone = phone.substring(0, 7) + ' ' + phone.substring(7);
        }
        
        input.value = phone;
    }

    // Announcement Modal Functions
    function openAnnouncementModal(announcementId) {
        fetchAnnouncementDetails(announcementId);
        document.getElementById('announcementModal').classList.remove('hidden');
    }

    function closeAnnouncementModal() {
        document.getElementById('announcementModal').classList.add('hidden');
    }

    function fetchAnnouncementDetails(announcementId) {
        // In a real implementation, you would make an AJAX call here
        // For demo purposes, we'll simulate loading and show sample content
        
        const modalContent = document.getElementById('announcementContent');
        modalContent.innerHTML = `
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
                <p class="text-gray-500 mt-4">Loading announcement...</p>
            </div>
        `;
        
        // Simulate API call - replace with actual AJAX call
        setTimeout(() => {
            // Sample data - replace with actual data from your AJAX response
            const sampleData = {
                title: "System Maintenance Scheduled",
                category: "MAINTENANCE",
                date: "Nov 24, 2025",
                author: "By: IT Department",
                content: `
                    <p>Our system will undergo maintenance on Saturday from 2:00 AM to 6:00 AM. Services may be temporarily unavailable during this period.</p>
                    <p class="mt-4">We apologize for any inconvenience this may cause and appreciate your understanding as we work to improve our services.</p>
                    <div class="bg-pink-50 border-l-4 border-primary p-4 mt-6 rounded">
                        <p class="text-pink-800 font-semibold">Important Notes:</p>
                        <ul class="list-disc list-inside mt-2 text-pink-700">
                            <li>Emergency services will remain available</li>
                            <li>All data will be securely backed up</li>
                            <li>Normal operations will resume after maintenance</li>
                        </ul>
                    </div>
                `
            };
            
            // Update modal with data
            document.getElementById('modalTitle').textContent = sampleData.title;
            document.getElementById('modalCategory').textContent = sampleData.category;
            document.getElementById('modalDate').textContent = sampleData.date;
            document.getElementById('modalAuthor').textContent = sampleData.author;
            modalContent.innerHTML = sampleData.content;
        }, 800);
    }
    </script>
    <?php if (!empty($flashMessages) || !empty($flashActions)): ?>
    <script>
    (function () {
        function run() {
            <?php foreach ($flashMessages as $f): ?>
            if (typeof window.showToast === 'function') {
                window.showToast(<?php echo json_encode($f['message']); ?>, <?php echo json_encode($f['type']); ?>);
            }
            <?php endforeach; ?>
            <?php foreach ($flashActions as $a): echo "\n            " . $a; endforeach; ?>
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