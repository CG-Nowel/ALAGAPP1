<?php
// complete_setup.php
$host = 'localhost';
$username = 'root';
$password = '';

// Connect without selecting database first
$conn = mysqli_connect($host, $username, $password);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS pediatric_clinic";
if (mysqli_query($conn, $sql)) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . mysqli_error($conn) . "<br>";
}

// Select the database
mysqli_select_db($conn, "pediatric_clinic");

// Complete SQL to create all tables
$sql = "

-- Users table (for parents, doctors, admins)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    user_type ENUM('PARENT', 'DOCTOR', 'DOCTOR_OWNER', 'ADMIN') NOT NULL DEFAULT 'PARENT',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    date_of_birth DATE,
    gender ENUM('MALE', 'FEMALE', 'OTHER'),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    profile_picture VARCHAR(255),
    specialization VARCHAR(100),
    license_number VARCHAR(50),
    years_of_experience INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Patients table (children)
CREATE TABLE IF NOT EXISTS patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('MALE', 'FEMALE', 'OTHER') NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    allergies TEXT,
    medical_conditions TEXT,
    special_notes TEXT,
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    type ENUM('CONSULTATION', 'VACCINATION', 'CHECKUP', 'FOLLOW_UP', 'OTHER') NOT NULL,
    status ENUM('SCHEDULED', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW') DEFAULT 'SCHEDULED',
    reason TEXT,
    notes TEXT,
    duration INT DEFAULT 30,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Vaccination records table
CREATE TABLE IF NOT EXISTS vaccination_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    vaccine_name VARCHAR(100) NOT NULL,
    vaccine_type ENUM('ROUTINE', 'OPTIONAL', 'SPECIAL') DEFAULT 'ROUTINE',
    dose_number INT,
    total_doses INT,
    administration_date DATE NOT NULL,
    next_due_date DATE,
    administered_by INT,
    lot_number VARCHAR(50),
    manufacturer VARCHAR(100),
    site ENUM('LEFT_ARM', 'RIGHT_ARM', 'LEFT_THIGH', 'RIGHT_THIGH', 'ORAL') DEFAULT 'LEFT_ARM',
    notes TEXT,
    status ENUM('COMPLETED', 'SCHEDULED', 'MISSED', 'OVERDUE') DEFAULT 'COMPLETED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (administered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Medical records table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    record_date DATE NOT NULL,
    record_type ENUM('CONSULTATION', 'CHECKUP', 'FOLLOW_UP', 'EMERGENCY', 'OTHER') NOT NULL,
    diagnosis TEXT,
    symptoms TEXT,
    temperature DECIMAL(4,2),
    blood_pressure VARCHAR(20),
    heart_rate INT,
    respiratory_rate INT,
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    treatment_plan TEXT,
    prescriptions TEXT,
    notes TEXT,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Services table
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration INT DEFAULT 30,
    cost DECIMAL(10,2),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor schedules table
CREATE TABLE IF NOT EXISTS doctor_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    day_of_week ENUM('MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30,
    max_patients INT DEFAULT 10,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('APPOINTMENT', 'VACCINATION', 'SYSTEM', 'REMINDER') DEFAULT 'SYSTEM',
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user (password: 'password')
INSERT IGNORE INTO users (first_name, last_name, email, password, user_type, status) 
VALUES ('Admin', 'User', 'admin@pedicare.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN', 'active');

-- Insert sample doctors
INSERT IGNORE INTO users (first_name, last_name, email, password, user_type, status, specialization, years_of_experience) VALUES
('Sarah', 'Johnson', 'dr.sarah@pedicare.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'active', 'Pediatrician', 15),
('Michael', 'Chen', 'dr.chen@pedicare.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'active', 'Pediatric Specialist', 10),
('Emily', 'Rodriguez', 'dr.emily@pedicare.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'active', 'Child Psychologist', 8);

-- Insert default services
INSERT IGNORE INTO services (name, description, duration, cost) VALUES
('Consultation', 'Comprehensive health assessment and medical consultation', 30, 50.00),
('Vaccination', 'Immunization services with proper documentation', 15, 25.00),
('Well Baby Checkup', 'Regular developmental assessment and health monitoring', 30, 40.00),
('Pediatric Clearance', 'Medical clearance certificate for school and activities', 20, 30.00),
('Referral Services', 'Coordinated care with specialists', 15, 20.00),
('Ear Piercing', 'Safe and professional ear piercing service', 30, 35.00);

";

// Execute multi query
if (mysqli_multi_query($conn, $sql)) {
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_next_result($conn));
    echo "All tables created successfully!<br>";
} else {
    echo "Error creating tables: " . mysqli_error($conn) . "<br>";
}

echo "Database setup complete! You can now use the application.";
mysqli_close($conn);
?>