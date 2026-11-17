<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$pageTitle = 'Admin Dashboard';
$pdo = getDB();

$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$pendingTeachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_approved = 0")->fetchColumn();
$totalLearners = $pdo->query("SELECT COUNT(*) FROM learners WHERE is_active = 1")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$pendingReports = $pdo->query("SELECT COUNT(*) FROM report_submissions WHERE status = 'submitted'")->fetchColumn();

$recentTeachers = $pdo->query("SELECT t.*, u.username FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll();
$recentLearners = $pdo->query("SELECT l.*, c.class_name FROM learners l LEFT JOIN classes c ON l.class_id = c.id ORDER BY l.created_at DESC LIMIT 5")->fetchAll();

// Staff login credentials: headteacher + all teachers (username & password for admin reference)
$credentials = $pdo->query("
    SELECT u.id AS user_id, u.username, u.password_plain, u.role, u.full_name AS user_full_name,
           t.staff_id, t.is_approved, t.class_taught
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.role IN ('headteacher', 'teacher')
    ORDER BY (u.role = 'headteacher') DESC, u.created_at ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-bg" style="background-image:url('<?= APP_URL ?>/assets/images/admin_dashboard.jpg');"></div>

<div class="page-header">
    <h1>Admin Dashboard</h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <img src="<?= APP_URL ?>/assets/images/teachers_card.jpg" alt="Total Teachers" class="stat-card-img">
        <div class="stat-number"><?= $totalTeachers ?></div>
        <div class="stat-label">Total Teachers</div>
    </div>
    <div class="stat-card gold">
        <img src="<?= APP_URL ?>/assets/images/pending_approval.jpg" alt="Pending Approvals" class="stat-card-img">
        <div class="stat-number"><?= $pendingTeachers ?></div>
        <div class="stat-label">Pending Approvals</div>
    </div>
    <div class="stat-card purple">
        <img src="<?= APP_URL ?>/assets/images/active_learners.jpg" alt="Active Learners" class="stat-card-img">
        <div class="stat-number"><?= $totalLearners ?></div>
        <div class="stat-label">Active Learners</div>
    </div>
    <div class="stat-card red">
        <img src="<?= APP_URL ?>/assets/images/classes.jpg" alt="Classes" class="stat-card-img">
        <div class="stat-number"><?= $totalClasses ?></div>
        <div class="stat-label">Classes</div>
    </div>
    <div class="stat-card">
        <img src="<?= APP_URL ?>/assets/images/pending_reports.jpg" alt="Pending Reports" class="stat-card-img">
        <div class="stat-number"><?= $pendingReports ?></div>
        <div class="stat-label">Pending Reports</div>
    </div>
</div>

<div class="form-grid">
    <div class="card">
        <h3>Recent Teacher Registrations</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Staff ID</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (empty($recentTeachers)): ?>
                    <tr><td colspan="4">No teachers yet.</td></tr>
                <?php else: foreach ($recentTeachers as $t): ?>
                    <tr>
                        <td><?= sanitize($t['full_name']) ?></td>
                        <td><?= sanitize($t['staff_id']) ?></td>
                        <td>
                            <?php if ($t['is_approved']): ?>
                                <span class="badge badge-success">Approved</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?= APP_URL ?>/admin/teachers.php" class="btn btn-sm btn-secondary">View</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3>Staff Login Credentials</h3>
        <p class="hint" style="margin-bottom:1rem;">Usernames and passwords for the headteacher and every registered teacher (visible to the admin only).</p>
        <div class="table-responsive" style="overflow-x:auto;">
            <table class="data-table" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Password</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($credentials)): ?>
                    <tr><td colspan="4">No headteacher or teacher accounts yet.</td></tr>
                <?php else: foreach ($credentials as $c): ?>
                    <tr>
                        <td><?= sanitize(ucfirst($c['role'])) ?></td>
                        <td><?= sanitize($c['user_full_name']) ?></td>
                        <td><?= sanitize($c['username']) ?></td>
                        <td><?= sanitize($c['password_plain'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3>Recent Enrollments</h3>
        <div class="table-responsive" style="overflow-x:auto;">
            <table class="data-table" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Adm No</th>
                        <th>Full Name</th>
                        <th>Sex</th>
                        <th>Age</th>
                        <th>Class</th>
                        <th>DOB</th>
                        <th>NHIS</th>
                        <th>Ghana Card</th>
                        <th>Mother</th>
                        <th>Mother Work</th>
                        <th>Mother Contact</th>
                        <th>Father</th>
                        <th>Father Work</th>
                        <th>Father Contact</th>
                        <th>Guardian</th>
                        <th>Guardian Work</th>
                        <th>Guardian Contact</th>
                        <th>Resident/GPS</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentLearners)): ?>
                    <tr><td colspan="18">No learners enrolled yet.</td></tr>
                <?php else: foreach ($recentLearners as $l): ?>
                    <tr>
                        <td><?= sanitize($l['admission_no']) ?></td>
                        <td><?= sanitize($l['full_name']) ?></td>
                        <td><?= sanitize($l['sex']) ?></td>
                        <td><?= sanitize($l['age'] ?? '-') ?></td>
                        <td><?= sanitize($l['class_name'] ?? '-') ?></td>
                        <td><?= sanitize($l['date_of_birth'] ?? '-') ?></td>
                        <td><?= sanitize($l['nhis_no'] ?? '-') ?></td>
                        <td><?= sanitize($l['ghana_card_no'] ?? '-') ?></td>
                        <td><?= sanitize($l['mother_name'] ?? '-') ?></td>
                        <td><?= sanitize($l['mother_occupation'] ?? '-') ?></td>
                        <td><?= sanitize($l['mother_contact'] ?? '-') ?></td>
                        <td><?= sanitize($l['father_name'] ?? '-') ?></td>
                        <td><?= sanitize($l['father_occupation'] ?? '-') ?></td>
                        <td><?= sanitize($l['father_contact'] ?? '-') ?></td>
                        <td><?= sanitize($l['guardian_name'] ?? '-') ?></td>
                        <td><?= sanitize($l['guardian_occupation'] ?? '-') ?></td>
                        <td><?= sanitize($l['guardian_contact'] ?? '-') ?></td>
                        <td><?= sanitize($l['parents_resident'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
</table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

