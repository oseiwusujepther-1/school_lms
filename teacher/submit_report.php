<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('teacher');
$pageTitle = 'My Submitted Reports';
$pdo = getDB();
$teacherId = $_SESSION['teacher_id'] ?? 0;

$reports = $pdo->prepare("SELECT rs.*, l.full_name as learner_name, l.admission_no, c.class_name, tm.term_name
    FROM report_submissions rs
    JOIN learners l ON rs.learner_id = l.id
    JOIN classes c ON rs.class_id = c.id
    JOIN terms tm ON rs.term_id = tm.id
    WHERE rs.teacher_id = ?
    ORDER BY rs.created_at DESC");
$reports->execute([$teacherId]);
$list = $reports->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-bg" style="background-image:url('<?= APP_URL ?>/assets/images/report_submission_background.jpg');"></div>

<div class="page-header">
    <h1 style="color:#fff;">My Report Submissions</h1>
    <a href="<?= APP_URL ?>/teacher/report_card.php" class="btn btn-primary">New Report Card</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Learner</th>
                    <th>Class</th>
                    <th>Term</th>
                    <th>Total</th>
                    <th>Aggregate</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($list)): ?>
                <tr><td colspan="10">No reports yet.</td></tr>
            <?php else: foreach ($list as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= sanitize($r['learner_name']) ?></td>
                    <td><?= sanitize($r['class_name']) ?></td>
                    <td><?= sanitize($r['term_name']) ?></td>
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
                    <td><?= $r['submitted_at'] ? date('d/m/Y', strtotime($r['submitted_at'])) : '-' ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/teacher/report_card.php?class_id=<?= $r['class_id'] ?>&learner_id=<?= $r['learner_id'] ?>&term_id=<?= $r['term_id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>