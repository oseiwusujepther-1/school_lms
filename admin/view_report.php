<?php
require_once __DIR__ . '/../includes/config.php';
requireRole(['admin', 'headteacher']);
$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT rs.*, l.*, c.class_name, t.full_name as teacher_name, t.phone as teacher_phone, tm.term_name, tm.term_ends, tm.next_term_begins,
    ss.school_name, ss.address, ss.gps, ss.telephone, ss.email, ss.headteacher_name
    FROM report_submissions rs
    JOIN learners l ON rs.learner_id = l.id
    JOIN classes c ON rs.class_id = c.id
    JOIN teachers t ON rs.teacher_id = t.id
    JOIN terms tm ON rs.term_id = tm.id
    CROSS JOIN school_settings ss
    WHERE rs.id = ?");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) {
    flash('Report not found.', 'error');
    redirect(APP_URL . '/admin/reports.php');
}

// Get scores
$scoreStmt = $pdo->prepare("SELECT s.*, sub.subject_name FROM scores s JOIN subjects sub ON s.subject_id = sub.id WHERE s.learner_id = ? AND s.term_id = ? ORDER BY sub.sort_order");
$scoreStmt->execute([$r['learner_id'], $r['term_id']]);
$scores = $scoreStmt->fetchAll();

$pageTitle = 'Report Card - ' . $r['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.report-card {
    max-width: 210mm;
    margin: 0 auto;
    background: #edf3e8;
    border: 5px solid #008000;
    border-radius: 10px;
    padding: 15px;
    font-size: 11pt;
}
.report-card .header { text-align: center; margin-bottom: 10px; }
.report-card .school-name { color: #8B0000; font-size: 16pt; margin: 4px 0; }
.report-card .student-info { display: flex; justify-content: space-between; border: 1px solid #000; padding: 8px; margin-bottom: 8px; }
.report-card table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 10pt; }
.report-card th, .report-card td { border: 1px solid #000; padding: 3px 5px; text-align: center; }
.report-card th { background: #e0e0e0; }
.report-card .subject-col { text-align: left; font-weight: bold; }
.report-card .footer-row { display: flex; margin: 4px 0; }
.report-card .footer-label { font-weight: bold; width: 200px; }
.report-card .footer-line { flex: 1; border-bottom: 1px dotted #000; }
@media print {
    .no-print { display: none !important; }
    .report-card { border: 5px solid #008000; box-shadow: none; }
    body { background: white; }
}
</style>

<div class="no-print" style="text-align:center;margin-bottom:1rem;">
    <button onclick="window.print()" class="btn btn-primary">Print Report Card</button>
    <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-dark">Back</a>
</div>

<div class="report-card">
    <div class="header">
        <div style="position:relative;">
            <div style="position:absolute; left:0; top:0; width:70px; height:70px; border:1px solid #000; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#fff;">
                <img src="<?= APP_URL ?>/assets/logos/logo.jpg" alt="Logo" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
            </div>
            <div style="font-size:10pt;">GHANA EDUCATION SERVICE</div>
            <h1 class="school-name"><?= sanitize($r['school_name']) ?></h1>
            <div style="font-size:9pt;">
                <?= sanitize($r['address']) ?><br>
                <strong>GPS:</strong> <?= sanitize($r['gps']) ?> | <strong>Tel:</strong> <?= sanitize($r['telephone']) ?><br>
                <strong>Email:</strong> <?= sanitize($r['email']) ?>
            </div>
        </div>
    </div>

    <div class="student-info">
        <div style="flex:1;">
            <div><strong>Name:</strong> <?= sanitize(strtoupper($r['full_name'])) ?></div>
            <div><strong>Age:</strong> <?= sanitize($r['age'] ?? '-') ?> &nbsp; <strong>Class:</strong> <?= sanitize($r['class_name']) ?></div>
            <div><strong>Admission No:</strong> <?= sanitize($r['admission_no']) ?></div>
            <div><strong>No. on Roll:</strong> <?= sanitize($r['no_on_roll'] ?? '-') ?></div>
            <div><strong>Teacher:</strong> <?= sanitize(strtoupper($r['teacher_name'])) ?></div>
            <div><strong>Term:</strong> <?= sanitize($r['term_name']) ?> &nbsp; <strong>Year:</strong> 2025/2026</div>
            <div><strong>Position:</strong> <?= sanitize($r['position'] ?? 'N/A') ?></div>
            <div><strong>Term Ends:</strong> <?= $r['term_ends'] ? date('d/m/Y', strtotime($r['term_ends'])) : '-' ?>
                &nbsp; <strong>Next Term:</strong> <?= $r['next_term_begins'] ? date('d/m/Y', strtotime($r['next_term_begins'])) : '-' ?></div>
        </div>
        <div style="width:70px; height:90px; border:1px solid #000; display:flex; align-items:center; justify-content:center; overflow:hidden; font-size:8px; color:#888;">
            <?php if (!empty($r['passport_photo'])): ?>
                <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($r['passport_photo']) ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <span>Passport<br>Photo</span>
            <?php endif; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="30%">SUBJECTS</th>
                <th>Class (50%)</th>
                <th>Exam (50%)</th>
                <th>Total (100%)</th>
                <th>Grade</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($scores as $s): ?>
            <tr>
                <td class="subject-col"><?= sanitize($s['subject_name']) ?></td>
                <td><?= $s['class_score'] ?></td>
                <td><?= $s['exam_score'] ?></td>
                <td><strong><?= $s['total_score'] ?></strong></td>
                <td><strong><?= sanitize($s['grade']) ?></strong></td>
                <td><?= sanitize($s['remark']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#e0e0e0;">
                <th class="subject-col">TOTAL SCORE:</th>
                <th>-</th><th>-</th>
                <th><?= sanitize($r['grand_total']) ?></th>
                <th>-</th><th>-</th>
            </tr>
            <tr style="background:#d0e0d0;">
                <th class="subject-col">AGGREGATE (4 Core + 2 Best Other):</th>
                <th>-</th><th>-</th>
                <th><?= sanitize($r['aggregate_number']) ?></th>
                <th>-</th><th>-</th>
            </tr>
        </tfoot>
    </table>

    <div style="border:1px solid #000;padding:5px;margin-bottom:8px;font-weight:bold;">
        Attendance: <?= sanitize($r['attendance_present'] ?? '_') ?> Present out of <?= sanitize($r['attendance_total'] ?? '_') ?> Days
    </div>

    <div>
        <div class="footer-row"><div class="footer-label">Learner's Conduct:</div><div class="footer-line"><?= sanitize($r['conduct'] ?? '') ?></div></div>
        <div class="footer-row"><div class="footer-label">Learner's Interest:</div><div class="footer-line"><?= sanitize($r['interest'] ?? '') ?></div></div>
        <div class="footer-row"><div class="footer-label">Promoted To:</div><div class="footer-line"><?php
                        $promoText = '';
                        if (!empty($r['promotion_status'])) {
                            if ($r['promotion_status'] === 'Promoted' && !empty($r['promotion_class_id'])) {
                                $pcStmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
                                $pcStmt->execute([$r['promotion_class_id']]);
                                $pcName = $pcStmt->fetchColumn();
                                $promoText = 'Promoted to ' . ($pcName ?: 'Class ' . $r['promotion_class_id']);
                            } elseif ($r['promotion_status'] === 'Repeated') {
                                $promoText = 'Repeated ' . $r['class_name'];
                            } else {
                                $promoText = $r['promotion_status'];
                            }
                        }
                        echo sanitize($promoText);
                    ?></div></div>
        <div class="footer-row"><div class="footer-label">Headteacher's Name:</div><div class="footer-line"><?= sanitize($r['headteacher_name'] ?? '') ?></div></div>
<div class="footer-row"><div class="footer-label">Class Teacher's Tel:</div><div class="footer-line"><?= sanitize($r['teacher_phone'] ?? '') ?></div></div>
        <div class="footer-row"><div class="footer-label">Class Teacher's Remark:</div><div class="footer-line"><?= sanitize($r['teacher_remark'] ?? '') ?></div></div>
    </div>

    <div style="display:flex;justify-content:space-around;margin-top:20px;">
        <div style="text-align:center;width:40%;"><div style="border-bottom:1px solid #000;height:20px;"></div>Class Teacher's Signature</div>
        <div style="text-align:center;width:40%;"><div style="border-bottom:1px solid #000;height:20px;"></div>Head Teacher's Signature</div>
    </div>
    <div style="text-align:center;margin-top:10px;font-size:8pt;font-style:italic;">System Generated Report - School LMS</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
