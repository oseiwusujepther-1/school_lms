<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$pageTitle = 'Reset Password';
$pdo = getDB();

$error = '';
$success = false;

// Determine target user from ?user_id= (user id) or ?teacher_id= (teacher id)
$userId = (int)($_GET['user_id'] ?? 0);
$teacherId = (int)($_GET['teacher_id'] ?? 0);

$target = null;
if ($teacherId) {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.role FROM users u JOIN teachers t ON u.id = t.user_id WHERE t.id = ?");
    $stmt->execute([$teacherId]);
    $target = $stmt->fetch();
} elseif ($userId) {
    $stmt = $pdo->prepare("SELECT id, username, full_name, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();
}

if (!$target) {
    flash('User not found.', 'error');
    redirect(APP_URL . '/admin/teachers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ?, password_plain = ? WHERE id = ?");
        $update->execute([$hashed, $newPassword, $target['id']]);
        flash('Password reset successfully for ' . $target['full_name'] . ' (' . $target['username'] . ').');
        redirect(APP_URL . '/admin/teachers.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Reset Password</h1>
    <a href="<?= APP_URL ?>/admin/teachers.php" class="btn btn-dark">Back to Teachers</a>
</div>

<div class="card" style="max-width:600px;">
    <div class="flash-message flash-info" style="margin-bottom:1rem;">
        <span>
            Resetting password for <strong><?= sanitize($target['full_name']) ?></strong>
            (<?= sanitize($target['username']) ?> · <?= sanitize(ucfirst($target['role'])) ?>)
        </span>
    </div>

    <?php if ($error): ?>
        <div class="flash-message flash-error"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="required">New Password</label>
            <input type="password" name="new_password" required minlength="6" autocomplete="off">
            <div class="hint">Must be at least 6 characters long.</div>
        </div>
        <div class="form-group">
            <label class="required">Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6" autocomplete="off">
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Reset Password</button>
            <a href="<?= APP_URL ?>/admin/teachers.php" class="btn btn-dark">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


