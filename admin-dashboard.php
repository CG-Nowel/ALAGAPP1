<?php
// admin-dashboard.php
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check if user is admin
if ($_SESSION['user_type'] !== 'ADMIN') {
    header('Location: dashboard.php'); // Redirect to regular user dashboard
    exit;
}

$current_user = get_current_session_user();


// Get dashboard statistics
function get_dashboard_stats($conn) {
    $stats = [
        'total_users' => 0,
        'total_children' => 0,
        'total_appointments' => 0,
        'total_vaccinations' => 0
    ];
    
    try {
        // Total users
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['total_users'] = $row['total'] ?? 0;
        }
        
        // Total patients (children)
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM patients");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['total_children'] = $row['total'] ?? 0;
        }
        
        // Total appointments
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM appointments");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['total_appointments'] = $row['total'] ?? 0;
        }
        
        // Total vaccinations
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM vaccination_records");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['total_vaccinations'] = $row['total'] ?? 0;
        }
    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
    }
    
    return $stats;
}

// Get recent activity
function get_recent_activity($conn, $limit = 10) {
    $activity = [];
    
    try {
        $query = "SELECT al.*, u.first_name, u.last_name 
                  FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  ORDER BY al.timestamp DESC 
                  LIMIT $limit";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $activity[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting recent activity: " . $e->getMessage());
    }
    
    return $activity;
}

// Get data for different sections
function get_all_users($conn) {
    $users = [];
    $query = "SELECT * FROM users ORDER BY created_at DESC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
    }
    return $users;
}

function get_doctor_schedules($conn) {
    $schedules = [];
    $query = "SELECT ds.*, u.first_name, u.last_name 
              FROM doctor_schedules ds 
              JOIN users u ON ds.doctor_id = u.id 
              ORDER BY 
                FIELD(ds.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'),
                ds.start_time";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $schedules[] = $row;
        }
    }
    return $schedules;
}

function get_all_services($conn) {
    $services = [];
    $query = "SELECT * FROM services ORDER BY name";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $services[] = $row;
        }
    }
    return $services;
}

function get_all_appointments($conn) {
    $appointments = [];
    $query = "SELECT a.*, 
              p.first_name as patient_first_name, p.last_name as patient_last_name,
              d.first_name as doctor_first_name, d.last_name as doctor_last_name,
              u.first_name as created_by_first_name, u.last_name as created_by_last_name
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              JOIN users d ON a.doctor_id = d.id
              LEFT JOIN users u ON a.created_by = u.id
              ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $appointments[] = $row;
        }
    }
    return $appointments;
}

function get_clinic_settings($conn) {
    $settings = [];
    $query = "SELECT * FROM clinic_settings";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['setting_key']] = $row;
        }
    }
    return $settings;
}

function get_activity_logs($conn, $limit = 50) {
    $logs = [];
    $query = "SELECT al.*, u.first_name, u.last_name 
              FROM activity_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              ORDER BY al.timestamp DESC 
              LIMIT $limit";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $logs[] = $row;
        }
    }
    return $logs;
}

$stats = get_dashboard_stats($conn);
$recent_activity = get_recent_activity($conn);
$all_users = get_all_users($conn);
$doctor_schedules = get_doctor_schedules($conn);
$services = get_all_services($conn);
$appointments = get_all_appointments($conn);
$clinic_settings = get_clinic_settings($conn);
$activity_logs = get_activity_logs($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AlagApp Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary-pink: #FF6B9A; --light-pink: #FFBCD9; --dark-text: #333333; --light-gray: #F6F6F8; }
        body { font-family: 'Source Sans Pro', sans-serif; background: linear-gradient(135deg, #ffffff 0%, #fef5f8 100%); }
        .font-inter { font-family: 'Inter', sans-serif; }
        .text-primary { color: var(--primary-pink); }
        .bg-primary { background-color: var(--primary-pink); }
        .bg-light-pink { background-color: var(--light-pink); }
        .sidebar { background: linear-gradient(180deg, #FF6B9A 0%, #FFBCD9 100%); min-height: 100vh; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 15px 35px rgba(255, 107, 154, 0.15); }
        .btn-primary { background: linear-gradient(135deg, var(--primary-pink), var(--light-pink)); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(255, 107, 154, 0.3); }
        .modal-backdrop { backdrop-filter: blur(8px); background: rgba(0, 0, 0, 0.4); }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #f3e8ff; color: #7c3aed; }
        .role-parent { background: #dbeafe; color: #1e40af; }
        .role-doctor { background: #d1fae5; color: #065f46; }
        .role-admin { background: #f3e8ff; color: #7c3aed; }
        .data-table { max-height: 400px; overflow-y: auto; }
        .data-table::-webkit-scrollbar { width: 6px; }
        .data-table::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .data-table::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
        .data-table::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
        
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.success { background: #10B981; }
        .notification.error { background: #EF4444; }
        .notification.info { background: #3B82F6; }
    </style>
    <script src="assets/toast.js"></script>
</head>
<body>
    <!-- Notification Container -->
    <div id="notification" class="notification hidden"></div>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="sidebar w-64 text-white">
            <div class="p-6">
                <h1 class="text-2xl font-inter font-bold mb-8">AlagApp</h1>
                
                <div class="mb-8">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold" id="adminName">
                                <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                            </div>
                            <div class="text-sm text-white/80">System Administrator</div>
                        </div>
                    </div>
                </div>
                
                <nav class="space-y-2">
                    <a href="#dashboard" onclick="showSection('dashboard')" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white/20">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path></svg>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="#users" onclick="showSection('users')" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                        <span>User Management</span>
                    </a>
                    
                    <a href="#schedules" onclick="showSection('schedules')" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path></svg>
                        <span>Doctor Schedules</span>
                    </a>
                    
                    <a href="#services" onclick="showSection('services')" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path></svg>
                        <span>Services & Vaccines</span>
                    </a>
                    
                    <a href="#appointments" onclick="showSection('appointments')" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path></svg>
                        <span>All Appointments</span>
                    </a>
                    
                    <a href="#analytics" onclick="showSection('analytics')" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path></svg>
                        <span>Analytics</span>
                    </a>
                    
                    <a href="#settings" onclick="showSection('settings')" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path></svg>
                        <span>System Settings</span>
                    </a>
                    
                    <a href="#logs" onclick="showSection('logs')" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/20 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path></svg>
                        <span>Audit Logs</span>
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
        <div class="flex-1 overflow-auto">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="section-content p-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Admin Dashboard</h1>
                    <p class="text-gray-600">System overview and management</p>
                </div>
                
                <!-- System Overview Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100">
                                <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                                <div class="text-sm text-gray-600">Total Users</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100">
                                <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_children']; ?></div>
                                <div class="text-sm text-gray-600">Total Children</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100">
                                <svg class="w-8 h-8 text-orange-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_appointments']; ?></div>
                                <div class="text-sm text-gray-600">Total Appointments</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100">
                                <svg class="w-8 h-8 text-purple-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_vaccinations']; ?></div>
                                <div class="text-sm text-gray-600">Total Vaccinations</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Recent Activity</h3>
                        <div id="recentActivity" class="space-y-4">
                            <?php if (empty($recent_activity)): ?>
                                <p class="text-gray-500">No recent activity</p>
                            <?php else: ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($activity['action'] ?? 'Unknown Action'); ?></div>
                                            <div class="text-sm text-gray-600"><?php echo htmlspecialchars($activity['details'] ?? 'No details'); ?></div>
                                            <div class="text-sm text-gray-500">User: <?php echo htmlspecialchars(($activity['first_name'] ?? 'Unknown') . ' ' . ($activity['last_name'] ?? 'User')); ?></div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($activity['ip_address'] ?? 'Unknown IP'); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">System Health</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Database Status</span>
                                <span class="px-2 py-1 text-xs font-medium rounded-full status-active">Healthy</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Backup Status</span>
                                <span class="px-2 py-1 text-xs font-medium rounded-full status-active">Current</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Security Status</span>
                                <span class="px-2 py-1 text-xs font-medium rounded-full status-active">Secure</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">System Load</span>
                                <span class="px-2 py-1 text-xs font-medium rounded-full status-active">Normal</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Management Section -->
            <div id="users-section" class="section-content p-8 hidden">
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">User Management</h1>
                            <p class="text-gray-600">Manage system users and permissions</p>
                        </div>
                        <button onclick="openAddUserModal()" class="btn-primary text-white px-6 py-3 rounded-lg font-semibold">
                            Add User
                        </button>
                    </div>
                </div>
                
                <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800">All Users</h3>
                        <div class="flex space-x-4">
                            <select id="userRoleFilter" onchange="filterUsers()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Roles</option>
                                <option value="PARENT">Parents</option>
                                <option value="DOCTOR">Doctors</option>
                                <option value="ADMIN">Admins</option>
                            </select>
                            <select id="userStatusFilter" onchange="filterUsers()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="data-table">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody" class="bg-white divide-y divide-gray-200">
                                <?php foreach ($all_users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getRoleBadge($user['user_type']); ?>"><?php echo $user['user_type']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editUser(<?php echo $user['id']; ?>)" class="text-primary hover:underline mr-3">Edit</button>
                                        <button onclick="toggleUserStatus(<?php echo $user['id']; ?>)" class="text-<?php echo $user['status'] === 'active' ? 'red' : 'green'; ?>-600 hover:underline">
                                            <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Doctor Schedule Section -->
            <div id="schedules-section" class="section-content p-8 hidden">
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Doctor Schedule</h1>
                            <p class="text-gray-600">Manage doctor availability and schedules</p>
                        </div>
                        <button onclick="openAddScheduleModal()" class="btn-primary text-white px-6 py-3 rounded-lg font-semibold">
                            Add Schedule
                        </button>
                    </div>
                </div>
                
                <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-6">Current Schedule</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php 
                        $days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
                        foreach ($days as $day): 
                            $daySchedules = array_filter($doctor_schedules, function($schedule) use ($day) {
                                return $schedule['day_of_week'] === $day && $schedule['active'] == 1;
                            });
                        ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-800 mb-3"><?php echo $day; ?></h4>
                            <?php if (!empty($daySchedules)): ?>
                                <?php foreach ($daySchedules as $schedule): ?>
                                <div class="bg-gray-50 rounded p-3 mb-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium"><?php echo date('g:i A', strtotime($schedule['start_time'])) . ' - ' . date('g:i A', strtotime($schedule['end_time'])); ?></span>
                                        <div class="flex space-x-1">
                                            <button onclick="editSchedule(<?php echo $schedule['id']; ?>)" class="text-primary hover:text-primary-dark text-sm">Edit</button>
                                            <button onclick="deleteSchedule(<?php echo $schedule['id']; ?>)" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Duration: <?php echo $schedule['slot_duration']; ?> mins • 
                                        Max Patients: <?php echo $schedule['max_patients']; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm">No schedule</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Services & Vaccines Section -->
            <div id="services-section" class="section-content p-8 hidden">
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Services & Vaccines</h1>
                            <p class="text-gray-600">Manage clinic services and vaccination offerings</p>
                        </div>
                        <div class="flex space-x-4">
                            <button onclick="openAddServiceModal()" class="btn-primary text-white px-6 py-3 rounded-lg font-semibold">
                                Add Service
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-6">Clinic Services</h3>
                    
                    <div class="data-table">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($services as $service): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($service['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($service['description']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $service['duration']; ?> mins</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">&#8369;<?php echo number_format($service['cost'], 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $service['active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $service['active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editService(<?php echo $service['id']; ?>)" class="text-primary hover:underline mr-3">Edit</button>
                                        <button onclick="toggleServiceStatus(<?php echo $service['id']; ?>)" class="text-<?php echo $service['active'] ? 'red' : 'green'; ?>-600 hover:underline">
                                            <?php echo $service['active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- All Appointments Section -->
            <div id="appointments-section" class="section-content p-8 hidden">
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">All Appointments</h1>
                            <p class="text-gray-600">View and manage all clinic appointments</p>
                        </div>
                        <div class="flex flex-wrap gap-3 items-end">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                <select id="appointmentStatusFilter" onchange="filterAppointments()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">All Status</option>
                                    <option value="SCHEDULED">Scheduled</option>
                                    <option value="CONFIRMED">Confirmed</option>
                                    <option value="IN_PROGRESS">In Progress</option>
                                    <option value="COMPLETED">Completed</option>
                                    <option value="CANCELLED">Cancelled</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                                <input type="date" id="appointmentDateFrom" onchange="filterAppointments()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                                <input type="date" id="appointmentDateTo" onchange="filterAppointments()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                                <input type="text" id="appointmentSearch" placeholder="Patient or doctor..." oninput="filterAppointments()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <button type="button" onclick="clearAppointmentFilters()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Clear</button>
                        </div>
                    </div>
                    <p id="appointmentFilterSummary" class="text-xs text-gray-500 mt-3"></p>
                </div>
                
                <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                    <div class="data-table">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="appointmentsTableBody" class="bg-white divide-y divide-gray-200">
                                <?php foreach ($appointments as $appointment): ?>
                                <tr class="hover:bg-gray-50 appointment-row"
                                    data-status="<?php echo htmlspecialchars($appointment['status']); ?>"
                                    data-date="<?php echo htmlspecialchars($appointment['appointment_date']); ?>"
                                    data-patient="<?php echo htmlspecialchars(strtolower(($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''))); ?>"
                                    data-doctor="<?php echo htmlspecialchars(strtolower(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))); ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800"><?php echo $appointment['type']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getAppointmentStatusBadge($appointment['status']); ?>"><?php echo str_replace('_', ' ', $appointment['status']); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editAppointment(<?php echo $appointment['id']; ?>)" class="text-primary hover:underline mr-3">Edit</button>
                                        <button onclick="updateAppointmentStatus(<?php echo $appointment['id']; ?>)" class="text-green-600 hover:underline">Update Status</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Section -->
            <div id="analytics-section" class="section-content p-8 hidden">
                <div class="mb-8">
                    <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Analytics</h1>
                    <p class="text-gray-600">Clinic performance and statistics</p>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Appointments by Type</h3>
                        <div id="appointmentsChart" style="height: 300px;"></div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Monthly Appointments</h3>
                        <div id="monthlyChart" style="height: 300px;"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">User Distribution</h3>
                        <div id="usersChart" style="height: 250px;"></div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">Service Revenue</h3>
                        <div id="revenueChart" style="height: 250px;"></div>
                    </div>
                    
                    <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800 mb-4">System Metrics</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Appointment Completion Rate</span>
                                <span class="text-lg font-semibold text-green-600">85%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Patient Satisfaction</span>
                                <span class="text-lg font-semibold text-blue-600">92%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Average Wait Time</span>
                                <span class="text-lg font-semibold text-orange-600">12 min</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Settings Section -->
            <div id="settings-section" class="section-content p-8 hidden">
                <div class="mb-8">
                    <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">System Settings</h1>
                    <p class="text-gray-600">Configure clinic settings and preferences</p>
                </div>
                
                <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-inter font-semibold text-gray-800 mb-6">Clinic Information</h3>
                    
                    <form id="clinicSettingsForm" onsubmit="updateClinicSettings(event)" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Clinic Name</label>
                                <input type="text" id="clinic_name" value="<?php echo htmlspecialchars($clinic_settings['clinic_name']['setting_value'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="text" id="clinic_phone" value="<?php echo htmlspecialchars($clinic_settings['clinic_phone']['setting_value'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" id="clinic_email" value="<?php echo htmlspecialchars($clinic_settings['clinic_email']['setting_value'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Reminder (hours)</label>
                                <input type="number" id="appointment_reminder_hours" value="<?php echo htmlspecialchars($clinic_settings['appointment_reminder_hours']['setting_value'] ?? '24'); ?>" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Clinic Address</label>
                            <textarea id="clinic_address" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($clinic_settings['clinic_address']['setting_value'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Audit Logs Section -->
            <div id="logs-section" class="section-content p-8 hidden">
                <div class="mb-8">
                    <h1 class="text-3xl font-inter font-bold text-gray-800 mb-2">Audit Logs</h1>
                    <p class="text-gray-600">System activity and user actions</p>
                </div>
                
                <div class="card-hover bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-inter font-semibold text-gray-800">Activity Logs</h3>
                        <div class="flex space-x-4">
                            <input type="date" id="logDateFilter" onchange="filterLogs()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <select id="logActionFilter" onchange="filterLogs()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Actions</option>
                                <option value="LOGIN">Login</option>
                                <option value="LOGOUT">Logout</option>
                                <option value="CREATE">Create</option>
                                <option value="UPDATE">Update</option>
                                <option value="DELETE">Delete</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="data-table">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($activity_logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y g:i A', strtotime($log['timestamp'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(($log['first_name'] ?? 'Unknown') . ' ' . ($log['last_name'] ?? 'User')); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800"><?php echo $log['action']; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($log['details'] ?? 'No details'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-screen overflow-y-auto">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-inter font-bold text-gray-800 mb-2">Add New User</h2>
                    <p class="text-gray-600">Create a new system user account</p>
                </div>
                
                <form id="addUserForm" onsubmit="handleAddUser(event)" class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" id="newUserFirstName" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" id="newUserLastName" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="newUserEmail" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" id="newUserPhone" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">User Role</label>
                        <select id="newUserRole" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select role</option>
                            <option value="PARENT">Parent/Guardian</option>
                            <option value="DOCTOR">Doctor</option>
                            <option value="ADMIN">Administrator</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Initial Password</label>
                        <input type="password" id="newUserPassword" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="sendWelcomeEmail" class="rounded border-gray-300 text-primary focus:ring-primary">
                        <label for="sendWelcomeEmail" class="ml-2 text-sm text-gray-600">Send welcome email to user</label>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 btn-primary text-white py-3 rounded-lg font-semibold">
                            Create User
                        </button>
                        <button type="button" onclick="closeAddUserModal()" 
                                class="flex-1 border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div id="addScheduleModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 max-h-screen overflow-y-auto">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-inter font-bold text-gray-800 mb-2">Add Schedule</h2>
                    <p class="text-gray-600">Add new doctor schedule</p>
                </div>
                
                <form id="addScheduleForm" onsubmit="handleAddSchedule(event)" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Day of Week</label>
                        <select id="scheduleDay" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select day</option>
                            <option value="MONDAY">Monday</option>
                            <option value="TUESDAY">Tuesday</option>
                            <option value="WEDNESDAY">Wednesday</option>
                            <option value="THURSDAY">Thursday</option>
                            <option value="FRIDAY">Friday</option>
                            <option value="SATURDAY">Saturday</option>
                            <option value="SUNDAY">Sunday</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                            <input type="time" id="scheduleStartTime" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Time</label>
                            <input type="time" id="scheduleEndTime" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Slot Duration (mins)</label>
                            <input type="number" id="scheduleDuration" value="30" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Patients</label>
                            <input type="number" id="scheduleMaxPatients" value="10" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 btn-primary text-white py-3 rounded-lg font-semibold">
                            Add Schedule
                        </button>
                        <button type="button" onclick="closeAddScheduleModal()" 
                                class="flex-1 border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div id="addServiceModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 max-h-screen overflow-y-auto">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-inter font-bold text-gray-800 mb-2">Add Service</h2>
                    <p class="text-gray-600">Add new clinic service</p>
                </div>
                
                <form id="addServiceForm" onsubmit="handleAddService(event)" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Service Name <span class="text-red-500">*</span></label>
                        <input type="text" id="serviceName" required minlength="2" maxlength="120"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                        <textarea id="serviceDescription" rows="3" required minlength="5" maxlength="500"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration (mins) <span class="text-red-500">*</span></label>
                            <input type="number" id="serviceDuration" value="30" min="1" max="600" step="1" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cost (&#8369;) <span class="text-red-500">*</span></label>
                            <input type="number" id="serviceCost" min="0" step="0.01" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 btn-primary text-white py-3 rounded-lg font-semibold">
                            Add Service
                        </button>
                        <button type="button" onclick="closeAddServiceModal()" 
                                class="flex-1 border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeAdminDashboard();
            initializeCharts();
        });
        
        function initializeAdminDashboard() {
            // Dashboard is already loaded with PHP data
        }
        
        function showSection(sectionName) {
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.remove('hidden');
            }
            
            document.querySelectorAll('nav a').forEach(link => {
                link.classList.remove('bg-white/20');
            });
            
            const activeLink = event.target.closest('a');
            if (activeLink) {
                activeLink.classList.add('bg-white/20');
            }
        }
        
        function initializeCharts() {
            // Appointments by Type Chart
            const appointmentsChart = echarts.init(document.getElementById('appointmentsChart'));
            appointmentsChart.setOption({
                tooltip: { trigger: 'item' },
                legend: { orient: 'vertical', right: 10, top: 'center' },
                series: [{
                    name: 'Appointments',
                    type: 'pie',
                    radius: ['40%', '70%'],
                    data: [
                        { value: 45, name: 'CONSULTATION' },
                        { value: 30, name: 'VACCINATION' },
                        { value: 15, name: 'CHECKUP' },
                        { value: 10, name: 'FOLLOW_UP' }
                    ],
                    emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' } }
                }]
            });
            
            // Monthly Appointments Chart
            const monthlyChart = echarts.init(document.getElementById('monthlyChart'));
            monthlyChart.setOption({
                tooltip: { trigger: 'axis' },
                xAxis: {
                    type: 'category',
                    data: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                },
                yAxis: { type: 'value' },
                series: [{
                    data: [120, 200, 150, 80, 70, 110, 130, 180, 160, 140, 190, 220],
                    type: 'bar',
                    itemStyle: { color: '#FF6B9A' }
                }]
            });
            
            // Users Distribution Chart
            const usersChart = echarts.init(document.getElementById('usersChart'));
            usersChart.setOption({
                tooltip: { trigger: 'item' },
                series: [{
                    name: 'Users',
                    type: 'pie',
                    radius: '70%',
                    data: [
                        { value: 65, name: 'Parents' },
                        { value: 15, name: 'Doctors' },
                        { value: 20, name: 'Admins' }
                    ],
                    emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' } }
                }]
            });
            
            // Revenue Chart
            const revenueChart = echarts.init(document.getElementById('revenueChart'));
            revenueChart.setOption({
                tooltip: { trigger: 'axis' },
                radar: {
                    indicator: [
                        { name: 'Consultation', max: 6500 },
                        { name: 'Vaccination', max: 16000 },
                        { name: 'Checkup', max: 30000 },
                        { name: 'Follow-up', max: 38000 },
                        { name: 'Other', max: 52000 }
                    ]
                },
                series: [{
                    type: 'radar',
                    data: [{ value: [4200, 3000, 20000, 35000, 50000], name: 'Revenue' }]
                }]
            });
            
            // Resize charts on window resize
            window.addEventListener('resize', function() {
                appointmentsChart.resize();
                monthlyChart.resize();
                usersChart.resize();
                revenueChart.resize();
            });
        }
        
        // Notification system
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.remove('hidden');
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.add('hidden');
            document.getElementById('addUserForm').reset();
        }
        
        function openAddScheduleModal() {
            document.getElementById('addScheduleModal').classList.remove('hidden');
        }
        
        function closeAddScheduleModal() {
            document.getElementById('addScheduleModal').classList.add('hidden');
            document.getElementById('addScheduleForm').reset();
        }
        
        function openAddServiceModal() {
            document.getElementById('addServiceModal').classList.remove('hidden');
        }
        
        function closeAddServiceModal() {
            document.getElementById('addServiceModal').classList.add('hidden');
            document.getElementById('addServiceForm').reset();
        }
        
        // Form handlers
        function handleAddUser(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('first_name', document.getElementById('newUserFirstName').value);
            formData.append('last_name', document.getElementById('newUserLastName').value);
            formData.append('email', document.getElementById('newUserEmail').value);
            formData.append('phone', document.getElementById('newUserPhone').value);
            formData.append('user_type', document.getElementById('newUserRole').value);
            formData.append('password', document.getElementById('newUserPassword').value);
            formData.append('action', 'add_user');
            
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            fetch('admin-actions-secure.php', {
                method: 'POST',
                body: formData
            })

            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('User created successfully!');
                    closeAddUserModal();
                    // Reload the page to see the new user
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error creating user', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error creating user', 'error');
            });
        }
        
        function handleAddSchedule(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('doctor_id', 6); // Default doctor ID
            formData.append('day_of_week', document.getElementById('scheduleDay').value);
            formData.append('start_time', document.getElementById('scheduleStartTime').value);
            formData.append('end_time', document.getElementById('scheduleEndTime').value);
            formData.append('slot_duration', document.getElementById('scheduleDuration').value);
            formData.append('max_patients', document.getElementById('scheduleMaxPatients').value);
            formData.append('action', 'add_schedule');
            
            fetch('admin-actions-simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Schedule added successfully!');
                    closeAddScheduleModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error adding schedule', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding schedule', 'error');
            });
        }
        
        function handleAddService(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('name', document.getElementById('serviceName').value);
            formData.append('description', document.getElementById('serviceDescription').value);
            formData.append('duration', document.getElementById('serviceDuration').value);
            formData.append('cost', document.getElementById('serviceCost').value);
            formData.append('action', 'add_service');
            
            fetch('admin-actions-simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Service added successfully!');
                    closeAddServiceModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error adding service', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding service', 'error');
            });
        }
        
        function updateClinicSettings(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('clinic_name', document.getElementById('clinic_name').value);
            formData.append('clinic_phone', document.getElementById('clinic_phone').value);
            formData.append('clinic_email', document.getElementById('clinic_email').value);
            formData.append('clinic_address', document.getElementById('clinic_address').value);
            formData.append('appointment_reminder_hours', document.getElementById('appointment_reminder_hours').value);
            formData.append('action', 'update_clinic_settings');
            
            fetch('admin-actions-simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Settings updated successfully!');
                setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error updating settings', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating settings', 'error');
            });
        }
        
        function toggleUserStatus(userId) {
            if (!confirm('Are you sure you want to change this user\'s status?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('action', 'toggle_user_status');
            
            fetch('admin-actions-simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('User status updated successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error updating user status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating user status', 'error');
            });
        }
        
        function editUser(userId) {
            showNotification('Edit user feature coming soon!', 'info');
        }
        
        function filterUsers() {
            showNotification('Filter functionality coming soon!', 'info');
        }
        
        function editSchedule(scheduleId) {
            showNotification('Edit schedule feature coming soon!', 'info');
        }
        
        function deleteSchedule(scheduleId) {
            if (!confirm('Are you sure you want to delete this schedule?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('schedule_id', scheduleId);
            formData.append('action', 'delete_schedule');
            
            fetch('admin-actions-simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Schedule deleted successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error deleting schedule', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error deleting schedule', 'error');
            });
        }
        
        function editService(serviceId) {
            showNotification('Edit service feature coming soon!', 'info');
        }
        
        function toggleServiceStatus(serviceId) {
            if (!confirm('Are you sure you want to change this service\'s status?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('service_id', serviceId);
            formData.append('action', 'toggle_service_status');
            
            fetch('admin-actions-simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Service status updated successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error updating service status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating service status', 'error');
            });
        }
        
        function editAppointment(appointmentId) {
            showNotification('Edit appointment feature coming soon!', 'info');
        }
        
        function updateAppointmentStatus(appointmentId) {
            showNotification('Update appointment status feature coming soon!', 'info');
        }
        
        function filterAppointments() {
            var status   = (document.getElementById('appointmentStatusFilter')?.value || '').trim();
            var dateFrom = (document.getElementById('appointmentDateFrom')?.value || '').trim();
            var dateTo   = (document.getElementById('appointmentDateTo')?.value || '').trim();
            var search   = (document.getElementById('appointmentSearch')?.value || '').trim().toLowerCase();

            if (dateFrom && dateTo && dateFrom > dateTo) {
                (window.showToast || alert)('"From" date must be on or before "To" date.', 'warning');
                return;
            }

            var rows = document.querySelectorAll('#appointmentsTableBody tr.appointment-row');
            var shown = 0, total = rows.length;
            rows.forEach(function (row) {
                var rowStatus  = row.getAttribute('data-status')  || '';
                var rowDate    = row.getAttribute('data-date')    || '';
                var rowPatient = row.getAttribute('data-patient') || '';
                var rowDoctor  = row.getAttribute('data-doctor')  || '';

                var matches = true;
                if (status && rowStatus !== status) matches = false;
                if (matches && dateFrom && rowDate < dateFrom) matches = false;
                if (matches && dateTo   && rowDate > dateTo)   matches = false;
                if (matches && search && !(rowPatient.indexOf(search) !== -1 || rowDoctor.indexOf(search) !== -1)) matches = false;

                row.style.display = matches ? '' : 'none';
                if (matches) shown++;
            });

            var summary = document.getElementById('appointmentFilterSummary');
            if (summary) {
                summary.textContent = 'Showing ' + shown + ' of ' + total + ' appointment' + (total === 1 ? '' : 's') + '.';
            }
        }

        function clearAppointmentFilters() {
            var ids = ['appointmentStatusFilter','appointmentDateFrom','appointmentDateTo','appointmentSearch'];
            ids.forEach(function (id) { var el = document.getElementById(id); if (el) el.value = ''; });
            filterAppointments();
        }

        function filterUsersTable() {
            var search = (document.getElementById('userSearchInput')?.value || '').trim().toLowerCase();
            var role   = (document.getElementById('userRoleFilter')?.value || '').trim();
            var status = (document.getElementById('userStatusFilter')?.value || '').trim();
            document.querySelectorAll('#usersTableBody tr.user-row').forEach(function (row) {
                var t = (row.getAttribute('data-name') || '') + ' ' + (row.getAttribute('data-email') || '');
                var r = row.getAttribute('data-role') || '';
                var s = row.getAttribute('data-status') || '';
                var ok = true;
                if (search && t.indexOf(search) === -1) ok = false;
                if (ok && role   && r !== role)   ok = false;
                if (ok && status && s !== status) ok = false;
                row.style.display = ok ? '' : 'none';
            });
        }
        
        function filterLogs() {
            showNotification('Filter functionality coming soon!', 'info');
        }
    </script>
</body>
</html>

<?php
// Helper functions
function getRoleBadge($role) {
    switch ($role) {
        case 'PARENT': return 'role-parent';
        case 'DOCTOR': return 'role-doctor';
        case 'ADMIN': return 'role-admin';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getAppointmentStatusBadge($status) {
    switch ($status) {
        case 'SCHEDULED': return 'status-scheduled';
        case 'CONFIRMED': return 'bg-blue-100 text-blue-800';
        case 'IN_PROGRESS': return 'bg-yellow-100 text-yellow-800';
        case 'COMPLETED': return 'status-completed';
        case 'CANCELLED': return 'status-cancelled';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>