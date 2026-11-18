<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') redirect(APP_URL . '/admin/dashboard.php');
    if ($role === 'headteacher') redirect(APP_URL . '/headteacher/dashboard.php');
    if ($role === 'teacher') redirect(APP_URL . '/teacher/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, username, password, role, full_name, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Load teacher_id if teacher
            if ($user['role'] === 'teacher') {
                $tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
                $tStmt->execute([$user['id']]);
                $t = $tStmt->fetch();
                $_SESSION['teacher_id'] = $t ? $t['id'] : null;
            }

            if ($user['role'] === 'admin') redirect(APP_URL . '/admin/dashboard.php');
            if ($user['role'] === 'headteacher') redirect(APP_URL . '/headteacher/dashboard.php');
            redirect(APP_URL . '/teacher/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | School LMS</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="school-logo">
            <img src="<?= APP_URL ?>/assets/logos/logo.jpg" alt="School Logo" style="width:110px;height:110px;object-fit:contain;border-radius:50%;border:4px solid var(--kente-gold);box-shadow:0 4px 12px rgba(0,0,0,0.15);">
            <h2>Essienimpong M/A Primary &amp; KG School</h2>
            <p style="color:#666;font-size:0.85rem;">Ghana Education Service</p>
        </div>
        <h1>School LMS Login</h1>

        <?php if ($error): ?>
            <div class="flash-message flash-error" style="margin-bottom:1rem;"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
            <div class="flash-message flash-error" style="margin-bottom:1rem;">You are not authorized to access that page.</div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="flash-message flash-success" style="margin-bottom:1rem;">Registration successful! Wait for admin approval, then login.</div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="required">Username</label>
                <input type="text" name="username" required autofocus value="<?= sanitize($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="required">Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <div class="login-links">
            <p>Teachers: <a href="<?= APP_URL ?>/teacher_register.php">Register here</a></p>
            <p style="margin-top:8px;"><a href="<?= APP_URL ?>/forgot_password.php">Forgot Password?</a></p>
        </div>
</div>
</body>
</html>
