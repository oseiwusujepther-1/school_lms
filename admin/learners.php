<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$pageTitle = 'Learners';
$pdo = getDB();

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("UPDATE learners SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    flash('Learner deactivated successfully.', 'info');
    redirect(APP_URL . '/admin/learners.php');
}

// Handle permanent delete
if (isset($_GET['permanent_delete'])) {
    $id = (int)$_GET['permanent_delete'];
    // Delete scores and submissions first
    $pdo->prepare("DELETE FROM scores WHERE learner_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM report_submissions WHERE learner_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM learners WHERE id = ?")->execute([$id]);
    flash('Learner permanently deleted.', 'info');
    redirect(APP_URL . '/admin/learners.php');
}

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
    <div style="display:flex;gap:8px;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="class_id" onchange="this.form.submit()">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive" style="overflow-x:auto;">
        <table class="data-table" style="font-size:0.85rem;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Adm No</th>
                    <th>Full Name</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>Class</th>
                    <th>DOB</th>
                    <th>NHIS No</th>
                    <th>Ghana Card</th>
                    <th>Prev School</th>
                    <th>Mother</th>
                    <th>Mother Occ</th>
                    <th>Mother Contact</th>
                    <th>Father</th>
                    <th>Father Occ</th>
                    <th>Father Contact</th>
                    <th>Guardian</th>
                    <th>Guardian Occ</th>
                    <th>Guardian Contact</th>
                    <th>Resident</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($learners)): ?>
                <tr><td colspan="21">No learners found.</td></tr>
            <?php else: foreach ($learners as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= sanitize($l['admission_no']) ?></td>
                    <td><?= sanitize($l['full_name']) ?></td>
                    <td><?= sanitize($l['sex']) ?></td>
                    <td><?= sanitize($l['age'] ?? '-') ?></td>
                    <td><?= sanitize($l['class_name'] ?? '-') ?></td>
                    <td><?= sanitize($l['date_of_birth'] ?? '-') ?></td>
                    <td><?= sanitize($l['nhis_no'] ?? '-') ?></td>
                    <td><?= sanitize($l['ghana_card_no'] ?? '-') ?></td>
                    <td><?= sanitize($l['previous_school'] ?? '-') ?></td>
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
                    <td>
                        <a href="<?= APP_URL ?>/headteacher/edit_learner.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <a href="?delete=<?= $l['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Deactivate this learner?">Deactivate</a>
                        <a href="?permanent_delete=<?= $l['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Permanently delete this learner and all data?">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

