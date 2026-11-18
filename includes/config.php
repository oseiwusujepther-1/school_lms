<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_lms');

// App settings
define('APP_NAME', 'School LMS');

function detectAppBasePath() {
    $documentRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = str_replace('\\', '/', dirname(__DIR__));

    if ($documentRoot !== '' && strpos($appRoot, $documentRoot) === 0) {
        $relativePath = substr($appRoot, strlen($documentRoot));
        $relativePath = trim($relativePath, '/');
        return $relativePath !== '' ? '/' . $relativePath : '';
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== '') {
        $path = trim(dirname($scriptName), '/');
        return $path !== '' ? '/' . $path : '';
    }

    return '';
}

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = detectAppBasePath();
define('APP_URL', $scheme . '://' . $host . $basePath);

define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('PHOTO_PATH', UPLOAD_PATH . 'photos/');
define('LOGO_PATH', UPLOAD_PATH . 'logos/');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection with prepared statements support
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Helper functions
function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/index.php');
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        redirect(APP_URL . '/index.php?error=unauthorized');
    }
}

function calculateAge($dob) {
    if (empty($dob)) return null;
    $birth = new DateTime($dob);
    $today = new DateTime();
    return $today->diff($birth)->y;
}

function getGradeRemark($total) {
    $score = (int)$total;
    if ($score < 0) $score = 0;
    if ($score > 100) $score = 100;
    if ($score >= 90) return ['grade' => 'A1', 'remark' => 'EXCELLENT', 'aggregate' => 1];
    if ($score >= 80) return ['grade' => 'B2', 'remark' => 'VERY GOOD', 'aggregate' => 2];
    if ($score >= 75) return ['grade' => 'B3', 'remark' => 'GOOD', 'aggregate' => 3];
    if ($score >= 70) return ['grade' => 'C4', 'remark' => 'CREDIT', 'aggregate' => 4];
    if ($score >= 65) return ['grade' => 'C5', 'remark' => 'CREDIT', 'aggregate' => 5];
    if ($score >= 60) return ['grade' => 'C6', 'remark' => 'CREDIT', 'aggregate' => 6];
    if ($score >= 55) return ['grade' => 'D7', 'remark' => 'PASS', 'aggregate' => 7];
    if ($score >= 50) return ['grade' => 'E8', 'remark' => 'PASS', 'aggregate' => 8];
    return ['grade' => 'F9', 'remark' => 'FAIL', 'aggregate' => 9];
}

function flash($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function getSecurityQuestions() {
    return [
        'What is your mother\'s maiden name?',
        'What was the name of your first pet?',
        'What is the name of your first school?',
        'In which city were you born?',
        'What is your favourite meal?',
        'What is the name of your best friend?',
    ];
}

function uploadFile($file, $subdir = 'photos') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // 5MB max

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . strtolower($ext);
    $destDir = UPLOAD_PATH . $subdir . '/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $dest = $destDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $subdir . '/' . $filename;
    }
    return null;
}
?>