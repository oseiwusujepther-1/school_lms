<?php
require_once __DIR__ . '/includes/config.php';
$pdo = getDB();

$columns = [
    'security_question' => 'VARCHAR(255) DEFAULT NULL',
    'security_answer'   => 'VARCHAR(255) DEFAULT NULL',
];

foreach ($columns as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $def");
        echo "Column '$col' added successfully!<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Column '$col' already exists.<br>";
        } else {
            echo "Error adding '$col': " . $e->getMessage() . "<br>";
        }
    }
}

