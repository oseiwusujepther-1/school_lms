<?php
require_once __DIR__ . '/includes/config.php';
$pdo = getDB();
try {
    $pdo->exec("ALTER TABLE report_submissions ADD COLUMN promotion_class_id INT DEFAULT NULL AFTER promotion_status");
    echo "Column 'promotion_class_id' added successfully!";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column 'promotion_class_id' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}

