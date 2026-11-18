<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') redirect(APP_URL . '/admin/dashboard.php');
    if ($role === 'headteacher') redirect(APP_URL . '/headteacher/dashboard.php');
    redirect(APP_URL . '/teacher/dashboard.php');
}

$pdo = getDB();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = false;
$resetUser = null;

// Step 1: enter username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_username'])) {
    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
        $errors[] = 'Please enter your username.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, full_name, security_question, security_answer FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) {
            $errors[] = 'No account found with that username.';
        } elseif (empty($user['security_question']) || empty($user['security_answer'])) {
            $errors[] = 'This account has no security question set. Please contact the administrator to reset your password.';
        } else {
            // Store user id in session temporarily for this reset flow
            $_SESSION['reset_user_id'] = $user['id'];
            redirect(APP_URL . '/forgot_password.php?step=2');
        }
    }
}

// Step 2: answer security question + set new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reset'])) {
    if (empty($_SESSION['reset_user_id'])) {
        redirect(APP_URL . '/forgot_password.php');
    }
    $stmt = $pdo->prepare("SELECT id, username, full_name, security_question, security_answer FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $errors[] = 'Account not found. Please start again.';
        unset($_SESSION['reset_user_id']);
    } else {
        $answer = trim($_POST['security_answer'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify(strtolower($answer), $user['security_answer'])) {
            $errors[] = 'Your answer is incorrect.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (empty($errors)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ?, password_plain = ? WHERE id = ?");
            $update->execute([$hashed, $newPassword, $user['id']]);
            unset($_SESSION['reset_user_id']);
            $success = true;
            $resetUser = $user;
        }
    }
}

// Load user for step 2 display
if ($step === 2 && !empty($_SESSION['reset_user_id'])) {
    $stmt = $pdo->prepare("SELECT id, username, full_name, security_question FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $resetUser = $stmt->fetch();
    if (!$resetUser) {
        unset($_SESSION['reset_user_id']);
        redirect(APP_URL . '/forgot_password.php');
    }
}

$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | School LMS</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="school-logo">
            <div style="font-size:3rem;">🔑</div>
            <h2>Essienimpong M/A Primary &amp; KG School</h2>
            <p style="color:#666;font-size:0.85rem;">Ghana Education Service</p>
        </div>

        <?php if ($success): ?>
            <h1 style="text-align:center;font-size:1.2rem;color:#155724;">Password Reset Successful</h1>
            <p style="text-align:center;margin:1rem 0;color:#333;">
                Your password has been reset successfully.
                <?php if ($resetUser): ?><strong><?= sanitize($resetUser['full_name']) ?></strong> (<?= sanitize($resetUser['username']) ?>)<?php endif; ?>
                can now log in with the new password.
            </p>
            <a href="<?= APP_URL ?>/index.php" class="btn btn-primary" style="display:block;text-align:center;">Back to Login</a>
        <?php else: ?>
            <h1><?= $step === 2 ? 'Reset Your Password' : 'Forgot Password' ?></h1>

            <?php if (!empty($errors)): ?>
                <div class="flash-message flash-error" style="margin-bottom:1rem;">
                    <span>
                        <?php foreach ($errors as $err): ?>
                            <div><?= sanitize($err) ?></div>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($step === 2 && $resetUser): ?>
                <div class="flash-message flash-info" style="margin-bottom:1rem;">
                    <span>Account: <strong><?= sanitize($resetUser['full_name']) ?></strong> (<?= sanitize($resetUser['username']) ?>)</span>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="required">Security Question</label>
                        <input type="text" value="<?= sanitize($resetUser['security_question']) ?>" disabled style="background:#f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label class="required">Your Answer</label>
                        <input type="text" name="security_answer" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="required">New Password</label>
                        <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="required">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </div>
                    <button type="submit" name="submit_reset" class="btn btn-primary">Reset Password</button>
                </form>
                <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
                    <a href="<?= APP_URL ?>/forgot_password.php?step=1">Start over</a>
                </p>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="required">Username</label>
                        <input type="text" name="username" required autofocus value="<?= sanitize($_POST['username'] ?? '') ?>">
                    </div>
                    <button type="submit" name="submit_username" class="btn btn-primary">Continue</button>
                </form>
                <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
                    Remembered your password? <a href="<?= APP_URL ?>/index.php">Back to Login</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

