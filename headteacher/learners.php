<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('headteacher');
$pageTitle = 'Learners List';
$pdo = getDB();

$classFilter = $_GET['class_id'] ?? '';
$sql = "SELECT l.*, c.class_name FROM learners l LEFT JOIN classes c ON l.class_id = c.id WHERE l.is_active = 1";
$params = [];
if ($classFilter) {
    $sql .= " AND l.class_id = ?";
    $params[] = $classFilter;
}
$sql .= " ORDER BY c.id, l.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$learners = $stmt->fetchAll();
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY id")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>All Learners</h1>
    <div style="display:flex;gap:8px;">
        <form method="GET">
            <select name="class_id" onchange="this.form.submit()">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="<?= APP_URL ?>/headteacher/enroll.php" class="btn btn-primary">+ Enroll</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Admission No</th>
                    <th>Full Name</th>
                    <th>Sex</th>
                    <th>DOB</th>
                    <th>Age</th>
                    <th>Class</th>
                    <th>NHIS</th>
                    <th>Mother</th>
                    <th>Father</th>
                    <th>Guardian</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($learners)): ?>
                <tr><td colspan="11">No learners found.</td></tr>
            <?php else: foreach ($learners as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= sanitize($l['admission_no']) ?></td>
                    <td><?= sanitize($l['full_name']) ?></td>
                    <td><?= sanitize($l['sex']) ?></td>
                    <td><?= $l['date_of_birth'] ? date('d/m/Y', strtotime($l['date_of_birth'])) : '-' ?></td>
                    <td><?= sanitize($l['age'] ?? '-') ?></td>
                    <td><?= sanitize($l['class_name'] ?? '-') ?></td>
                    <td><?= sanitize($l['nhis_no'] ?? '-') ?></td>
                    <td><?= sanitize($l['mother_name'] ?? '-') ?></td>
                    <td><?= sanitize($l['father_name'] ?? '-') ?></td>
                    <td><?= sanitize($l['guardian_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
