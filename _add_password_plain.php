<?php
require_once __DIR__ . '/includes/config.php';
$pdo = getDB();

// Add password_plain column to store a readable copy of the password for admin display
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN password_plain VARCHAR(255) DEFAULT NULL AFTER password");
    echo "Column 'password_plain' added successfully!<br>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column 'password_plain' already exists.<br>";
    } else {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

// Seed plaintext passwords for the default admin and headteacher accounts (only if not set)
$stmt1 = $pdo->prepare("UPDATE users SET password_plain = 'admin' WHERE username = 'admin' AND role = 'admin' AND (password_plain IS NULL OR password_plain = '')");
$stmt1->execute();
echo "Admin default password seeded.<br>";

$stmt2 = $pdo->prepare("UPDATE users SET password_plain = 'admin' WHERE username = 'headteacher' AND role = 'headteacher' AND (password_plain IS NULL OR password_plain = '')");
$stmt2->execute();
echo "Headteacher default password seeded.<br>";

echo "Done. You can now delete this file.";

