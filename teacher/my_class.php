 <?php
require_once __DIR__ . '/../includes/config.php';
requireRole('teacher');
$pageTitle = 'My Class';
$pdo = getDB();
$teacherId = $_SESSION['teacher_id'] ?? 0;

$classId = (int)($_GET['class_id'] ?? 0);

// Verify teacher is assigned to this class
$check = $pdo->prepare("SELECT c.id, c.class_name FROM class_teachers ct JOIN classes c ON ct.class_id = c.id WHERE ct.teacher_id = ? AND ct.class_id = ?");
$check->execute([$teacherId, $classId]);
$class = $check->fetch();

if (!$class && $classId) {
    flash('You are not assigned to this class.', 'error');
    redirect(APP_URL . '/teacher/dashboard.php');
}

// Get all classes for this teacher
$myClasses = $pdo->prepare("SELECT c.id, c.class_name FROM class_teachers ct JOIN classes c ON ct.class_id = c.id WHERE ct.teacher_id = ? ORDER BY c.id");
$myClasses->execute([$teacherId]);
$classes = $myClasses->fetchAll();

$learners = [];
if ($classId) {
    $stmt = $pdo->prepare("SELECT * FROM learners WHERE class_id = ? AND is_active = 1 ORDER BY full_name");
    $stmt->execute([$classId]);
    $learners = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>My Class Learners</h1>
    <form method="GET">
        <select name="class_id" onchange="this.form.submit()">
            <option value="">Select Class...</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['class_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($class): ?>
<div class="card">
    <h3><?= sanitize($class['class_name']) ?> — <?= count($learners) ?> Learners</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Admission No</th>
                    <th>Full Name</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($learners)): ?>
                <tr><td colspan="6">No learners in this class yet.</td></tr>
            <?php else: foreach ($learners as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= sanitize($l['admission_no']) ?></td>
                    <td><?= sanitize($l['full_name']) ?></td>
                    <td><?= sanitize($l['sex']) ?></td>
                    <td><?= sanitize($l['age'] ?? '-') ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/teacher/report_card.php?class_id=<?= $classId ?>&learner_id=<?= $l['id'] ?>" class="btn btn-sm btn-primary">Enter Scores</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <p>Please select a class from the dropdown above.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>