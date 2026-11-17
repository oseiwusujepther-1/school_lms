<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$pageTitle = 'Classes Management';
$pdo = getDB();

// Add class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $name = trim($_POST['class_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            flash('Class added successfully.');
        } catch (PDOException $e) {
            flash('Class already exists or error occurred.', 'error');
        }
        redirect(APP_URL . '/admin/classes.php');
    }
}

// Assign teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher'])) {
    $classId = (int)$_POST['class_id'];
    $teacherId = (int)$_POST['teacher_id'];
    if ($classId && $teacherId) {
        try {
            $stmt = $pdo->prepare("INSERT INTO class_teachers (class_id, teacher_id) VALUES (?, ?)");
            $stmt->execute([$classId, $teacherId]);
            flash('Teacher assigned to class successfully.');
        } catch (PDOException $e) {
            flash('Teacher already assigned to this class.', 'error');
        }
        redirect(APP_URL . '/admin/classes.php');
    }
}

// Unassign
if (isset($_GET['unassign'])) {
    $id = (int)$_GET['unassign'];
    $pdo->prepare("DELETE FROM class_teachers WHERE id = ?")->execute([$id]);
    flash('Assignment removed.', 'info');
    redirect(APP_URL . '/admin/classes.php');
}

// Delete class
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);
    flash('Class deleted.', 'info');
    redirect(APP_URL . '/admin/classes.php');
}

$classes = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM learners l WHERE l.class_id = c.id AND l.is_active = 1) as learner_count FROM classes c ORDER BY c.id")->fetchAll();
$teachers = $pdo->query("SELECT id, full_name, staff_id FROM teachers WHERE is_approved = 1 ORDER BY full_name")->fetchAll();
$assignments = $pdo->query("SELECT ct.id, ct.class_id, ct.teacher_id, c.class_name, t.full_name as teacher_name, t.staff_id
    FROM class_teachers ct
    JOIN classes c ON ct.class_id = c.id
    JOIN teachers t ON ct.teacher_id = t.id
    ORDER BY c.id, t.full_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Classes Management</h1>
</div>

<div class="form-grid">
    <div class="card">
        <h3>Add New Class</h3>
        <form method="POST">
            <div class="form-group">
                <label class="required">Class Name</label>
                <input type="text" name="class_name" required placeholder="e.g. Basic 7">
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" placeholder="Optional">
            </div>
            <button type="submit" name="add_class" class="btn btn-primary">Add Class</button>
        </form>
    </div>

    <div class="card">
        <h3>Assign Teacher to Class</h3>
        <form method="POST">
            <div class="form-group">
                <label class="required">Class</label>
                <select name="class_id" required>
                    <option value="">Select Class...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Teacher</label>
                <select name="teacher_id" required>
                    <option value="">Select Teacher...</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= sanitize($t['full_name']) ?> (<?= sanitize($t['staff_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="assign_teacher" class="btn btn-primary">Assign</button>
        </form>
        <p class="hint" style="margin-top:8px;">Multiple teachers can be assigned to the same class. They will share the same class dashboard.</p>
    </div>
</div>

<div class="card">
    <h3>All Classes</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Class Name</th><th>Description</th><th>Learners</th><th>Teachers Assigned</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($classes as $i => $c): ?>
                <?php
                $classTeachers = array_filter($assignments, fn($a) => $a['class_id'] == $c['id']);
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= sanitize($c['class_name']) ?></strong></td>
                    <td><?= sanitize($c['description'] ?? '-') ?></td>
                    <td><?= $c['learner_count'] ?></td>
                    <td>
                        <?php if (empty($classTeachers)): ?>
                            <em style="color:#888;">None</em>
                        <?php else: foreach ($classTeachers as $ct): ?>
                            <span class="badge badge-info"><?= sanitize($ct['teacher_name']) ?></span>
                            <a href="?unassign=<?= $ct['id'] ?>" style="color:red;font-size:0.8rem;" data-confirm="Remove this assignment?">×</a>
                        <?php endforeach; endif; ?>
                    </td>
                    <td>
                        <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this class? Learners will be unassigned.">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<section class="image-gallery" aria-label="School LMS images">
    <img src="/assets/images/active_learners.jpg" alt="Active learners" />
    <img src="/assets/images/admin_dashboard.jpg" alt="Admin dashboard" />
    <img src="/assets/images/approved_report.jpg" alt="Approved report" />
    <img src="/assets/images/classes.jpg" alt="Classes" />
    <img src="/assets/images/draft_report.jpg" alt="Draft report" />
    <img src="/assets/images/enroll_new_pupil.jpg" alt="Enroll new pupil" />
    <img src="/assets/images/login_background.jpg" alt="Login background" />
    <img src="/assets/images/pending_approval.jpg" alt="Pending approval" />
    <img src="/assets/images/pending_reports.jpg" alt="Pending reports" />
    <img src="/assets/images/report_submission_background.jpg" alt="Report submission background" />
    <img src="/assets/images/school_settings.jpg" alt="School settings" />
    <img src="/assets/images/teacher_register_background.jpg" alt="Teacher registration background" />
    <img src="/assets/images/teachers_card.jpg" alt="Teachers card" />
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
