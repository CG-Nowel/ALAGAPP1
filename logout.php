<?php
// logout.php
require_once 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    // Log logout activity
    $log_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'LOGOUT', 'User logged out', ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    mysqli_stmt_bind_param($log_stmt, "is", $_SESSION['user_id'], $ip_address);
    mysqli_stmt_execute($log_stmt);
    
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

// Redirect to login page
header('Location: index.php');
exit;
?>