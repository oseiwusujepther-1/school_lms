<?php
require_once __DIR__ . '/includes/config.php';
$pdo = getDB();

// Create table if not exists (with matron_name, no cost)
$sql = "CREATE TABLE IF NOT EXISTS matron_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_date DATE NOT NULL,
    day_name VARCHAR(20) NOT NULL,
    matron_name VARCHAR(150) NOT NULL,
    meal_type VARCHAR(50) NOT NULL DEFAULT 'Lunch',
    food_type VARCHAR(255) NOT NULL,
    portions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    echo "Table 'matron_records' ensured successfully!" . PHP_EOL;
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . PHP_EOL;
}

// Add matron_name column if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM matron_records LIKE 'matron_name'")->fetchColumn();
    if (!$cols) {
        $pdo->exec("ALTER TABLE matron_records ADD COLUMN matron_name VARCHAR(150) NOT NULL DEFAULT '' AFTER day_name");
        echo "Added matron_name column." . PHP_EOL;
    } else {
        echo "matron_name column already exists." . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error adding matron_name: " . $e->getMessage() . PHP_EOL;
}

// Remove cost column if exists
try {
    $cols = $pdo->query("SHOW COLUMNS FROM matron_records LIKE 'cost'")->fetchColumn();
    if ($cols) {
        $pdo->exec("ALTER TABLE matron_records DROP COLUMN cost");
        echo "Removed cost column." . PHP_EOL;
    } else {
        echo "cost column already removed." . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error removing cost: " . $e->getMessage() . PHP_EOL;
}
