<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$pageTitle = 'Teachers Management';
$pdo = getDB();

// Approve / Deactivate actions
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $pdo->prepare("UPDATE teachers SET is_approved = 1 WHERE id = ?");
    $stmt->execute([$id]);
    // Activate user
    $stmt2 = $pdo->prepare("UPDATE users u JOIN teachers t ON u.id = t.user_id SET u.is_active = 1 WHERE t.id = ?");
    $stmt2->execute([$id]);
    flash('Teacher approved successfully.');
    redirect(APP_URL . '/admin/teachers.php');
}
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    $stmt = $pdo->prepare("UPDATE teachers SET is_approved = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $stmt2 = $pdo->prepare("UPDATE users u JOIN teachers t ON u.id = t.user_id SET u.is_active = 0 WHERE t.id = ?");
    $stmt2->execute([$id]);
    flash('Teacher deactivated.', 'info');
    redirect(APP_URL . '/admin/teachers.php');
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if ($t) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$t['user_id']]);
        // teachers cascade deleted
    }
    flash('Teacher deleted.', 'info');
    redirect(APP_URL . '/admin/teachers.php');
}

$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewTeacher = null;
if ($viewId) {
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.is_active FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$viewId]);
    $viewTeacher = $stmt->fetch();
}

$teachers = $pdo->query("SELECT t.*, u.username, u.is_active FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Teachers Management</h1>
    <a href="<?= APP_URL ?>/teacher_register.php" class="btn btn-primary" target="_blank">Registration Link</a>
</div>

<?php if ($viewTeacher): ?>
<div class="card">
    <h2>Teacher Details: <?= sanitize($viewTeacher['full_name']) ?></h2>
    <div class="form-grid">
        <div>
            <?php if ($viewTeacher['passport_photo']): ?>
                <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($viewTeacher['passport_photo']) ?>" alt="Photo" style="width:120px;height:140px;object-fit:cover;border-radius:4px;">
            <?php else: ?>
                <div class="photo-preview">No Photo</div>
            <?php endif; ?>
        </div>
        <div>
            <p><strong>Staff ID:</strong> <?= sanitize($viewTeacher['staff_id']) ?></p>
            <p><strong>Username:</strong> <?= sanitize($viewTeacher['username']) ?></p>
            <p><strong>Sex:</strong> <?= sanitize($viewTeacher['sex']) ?></p>
            <p><strong>DOB:</strong> <?= sanitize($viewTeacher['date_of_birth'] ?? '-') ?></p>
            <p><strong>Marital Status:</strong> <?= sanitize($viewTeacher['marital_status']) ?></p>
            <p><strong>Rank:</strong> <?= sanitize($viewTeacher['current_rank'] ?? '-') ?></p>
        </div>
        <div>
            <p><strong>License No:</strong> <?= sanitize($viewTeacher['license_number'] ?? '-') ?></p>
            <p><strong>GPS:</strong> <?= sanitize($viewTeacher['gps'] ?? '-') ?></p>
            <p><strong>SSNIT:</strong> <?= sanitize($viewTeacher['ssnit_no'] ?? '-') ?></p>
            <p><strong>EMIS:</strong> <?= sanitize($viewTeacher['emis_code'] ?? '-') ?></p>
            <p><strong>Email:</strong> <?= sanitize($viewTeacher['email'] ?? '-') ?></p>
            <p><strong>Phone:</strong> <?= sanitize($viewTeacher['phone'] ?? '-') ?></p>
        </div>
        <div>
            <p><strong>Bank:</strong> <?= sanitize($viewTeacher['bank_name'] ?? '-') ?></p>
            <p><strong>Account:</strong> <?= sanitize($viewTeacher['bank_account_number'] ?? '-') ?></p>
            <p><strong>Branch:</strong> <?= sanitize($viewTeacher['bank_branch'] ?? '-') ?></p>
            <p><strong>Class Taught:</strong> <?= sanitize($viewTeacher['class_taught'] ?? '-') ?></p>
            <p><strong>Status:</strong>
                <?php if ($viewTeacher['is_approved']): ?>
                    <span class="badge badge-success">Approved</span>
                <?php else: ?>
                    <span class="badge badge-warning">Pending</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="btn-group">
        <?php if (!$viewTeacher['is_approved']): ?>
            <a href="?approve=<?= $viewTeacher['id'] ?>" class="btn btn-primary">Approve</a>
        <?php else: ?>
            <a href="?deactivate=<?= $viewTeacher['id'] ?>" class="btn btn-danger">Deactivate</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/teachers.php" class="btn btn-dark">Back to List</a>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3>All Teachers</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staff ID</th>
                    <th>Full Name</th>
                    <th>Sex</th>
                    <th>Phone</th>
                    <th>Class</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($teachers)): ?>
                <tr><td colspan="8">No teachers registered yet.</td></tr>
            <?php else: foreach ($teachers as $i => $t): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= sanitize($t['staff_id']) ?></td>
                    <td><?= sanitize($t['full_name']) ?></td>
                    <td><?= sanitize($t['sex']) ?></td>
                    <td><?= sanitize($t['phone'] ?? '-') ?></td>
                    <td><?= sanitize($t['class_taught'] ?? '-') ?></td>
                    <td>
                        <?php if ($t['is_approved']): ?>
                            <span class="badge badge-success">Approved</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?view=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                        <a href="<?= APP_URL ?>/admin/edit_teacher.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <?php if (!$t['is_approved']): ?>
                            <a href="?approve=<?= $t['id'] ?>" class="btn btn-sm btn-success">Approve</a>
                        <?php endif; ?>
                        <a href="<?= APP_URL ?>/admin/reset_password.php?teacher_id=<?= $t['id'] ?>" class="btn btn-sm btn-gold">Reset Pwd</a>
                        <a href="?delete=<?= $t['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this teacher permanently?">Del</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
