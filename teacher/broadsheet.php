<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('teacher');
$pageTitle = 'Class Broadsheet & Summary';
$pdo = getDB();
$teacherId = $_SESSION['teacher_id'] ?? 0;

$classId = (int)($_GET['class_id'] ?? 0);
$termId  = (int)($_GET['term_id'] ?? 0);

$settings = $pdo->query("SELECT * FROM school_settings WHERE id = 1")->fetch();
$teacherStmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$teacherStmt->execute([$teacherId]);
$teacherRow = $teacherStmt->fetch();

$myClasses = $pdo->prepare(
    "SELECT c.id, c.class_name FROM class_teachers ct JOIN classes c ON ct.class_id = c.id WHERE ct.teacher_id = ? ORDER BY c.id"
);
$myClasses->execute([$teacherId]);
$classes = $myClasses->fetchAll();

$terms = $pdo->query("SELECT * FROM terms ORDER BY id")->fetchAll();

if (!$termId) {
    foreach ($terms as $t) { if ($t['is_current']) { $termId = $t['id']; break; } }
    if (!$termId && !empty($terms)) $termId = $terms[0]['id'];
}

if (isset($_GET['submit_all']) && $classId && $termId) {
    $pdo->prepare("UPDATE report_submissions SET status = 'submitted', submitted_at = NOW() WHERE class_id = ? AND term_id = ? AND teacher_id = ? AND status = 'draft'")->execute([$classId, $termId, $teacherId]);
    flash('All draft reports submitted.');
    redirect(APP_URL . '/teacher/broadsheet.php?class_id=' . $classId . '&term_id=' . $termId);
}

if (isset($_GET['update']) && $classId && $termId) {
    recalculateClassPositions($pdo, $classId, $termId);
    flash('Class positions recalculated.');
    redirect(APP_URL . '/teacher/broadsheet.php?class_id=' . $classId . '&term_id=' . $termId);
}

$learners = [];
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY sort_order")->fetchAll();
$classData = null;

if ($classId && $termId) {
    $cStmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $cStmt->execute([$classId]);
    $classData = $cStmt->fetch();

    $stmt = $pdo->prepare(
        "SELECT l.id, l.full_name, l.admission_no, l.sex, l.age, rs.grand_total, rs.aggregate_number, rs.position, rs.status,
                rs.no_on_roll, rs.attendance_present, rs.attendance_total, rs.conduct, rs.interest, rs.teacher_remark
         FROM learners l
         LEFT JOIN report_submissions rs ON rs.learner_id = l.id AND rs.term_id = ? AND rs.teacher_id = ?
         WHERE l.class_id = ? AND l.is_active = 1
         ORDER BY l.full_name"
    );
    $stmt->execute([$termId, $teacherId, $classId]);
    $learners = $stmt->fetchAll();

    $learnerIds = array_column($learners, 'id');
    $scoresByLearner = [];
    if (!empty($learnerIds)) {
        $placeholders = implode(',', array_fill(0, count($learnerIds), '?'));
        $params = array_merge($learnerIds, [$termId]);
        $scStmt = $pdo->prepare(
            "SELECT s.*, sub.subject_name, sub.is_core FROM scores s JOIN subjects sub ON s.subject_id = sub.id WHERE s.learner_id IN ($placeholders) AND s.term_id = ? ORDER BY sub.sort_order"
        );
        $scStmt->execute($params);
        foreach ($scStmt->fetchAll() as $sc) {
            $scoresByLearner[$sc['learner_id']][$sc['subject_id']] = $sc;
        }
    }
}

function recalculateClassPositions(PDO $pdo, int $classId, int $termId): void {
    if (!$classId || !$termId) return;
    $stmt = $pdo->prepare(
        "SELECT rs.id, rs.learner_id, rs.grand_total, rs.aggregate_number FROM report_submissions rs
         JOIN learners l ON rs.learner_id = l.id
         WHERE l.class_id = ? AND rs.term_id = ? AND rs.grand_total IS NOT NULL AND rs.grand_total > 0
         ORDER BY rs.grand_total DESC, rs.aggregate_number ASC"
    );
    $stmt->execute([$classId, $termId]);
    $rows = $stmt->fetchAll();
    $rank = 0; $lastTotal = -1; $atRank = 0;
    foreach ($rows as $i => $row) {
        $total = (float)$row['grand_total'];
        if ($total < $lastTotal) { $rank += $atRank; $atRank = 1; }
        elseif ($total == $lastTotal) { $atRank++; }
        else { $rank = $i + 1; $atRank = 1; }
        $lastTotal = $total;
        $suffix = 'th';
        if ($rank % 10 === 1 && $rank % 100 !== 11) $suffix = 'st';
        elseif ($rank % 10 === 2 && $rank % 100 !== 12) $suffix = 'nd';
        elseif ($rank % 10 === 3 && $rank % 100 !== 13) $suffix = 'rd';
        $pdo->prepare("UPDATE report_submissions SET position = ? WHERE id = ?")->execute([$rank . $suffix, $row['id']]);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.broadsheet-container { margin-top: 10px; }
.broadsheet-controls { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; background: #f5f5f5; padding: 15px; border-radius: 8px; border-top: 4px solid #008000; margin-bottom: 20px; }
.broadsheet-controls .form-group { margin-bottom: 0; min-width: 180px; }
.broadsheet-controls label { font-size: 12px; font-weight: bold; display: block; margin-bottom: 2px; }
.broadsheet-controls select, .broadsheet-controls input { width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
.broadsheet-table-wrap { overflow-x: auto; margin-bottom: 20px; }
.broadsheet-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; min-width: 900px; }
.broadsheet-table th, .broadsheet-table td { border: 1px solid #000; padding: 3px 4px; text-align: center; white-space: nowrap; }
.broadsheet-table th { background: #008000; color: white; font-weight: 600; }
.broadsheet-table .learner-name-col { text-align: left; font-weight: bold; min-width: 160px; }
.broadsheet-table .rank-1st { background: #FFD700; font-weight: bold; }
.broadsheet-table .rank-2nd { background: #C0C0C0; font-weight: bold; }
.broadsheet-table .rank-3rd { background: #CD7F32; font-weight: bold; color: white; }
.broadsheet-table .above-avg { background: #d4edda; }
.broadsheet-table .at-avg { background: #fff3cd; }
.broadsheet-table .below-avg { background: #f8d7da; }
.stats-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 20px; }
.stats-card { background: white; border-radius: 8px; padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #008000; }
.stats-card .stats-number { font-size: 1.8rem; font-weight: 700; color: #008000; }
.stats-card .stats-label { font-size: 0.8rem; color: #666; margin-top: 2px; }
.stats-card.gold { border-top-color: #FFD700; }
.stats-card.red { border-top-color: #8B0000; }
.stats-card.purple { border-top-color: #3a0061; }
.stats-card.blue { border-top-color: #021a41; }
.subject-stats-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 20px; }
.subject-stats-table th, .subject-stats-table td { border: 1px solid #000; padding: 4px 6px; text-align: center; }
.subject-stats-table th { background: #3a0061; color: white; }
.subject-stats-table .subject-name-col { text-align: left; font-weight: bold; }
.broadsheet-actions { display: flex; gap: 10px; flex-wrap: wrap; margin: 15px 0; }
.broadsheet-header { display: flex; gap: 20px; align-items: center; margin-bottom: 15px; padding: 15px; background: linear-gradient(135deg, #f0f8f0, #e8f5e9); border: 2px solid #008000; border-radius: 10px; }
.broadsheet-header-logo { width: 90px; height: 90px; border: 2px solid #008000; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: white; flex-shrink: 0; }
.broadsheet-header-logo img { width: 100%; height: 100%; object-fit: cover; }
.broadsheet-header-info { flex: 1; }
.broadsheet-header-info .school-name { font-size: 14pt; font-weight: bold; color: #630303; margin-bottom: 2px; }
.broadsheet-header-info .school-address { font-size: 8pt; color: #333; margin-bottom: 2px; }
.broadsheet-header-info .school-contact { font-size: 7.5pt; color: #555; margin-bottom: 4px; }
.broadsheet-header-info .broadsheet-title { font-size: 12pt; font-weight: bold; color: #008000; border-top: 2px solid #FFD700; padding-top: 4px; margin-top: 4px; }
@media print { body { background: white; } .navbar, .main-content > .page-header, .broadsheet-controls, .broadsheet-actions, .flash-message, .btn, .no-print { display: none !important; } .broadsheet-container { margin-top: 0; } .stats-summary { break-inside: avoid; } .broadsheet-table-wrap { overflow: visible; } .broadsheet-table { font-size: 7pt; min-width: auto; width: 100%; } .broadsheet-table th, .broadsheet-table td { padding: 2px 3px; } .broadsheet-header { break-inside: avoid; } .broadsheet-header-info .school-name { font-size: 11pt; } .broadsheet-header-info .broadsheet-title { font-size: 10pt; } .stats-card { break-inside: avoid; padding: 8px; } .stats-card .stats-number { font-size: 1.2rem; } @page { size: A4 landscape; margin: 0.5cm; } }
</style>

<div class="page-header">
    <h1>Class Broadsheet &amp; Summary</h1>
    <a href="<?= APP_URL ?>/teacher/report_card.php" class="btn btn-dark">Report Card Entry</a>
</div>

<div class="broadsheet-container">
    <form method="GET" class="broadsheet-controls" id="broadsheetForm">
        <div class="form-group">
            <label>Class</label>
            <select name="class_id" onchange="document.getElementById('broadsheetForm').submit()">
                <option value="">Select Class...</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Term</label>
            <select name="term_id" onchange="document.getElementById('broadsheetForm').submit()">
                <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $termId == $t['id'] ? 'selected' : '' ?>><?= sanitize($t['term_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($classId && $termId): ?>
            <div class="form-group" style="min-width:auto;">
                <label>&nbsp;</label>
                <a href="?class_id=<?= $classId ?>&term_id=<?= $termId ?>&update=1" class="btn btn-primary btn-sm">Update / Recalculate</a>
            </div>
            <div class="form-group" style="min-width:auto;">
                <label>&nbsp;</label>
                <a href="?class_id=<?= $classId ?>&term_id=<?= $termId ?>&submit_all=1" class="btn btn-success btn-sm" onclick="return confirm('Submit ALL draft reports?')">Submit All to Headteacher</a>
            </div>
        <?php endif; ?>
    </form>

    <?php if ($classId && $termId && !empty($learners)):
        $grandTotals = array_filter(array_column($learners, 'grand_total'), function($v) { return $v !== null && $v > 0; });
        $overallAvg = !empty($grandTotals) ? round(array_sum($grandTotals) / count($grandTotals), 1) : 0;
        $aboveAvg = 0; $atAvg = 0; $belowAvg = 0;
        $totalWithScores = count($grandTotals);
        foreach ($grandTotals as $gt) {
            if ($gt > $overallAvg) $aboveAvg++;
            elseif ($gt == $overallAvg) $atAvg++;
            else $belowAvg++;
        }
        $submittedCount = 0; $draftCount = 0;
        foreach ($learners as $l) {
            if (empty($l['status'])) continue;
            elseif ($l['status'] === 'draft') $draftCount++;
            else $submittedCount++;
        }
        $subjectStats = [];
        foreach ($subjects as $subj) {
            $scores = [];
            foreach ($learners as $l) {
                if (isset($scoresByLearner[$l['id']][$subj['id']])) {
                    $ts = (float)$scoresByLearner[$l['id']][$subj['id']]['total_score'];
                    if ($ts > 0) $scores[] = $ts;
                }
            }
            $avg = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
            $above = 0; $at = 0; $below = 0;
            foreach ($scores as $s) {
                if ($s > $avg) $above++;
                elseif ($s == $avg) $at++;
                else $below++;
            }
            $subjectStats[$subj['id']] = ['avg' => $avg, 'above' => $above, 'at' => $at, 'below' => $below, 'total' => count($scores)];
        }
    ?>
    <div class="broadsheet-header">
        <div class="broadsheet-header-logo">
            <img src="<?= APP_URL ?>/assets/logos/logo.jpg" alt="School Logo" onerror="this.style.display='none'">
        </div>
        <div class="broadsheet-header-info">
            <div class="school-name"><?= sanitize($settings['school_name'] ?? 'ESSIENIMPONG M/A PRIMARY AND KG SCHOOL') ?></div>
            <div class="school-address"><?= sanitize($settings['address'] ?? '') ?></div>
            <div class="school-contact">GPS: <?= sanitize($settings['gps'] ?? '') ?> | Tel: <?= sanitize($settings['telephone'] ?? '') ?> | Email: <?= sanitize($settings['email'] ?? '') ?></div>
            <div class="broadsheet-title">CLASS PERFORMANCE BROADSHEET - <?= sanitize(strtoupper($classData['class_name'] ?? '')) ?></div>
        </div>
    </div>

    <div class="stats-summary">
        <div class="stats-card"><div class="stats-number"><?= count($learners) ?></div><div class="stats-label">Total Learners</div></div>
        <div class="stats-card gold"><div class="stats-number"><?= $totalWithScores ?></div><div class="stats-label">With Scores</div></div>
        <div class="stats-card purple"><div class="stats-number"><?= $overallAvg ?></div><div class="stats-label">Class Average</div></div>
        <div class="stats-card" style="border-top-color:#28a745;"><div class="stats-number"><?= $aboveAvg ?></div><div class="stats-label">Above Average</div></div>
        <div class="stats-card" style="border-top-color:#ffc107;"><div class="stats-number"><?= $atAvg ?></div><div class="stats-label">At Average</div></div>
        <div class="stats-card red"><div class="stats-number"><?= $belowAvg ?></div><div class="stats-label">Below Average</div></div>
        <div class="stats-card blue"><div class="stats-number"><?= $submittedCount ?></div><div class="stats-label">Submitted</div></div>
        <div class="stats-card" style="border-top-color:#6c757d;"><div class="stats-number"><?= $draftCount ?></div><div class="stats-label">Drafts</div></div>
    </div>

    <div class="broadsheet-actions">
        <a href="?class_id=<?= $classId ?>&term_id=<?= $termId ?>&update=1" class="btn btn-primary">Update / Recalculate</a>
        <a href="?class_id=<?= $classId ?>&term_id=<?= $termId ?>&submit_all=1" class="btn btn-success" onclick="return confirm('Submit ALL draft reports?')">Submit All Reports</a>
        <button onclick="window.print()" class="btn btn-dark">Print Broadsheet</button>
    </div>

    <h3>Class Performance - <?= sanitize($classData['class_name'] ?? '') ?></h3>
    <div class="broadsheet-table-wrap">
        <table class="broadsheet-table">
            <thead>
                <tr><th rowspan="2">#</th><th rowspan="2">Learner Name</th><th rowspan="2">Adm No</th>
                <?php foreach ($subjects as $subj): ?><th colspan="1"><?= sanitize($subj['subject_name']) ?></th><?php endforeach; ?>
                <th rowspan="2">Total</th><th rowspan="2">Rank</th><th rowspan="2">Status</th></tr>
                <tr><?php foreach ($subjects as $subj): ?><th>Score</th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php
            $sortedLearners = $learners;
            usort($sortedLearners, function($a, $b) {
                $posA = $a['position'] ? (int)$a['position'] : 999;
                $posB = $b['position'] ? (int)$b['position'] : 999;
                return $posA - $posB;
            });
            foreach ($sortedLearners as $i => $l):
                $rankClass = '';
                $pos = $l['position'] ?? '';
                if ($pos === '1st') $rankClass = 'rank-1st';
                elseif ($pos === '2nd') $rankClass = 'rank-2nd';
                elseif ($pos === '3rd') $rankClass = 'rank-3rd';
            ?>
            <tr class="<?= $rankClass ?>">
                <td><?= $i + 1 ?></td>
                <td class="learner-name-col"><?= sanitize($l['full_name']) ?></td>
                <td><?= sanitize($l['admission_no']) ?></td>
                <?php foreach ($subjects as $subj):
                    $score = $scoresByLearner[$l['id']][$subj['id']]['total_score'] ?? null;
                    $scoreClass = '';
                    $subjAvg = $subjectStats[$subj['id']]['avg'];
                    if ($score !== null && $score > 0 && $subjAvg > 0) {
                        if ($score > $subjAvg) $scoreClass = 'above-avg';
                        elseif ($score == $subjAvg) $scoreClass = 'at-avg';
                        else $scoreClass = 'below-avg';
                    }
                ?><td class="<?= $scoreClass ?>"><?= $score !== null && $score > 0 ? (float)$score : '-' ?></td>
                <?php endforeach; ?>
                <td style="font-weight:bold;"><?= $l['grand_total'] ? (float)$l['grand_total'] : '-' ?></td>
                <td style="font-weight:bold;"><?= sanitize($pos ?: '-') ?></td>
                <td><?php
                    if (empty($l['status'])) echo '<span class="badge badge-danger">No Report</span>';
                    elseif ($l['status'] === 'draft') echo '<span class="badge badge-warning">Draft</span>';
                    elseif ($l['status'] === 'submitted') echo '<span class="badge badge-info">Submitted</span>';
                    elseif ($l['status'] === 'approved') echo '<span class="badge badge-success">Approved</span>';
                    else echo '<span class="badge">' . ucfirst($l['status']) . '</span>';
                ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#e0e0e0;font-weight:bold;">
                    <td colspan="2">Subject Average</td><td>-</td>
                    <?php foreach ($subjects as $subj): ?><td><?= $subjectStats[$subj['id']]['avg'] ?: '-' ?></td><?php endforeach; ?>
                    <td><?= $overallAvg ?: '-' ?></td><td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <h3>Subject Performance Analysis</h3>
    <div class="broadsheet-table-wrap">
        <table class="subject-stats-table">
            <thead><tr><th class="subject-name-col">Subject</th><th>Average</th><th>Total</th><th style="background:#28a745;color:white;">Above</th><th style="background:#ffc107;">At</th><th style="background:#dc3545;color:white;">Below</th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $subj): $st = $subjectStats[$subj['id']]; ?>
                <tr><td class="subject-name-col"><?= sanitize($subj['subject_name']) ?></td><td style="font-weight:bold;"><?= $st['avg'] ?: '-' ?></td><td><?= $st['total'] ?></td><td style="background:#d4edda;font-weight:bold;"><?= $st['above'] ?></td><td style="background:#fff3cd;"><?= $st['at'] ?></td><td style="background:#f8d7da;font-weight:bold;"><?= $st['below'] ?></td></tr>
            <?php endforeach; ?>
                <tr style="font-weight:bold;background:#e0e0e0;"><td>OVERALL</td><td><?= $overallAvg ?: '-' ?></td><td><?= $totalWithScores ?></td><td style="background:#d4edda;"><?= $aboveAvg ?></td><td style="background:#fff3cd;"><?= $atAvg ?></td><td style="background:#f8d7da;"><?= $belowAvg ?></td></tr>
            </tbody>
        </table>
    </div>
    <?php elseif ($classId && $termId): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <p style="font-size:1.1rem;color:#888;">No learners found in this class.</p>
        <a href="<?= APP_URL ?>/headteacher/enroll.php" class="btn btn-primary">Enroll Learners</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

