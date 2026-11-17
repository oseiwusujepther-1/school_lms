<?php
require_once __DIR__ . '/../includes/config.php';
requireRole(['admin', 'headteacher']);
$pageTitle = 'Submitted Reports';
$pdo = getDB();

// Approve / mark printed
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $pdo->prepare("UPDATE report_submissions SET status = 'approved', approved_at = NOW() WHERE id = ?")->execute([$id]);
    flash('Report approved.');
    redirect(APP_URL . '/headteacher/reports.php');
}
if (isset($_GET['printed'])) {
    $id = (int)$_GET['printed'];
    $pdo->prepare("UPDATE report_submissions SET status = 'printed' WHERE id = ?")->execute([$id]);
    flash('Marked as printed.', 'info');
    redirect(APP_URL . '/headteacher/reports.php');
}

$statusFilter = $_GET['status'] ?? 'submitted';
$sql = "SELECT rs.*, l.full_name as learner_name, l.admission_no, c.class_name, t.full_name as teacher_name, tm.term_name
        FROM report_submissions rs
        JOIN learners l ON rs.learner_id = l.id
        JOIN classes c ON rs.class_id = c.id
        JOIN teachers t ON rs.teacher_id = t.id
        JOIN terms tm ON rs.term_id = tm.id
        WHERE 1=1";
$params = [];
if ($statusFilter && $statusFilter !== 'all') {
    $sql .= " AND rs.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY rs.submitted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Report Submissions</h1>
    <form method="GET" style="display:flex;gap:8px;">
        <select name="status" onchange="this.form.submit()">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="printed" <?= $statusFilter === 'printed' ? 'selected' : '' ?>>Printed</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Learner</th>
                    <th>Admission</th>
                    <th>Class</th>
                    <th>Term</th>
                    <th>Teacher</th>
                    <th>Total</th>
                    <th>Agg</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="12">No reports found.</td></tr>
            <?php else: foreach ($reports as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= sanitize($r['learner_name']) ?></td>
                    <td><?= sanitize($r['admission_no']) ?></td>
                    <td><?= sanitize($r['class_name']) ?></td>
                    <td><?= sanitize($r['term_name']) ?></td>
                    <td><?= sanitize($r['teacher_name']) ?></td>
                    <td><?= sanitize($r['grand_total']) ?></td>
                    <td><?= sanitize($r['aggregate_number']) ?></td>
                    <td><?= sanitize($r['position'] ?? '-') ?></td>
                    <td>
                        <?php
                        $badge = match($r['status']) {
                            'submitted' => 'badge-warning',
                            'approved' => 'badge-success',
                            'printed' => 'badge-info',
                            default => 'badge-danger'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= ucfirst($r['status']) ?></span>
                    </td>
                    <td><?= $r['submitted_at'] ? date('d/m/Y H:i', strtotime($r['submitted_at'])) : '-' ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/admin/view_report.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">View/Print</a>
                        <?php if ($r['status'] === 'submitted'): ?>
                            <a href="?approve=<?= $r['id'] ?>" class="btn btn-sm btn-primary">Approve</a>
                        <?php endif; ?>
                        <?php if ($r['status'] === 'approved'): ?>
                            <a href="?printed=<?= $r['id'] ?>" class="btn btn-sm btn-dark">Mark Printed</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
