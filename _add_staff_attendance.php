<?php
require_once __DIR__ . '/includes/config.php';
$pdo = getDB();

$sql = "CREATE TABLE IF NOT EXISTS staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    week_no INT NOT NULL,
    week_start_date DATE NOT NULL,
    staff_id VARCHAR(50) NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    attendance_date DATE NOT NULL,
    day_name ENUM('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
    present TINYINT(1) DEFAULT 0,
    arrival_time TIME NULL,
    departure_time TIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily (term_name, academic_year, week_no, staff_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    echo "Table 'staff_attendance' created successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

