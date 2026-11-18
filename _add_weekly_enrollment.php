<?php
require_once __DIR__ . '/includes/config.php';
$pdo = getDB();

$sql = "CREATE TABLE IF NOT EXISTS weekly_enrollment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    week_date DATE NOT NULL,
    week_no INT NOT NULL,
    class_id INT NOT NULL,
    active_boys INT DEFAULT 0,
    active_girls INT DEFAULT 0,
    dropout_boys INT DEFAULT 0,
    dropout_girls INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    echo "Table 'weekly_enrollment' created successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

