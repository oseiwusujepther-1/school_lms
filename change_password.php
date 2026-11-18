<?php
require_once __DIR__ . '/includes/config.php';
requireRole(['admin', 'headteacher']);
$pageTitle = 'Change Password';
$pdo = getDB();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Fetch current user's hashed password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $errors[] = 'User account not found.';
    } elseif (!password_verify($currentPassword, $user['password'])) {
        $errors[] = 'Your current password is incorrect.';
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
        $update->execute([$hashed, $newPassword, $_SESSION['user_id']]);
        flash('Your password has been changed successfully.');
        redirect(APP_URL . '/change_password.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Change Password</h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-message flash-error">
        <span>
            <?php foreach ($errors as $err): ?>
                <div><?= sanitize($err) ?></div>
            <?php endforeach; ?>
        </span>
        <button onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <p class="hint" style="margin-bottom:1.2rem;">
        Update your account password. You will need to use the new password the next time you log in.
    </p>
    <form method="POST" action="">
        <div class="form-group">
            <label class="required">Current Password</label>
            <input type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label class="required">New Password</label>
            <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
            <div class="hint">Must be at least 6 characters long.</div>
        </div>
        <div class="form-group">
            <label class="required">Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Change Password</button>
            <a href="<?= APP_URL ?>/<?= ($_SESSION['role'] ?? '') === 'admin' ? 'admin' : 'headteacher' ?>/dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

