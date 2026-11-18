<?php
if (!defined('APP_NAME')) require_once __DIR__ . '/config.php';
$flash = getFlash();
$currentUser = $_SESSION['full_name'] ?? 'Guest';
$currentRole = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '' ?>School LMS</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar">
        <div class="nav-brand">
            <img src="<?= APP_URL ?>/assets/logos/logo.jpg" alt="School Logo" class="logo-icon" style="width:36px;height:36px;object-fit:contain;border-radius:50%;">
            <span>School LMS</span>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">☰</button>
        <ul class="nav-menu" id="navMenu">
            <?php if ($currentRole === 'admin'): ?>
                <li><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
                <li><a href="<?= APP_URL ?>/admin/teachers.php">Teachers</a></li>
                <li><a href="<?= APP_URL ?>/admin/classes.php">Classes</a></li>
                <li><a href="<?= APP_URL ?>/admin/learners.php">Learners</a></li>
                <li><a href="<?= APP_URL ?>/admin/report.php">Reports</a></li>
                <li><a href="<?= APP_URL ?>/admin/settings.php">Settings</a></li>
            <?php elseif ($currentRole === 'headteacher'): ?>
                <li><a href="<?= APP_URL ?>/headteacher/dashboard.php">Dashboard</a></li>
                <li><a href="<?= APP_URL ?>/headteacher/enroll.php">Enroll Pupil</a></li>
                <li><a href="<?= APP_URL ?>/headteacher/learners.php">Learners</a></li>
                <li><a href="<?= APP_URL ?>/headteacher/reports.php">Reports</a></li>
            <?php elseif ($currentRole === 'teacher'): ?>
                <li><a href="<?= APP_URL ?>/teacher/dashboard.php">Dashboard</a></li>
                <li><a href="<?= APP_URL ?>/teacher/my_class.php">My Class</a></li>
                <li><a href="<?= APP_URL ?>/teacher/report_card.php">Report Cards</a></li>
<li><a href="<?= APP_URL ?>/teacher/submit_report.php">Submit Reports</a></li>
            <?php endif; ?>
            <li class="nav-user">
                <span><?= sanitize($currentUser) ?> (<?= sanitize(ucfirst($currentRole)) ?>)</span>
                <?php if (in_array($currentRole, ['admin', 'headteacher'])): ?>
                <a href="<?= APP_URL ?>/change_password.php" class="btn-change-password">Change Password</a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/security_question.php" class="btn-change-password">Security</a>
                <a href="<?= APP_URL ?>/logout.php" class="btn-logout">Logout</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <?php if ($flash): ?>
    <div class="flash-message flash-<?= $flash['type'] ?>" id="flashMsg">
        <?= sanitize($flash['message']) ?>
        <button onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>

    <main class="main-content">