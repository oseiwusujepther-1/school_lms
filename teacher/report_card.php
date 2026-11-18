<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('teacher');
$pageTitle = 'Report Card Entry';
$pdo = getDB();
$teacherId = $_SESSION['teacher_id'] ?? 0;

$classId   = (int)($_GET['class_id']   ?? $_POST['class_id']   ?? 0);
$learnerId = (int)($_GET['learner_id'] ?? $_POST['learner_id'] ?? 0);
$termId    = (int)($_GET['term_id']    ?? $_POST['term_id']    ?? 0);

/* ── Assigned classes for this teacher ── */
$myClasses = $pdo->prepare(
    "SELECT c.id, c.class_name
     FROM class_teachers ct
     JOIN classes c ON ct.class_id = c.id
     WHERE ct.teacher_id = ?
     ORDER BY c.id"
);
$myClasses->execute([$teacherId]);
$classes = $myClasses->fetchAll();

$terms    = $pdo->query("SELECT * FROM terms ORDER BY id")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY sort_order")->fetchAll();
$settings = $pdo->query("SELECT * FROM school_settings WHERE id = 1")->fetch();
$allClasses = $pdo->query("SELECT * FROM classes ORDER BY id")->fetchAll();
$teacher  = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$teacher->execute([$teacherId]);
$teacherRow = $teacher->fetch();

/* Default to current term */
if (!$termId) {
    foreach ($terms as $t) {
        if ($t['is_current']) { $termId = $t['id']; break; }
    }
    if (!$termId && !empty($terms)) $termId = $terms[0]['id'];
}

/* Learners in selected class */
$learners = [];
if ($classId) {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, admission_no, age, sex
         FROM learners WHERE class_id = ? AND is_active = 1
         ORDER BY full_name"
    );
    $stmt->execute([$classId]);
    $learners = $stmt->fetchAll();
}

$learner        = null;
$existingScores = [];
$existingReport = null;
$className      = '';

if ($learnerId && $termId) {
    $stmt = $pdo->prepare(
        "SELECT l.*, c.class_name
         FROM learners l
         JOIN classes c ON l.class_id = c.id
         WHERE l.id = ?"
    );
    $stmt->execute([$learnerId]);
    $learner   = $stmt->fetch();
    $className = $learner['class_name'] ?? '';

    $scStmt = $pdo->prepare("SELECT * FROM scores WHERE learner_id = ? AND term_id = ?");
    $scStmt->execute([$learnerId, $termId]);
    foreach ($scStmt->fetchAll() as $sc) {
        $existingScores[$sc['subject_id']] = $sc;
    }

    $rpStmt = $pdo->prepare(
        "SELECT * FROM report_submissions
         WHERE learner_id = ? AND term_id = ? AND teacher_id = ?"
    );
    $rpStmt->execute([$learnerId, $termId, $teacherId]);
    $existingReport = $rpStmt->fetch();
}

// Determine if we're editing an existing report
$isEditing = $learnerId && $termId && (!empty($existingScores) || !empty($existingReport));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scores'])) {
    $learnerId = (int)($_POST['learner_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);

    if ($learnerId && $termId) {
        // Delete existing scores for this learner + term
        $pdo->prepare("DELETE FROM scores WHERE learner_id = ? AND term_id = ?")->execute([$learnerId, $termId]);

        $grandTotal = 0;
        $allAggregates = [];

        foreach ($_POST['subject'] ?? [] as $subjectId => $data) {
            $clsScore = (float)($data['class_score'] ?? 0);
            $exmScore = (float)($data['exam_score'] ?? 0);
            $total = $clsScore + $exmScore;
            $grandTotal += $total;

            // Get grade
            $gradeInfo = getGradeRemark($total);
            $allAggregates[] = $gradeInfo['aggregate'];

            $stmt = $pdo->prepare("INSERT INTO scores (learner_id, subject_id, term_id, class_score, exam_score, total_score, grade, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$learnerId, $subjectId, $termId, $clsScore, $exmScore, $total, $gradeInfo['grade'], $gradeInfo['remark']]);
        }

        // Calculate aggregate (4 core + 2 best other)
        $coreAgg = array_slice($allAggregates, 0, 4);
        $otherAgg = array_slice($allAggregates, 4);
        sort($otherAgg);
        $bestOther = array_slice($otherAgg, 0, 2);
        $aggregateNumber = array_sum($coreAgg) + array_sum($bestOther);

// Collect additional fields
        $noOnRoll = (int)($_POST['no_on_roll'] ?? 0);
        $attendancePresent = (int)($_POST['attendance_present'] ?? 0);
        $attendanceTotal = (int)($_POST['attendance_total'] ?? 0);
        $conduct = sanitize($_POST['conduct'] ?? '');
        $interest = sanitize($_POST['interest'] ?? '');
        $promotionStatus = sanitize($_POST['promotion_status'] ?? '');
        $promotionClassId = !empty($_POST['promotion_class_id']) ? (int)$_POST['promotion_class_id'] : null;
        $teacherRemark = sanitize($_POST['teacher_remark'] ?? '');

// Delete existing report submission for this learner + term + teacher, then insert fresh
        $pdo->prepare("DELETE FROM report_submissions WHERE learner_id = ? AND term_id = ? AND teacher_id = ?")
            ->execute([$learnerId, $termId, $teacherId]);

        $stmt = $pdo->prepare("INSERT INTO report_submissions (learner_id, class_id, teacher_id, term_id, grand_total, aggregate_number, no_on_roll, attendance_present, attendance_total, conduct, interest, promotion_status, promotion_class_id, teacher_remark, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())");
        $stmt->execute([$learnerId, $classId, $teacherId, $termId, $grandTotal, $aggregateNumber, $noOnRoll, $attendancePresent, $attendanceTotal, $conduct, $interest, $promotionStatus, $promotionClassId, $teacherRemark]);

        // Auto-calculate class positions whenever scores are saved
        recalculateClassPositions($pdo, $classId, $termId);

        flash('Scores saved successfully.');
        redirect(APP_URL . '/teacher/report_card.php?class_id=' . $classId . '&learner_id=' . $learnerId . '&term_id=' . $termId);
    }
}

// Submit report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $learnerId = (int)($_POST['learner_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);

    $pdo->prepare("UPDATE report_submissions SET status = 'submitted', submitted_at = NOW() WHERE learner_id = ? AND term_id = ? AND teacher_id = ?")
        ->execute([$learnerId, $termId, $teacherId]);

    recalculateClassPositions($pdo, $classId, $termId);

    flash('Report submitted successfully.');
    redirect(APP_URL . '/teacher/submit_report.php');
}

/* ═══════════════════════════════════════════════
   AUTO-CALCULATE CLASS POSITIONS
   ═══════════════════════════════════════════════ */
function recalculateClassPositions(PDO $pdo, int $classId, int $termId): void
{
    if (!$classId || !$termId) return;

    $stmt = $pdo->prepare(
        "SELECT rs.id, rs.learner_id, rs.grand_total, rs.aggregate_number
         FROM report_submissions rs
         JOIN learners l ON rs.learner_id = l.id
         WHERE l.class_id = ? AND rs.term_id = ?
           AND rs.grand_total IS NOT NULL AND rs.grand_total > 0
         ORDER BY rs.grand_total DESC, rs.aggregate_number ASC"
    );
    $stmt->execute([$classId, $termId]);
    $rows = $stmt->fetchAll();

    $rank = 0;
    $lastTotal = -1;
    $atRank = 0;

    foreach ($rows as $i => $row) {
        $total = (float)$row['grand_total'];
        if ($total < $lastTotal) {
            $rank += $atRank;
            $atRank = 1;
        } elseif ($total == $lastTotal) {
            $atRank++;
        } else {
            $rank = $i + 1;
            $atRank = 1;
        }
        $lastTotal = $total;

        $suffix = 'th';
        if ($rank % 10 === 1 && $rank % 100 !== 11) $suffix = 'st';
        elseif ($rank % 10 === 2 && $rank % 100 !== 12) $suffix = 'nd';
        elseif ($rank % 10 === 3 && $rank % 100 !== 13) $suffix = 'rd';
        $position = $rank . $suffix;

        $upd = $pdo->prepare("UPDATE report_submissions SET position = ? WHERE id = ?");
        $upd->execute([$position, $row['id']]);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* GES Report Card Styles */
.ges-report-container {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}
.ges-controls {
    width: 380px;
    min-width: 380px;
    background: #f5f5f5;
    padding: 15px;
    border-radius: 8px;
    border-top: 4px solid var(--primary-color, #008000);
}
.ges-controls h3 {
    margin-top: 0;
    color: var(--primary-color, #008000);
    border-bottom: 2px solid #FFD700;
    padding-bottom: 5px;
}
.ges-controls .form-group { margin-bottom: 8px; }
.ges-controls label { font-size: 12px; font-weight: bold; display: block; margin-bottom: 2px; }
.ges-controls input, .ges-controls select { width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 13px; }
.ges-controls .row { display: flex; gap: 6px; }
.ges-controls .row .form-group { flex: 1; }
.ges-controls .btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }

.ges-preview {
    flex: 1;
    background: #525659;
    padding: 15px;
    border-radius: 8px;
    overflow: auto;
    max-height: 90vh;
}
.ges-a4 {
    width: 210mm;
    min-height: 297mm;
    background: #edf3e8;
    padding: 5mm;
    box-sizing: border-box;
    box-shadow: 0 0 15px rgba(0,0,0,0.2);
    margin: 0 auto;
}
.ges-kente {
    border: 4px solid #008000;
    border-radius: 8px;
    padding: 4px;
    height: 100%;
    box-sizing: border-box;
}
.ges-header { text-align: center; margin-bottom: 4px; }
.ges-header .logo-box { float: left; width: 70px; height: 70px; border: 1px solid #000; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #fff; }
.ges-header .logo-box img { width: 100%; height: 100%; object-fit: cover; }
.ges-header h3.ges-title { margin: 0; font-size: 10pt; font-weight: normal; }
.ges-header h1.school-name { margin: 0; color: #630303; font-size: 13pt; }
.ges-header .address { font-size: 7pt; margin-top: 3px; }
.ges-student-info { display: flex; justify-content: space-between; border: 1px solid #000; padding: 4px; margin-bottom: 4px; font-size: 8pt; }
.ges-student-info .info-fields { width: 78%; }
.ges-student-info .field-row { display: flex; margin-bottom: 1px; }
.ges-student-info .field-label { font-weight: bold; width: 100px; text-align: right; padding-right: 4px; }
.ges-student-info .field-val { flex: 1; border-bottom: 1px dotted #000; padding-left: 4px; }
.ges-photo-box { width: 55px; height: 70px; border: 1px solid #000; display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 7px; color: #888; }
.ges-photo-box img { width: 100%; height: 100%; object-fit: cover; }
.ges-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 4px; }
.ges-table th, .ges-table td { border: 1px solid #000; padding: 2px 3px; text-align: center; }
.ges-table .subject-col { text-align: left; padding-left: 4px; font-weight: bold; }
.ges-table .total-row th { background: #e0e0e0; font-size: 9pt; }
.ges-table .aggregate-row th { background: #d0e0d0; font-size: 10pt; }
.ges-attendance { border: 1px solid #000; padding: 3px; margin-bottom: 4px; font-weight: bold; font-size: 9pt; }
.ges-footer { margin-top: 8px; font-size: 8pt; }
.ges-footer .footer-row { display: flex; margin-bottom: 2px; }
.ges-footer .footer-label { font-weight: bold; width: 210px; }
.ges-footer .footer-line { flex: 1; border-bottom: 1px dotted #000; }
.ges-signatures { display: flex; justify-content: space-around; margin-top: 12px; }
.ges-signatures .sig-block { text-align: center; width: 45%; }
.ges-signatures .sig-line { border-bottom: 1px solid #000; height: 8px; margin-bottom: 2px; }
@media print {
    body { background: white; }
    .navbar, .main-content > .page-header, .ges-controls, .flash-message, .btn { display: none !important; }
    .ges-preview { background: white; padding: 0; overflow: visible; max-height: none; }
    .ges-a4 { box-shadow: none; margin: 0; width: 100%; height: 100%; }
    @page { size: A4 portrait; margin: 0.5cm; }
}
</style>

<div class="page-header">
    <h1><?= $isEditing ? 'Edit Report Card' : 'Report Card Entry' ?></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/teacher/submit_report.php" class="btn btn-dark">Submitted Reports</a>
        <a href="<?= APP_URL ?>/teacher/broadsheet.php?class_id=<?= $classId ?>&term_id=<?= $termId ?>" class="btn btn-gold">Class Broadsheet</a>
    </div>
</div>

<div class="ges-report-container">
    <!-- Left Controls -->
    <div class="ges-controls">
        <h3>STUDENT SELECTION</h3>
        <form method="GET" id="selectForm">
            <div class="form-group">
                <label>Class</label>
                <select name="class_id" onchange="document.getElementById('selectForm').submit()">
                    <option value="">Select Class...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Term</label>
                <select name="term_id" onchange="document.getElementById('selectForm').submit()">
                    <?php foreach ($terms as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $termId == $t['id'] ? 'selected' : '' ?>><?= sanitize($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($classId): ?>
            <div class="form-group">
                <label>Learner</label>
                <select name="learner_id" onchange="document.getElementById('selectForm').submit()">
                    <option value="">Select Learner...</option>
                    <?php foreach ($learners as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $learnerId == $l['id'] ? 'selected' : '' ?>><?= sanitize($l['full_name']) ?> (<?= sanitize($l['admission_no']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>

        <?php if ($learner): ?>
        <hr>
        <h3>SCORES (50% / 50%) <?php if ($isEditing): ?><span class="badge badge-primary" style="font-size:10px;float:right;margin-top:3px;">EDITING</span><?php endif; ?></h3>
        <form method="POST" id="scoreForm">
            <input type="hidden" name="learner_id" value="<?= $learnerId ?>">
            <input type="hidden" name="term_id" value="<?= $termId ?>">
            <input type="hidden" name="class_id" value="<?= $classId ?>">

            <?php foreach ($subjects as $subj):
                $clsVal = $existingScores[$subj['id']]['class_score'] ?? '';
                $exmVal = $existingScores[$subj['id']]['exam_score'] ?? '';
            ?>
            <div class="form-group">
                <label><?= sanitize($subj['subject_name']) ?></label>
                <div class="row">
                    <input type="number" name="subject[<?= $subj['id'] ?>][class_score]" class="cls-score" placeholder="Class (50%)" min="0" max="50" value="<?= sanitize($clsVal) ?>" oninput="calcRow(this)">
                    <input type="number" name="subject[<?= $subj['id'] ?>][exam_score]" class="exm-score" placeholder="Exam (50%)" min="0" max="50" value="<?= sanitize($exmVal) ?>" oninput="calcRow(this)">
                </div>
            </div>
            <?php endforeach; ?>

            <hr>
            <h3>ADDITIONAL INFO</h3>
            <div class="form-group">
                <label>No. on Roll</label>
                <input type="number" name="no_on_roll" min="1" max="100" value="<?= sanitize($existingReport['no_on_roll'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Attendance Present</label>
                <input type="number" name="attendance_present" min="0" max="100" value="<?= sanitize($existingReport['attendance_present'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Total School Days</label>
                <input type="number" name="attendance_total" min="0" max="100" value="<?= sanitize($existingReport['attendance_total'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Learner's Conduct</label>
                <select name="conduct">
                    <option value="">Select...</option>
                    <option value="Present" <?= ($existingReport['conduct'] ?? '') === 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Well Behaved" <?= ($existingReport['conduct'] ?? '') === 'Well Behaved' ? 'selected' : '' ?>>Well Behaved</option>
                    <option value="Respectful" <?= ($existingReport['conduct'] ?? '') === 'Respectful' ? 'selected' : '' ?>>Respectful</option>
                    <option value="Calm" <?= ($existingReport['conduct'] ?? '') === 'Calm' ? 'selected' : '' ?>>Calm</option>
                    <option value="Approachable" <?= ($existingReport['conduct'] ?? '') === 'Approachable' ? 'selected' : '' ?>>Approachable</option>
                    <option value="Truant" <?= ($existingReport['conduct'] ?? '') === 'Truant' ? 'selected' : '' ?>>Truant</option>
                    <option value="Humble" <?= ($existingReport['conduct'] ?? '') === 'Humble' ? 'selected' : '' ?>>Humble</option>
                    <option value="Obedient" <?= ($existingReport['conduct'] ?? '') === 'Obedient' ? 'selected' : '' ?>>Obedient</option>
                    <option value="Helpful" <?= ($existingReport['conduct'] ?? '') === 'Helpful' ? 'selected' : '' ?>>Helpful</option>
                    <option value="Quiet" <?= ($existingReport['conduct'] ?? '') === 'Quiet' ? 'selected' : '' ?>>Quiet</option>
                    <option value="Confident" <?= ($existingReport['conduct'] ?? '') === 'Confident' ? 'selected' : '' ?>>Confident</option>
                    <option value="Sociable" <?= ($existingReport['conduct'] ?? '') === 'Sociable' ? 'selected' : '' ?>>Sociable</option>
                </select>
            </div>
            <div class="form-group">
                <label>Learner's Interest</label>
                <select name="interest">
                    <option value="">Select...</option>
                    <option value="Sports" <?= ($existingReport['interest'] ?? '') === 'Sports' ? 'selected' : '' ?>>Sports</option>
                    <option value="Reading" <?= ($existingReport['interest'] ?? '') === 'Reading' ? 'selected' : '' ?>>Reading</option>
                    <option value="Arts Works" <?= ($existingReport['interest'] ?? '') === 'Arts Works' ? 'selected' : '' ?>>Arts & Works</option>
                    <option value="Music and Dance" <?= ($existingReport['interest'] ?? '') === 'Music and Dance' ? 'selected' : '' ?>>Music and Dance</option>
                    <option value="Writing" <?= ($existingReport['interest'] ?? '') === 'Writing' ? 'selected' : '' ?>>Writing</option>
                    <option value="Drawing" <?= ($existingReport['interest'] ?? '') === 'Drawing' ? 'selected' : '' ?>>Drawing</option>
                    <option value="Singing" <?= ($existingReport['interest'] ?? '') === 'Singing' ? 'selected' : '' ?>>Singing</option>
                    <option value="Drama" <?= ($existingReport['interest'] ?? '') === 'Drama' ? 'selected' : '' ?>>Drama</option>
                    <option value="Storytelling" <?= ($existingReport['interest'] ?? '') === 'Storytelling' ? 'selected' : '' ?>>Storytelling</option>
                    <option value="Games" <?= ($existingReport['interest'] ?? '') === 'Games' ? 'selected' : '' ?>>Games</option>
                </select>
            </div>
            <div class="form-group">
                <label>Promoted To / Repeat</label>
                <select name="promotion_status" id="promotionStatus" onchange="togglePromotionClass()">
                    <option value="">Select...</option>
                    <option value="Promoted" <?= ($existingReport['promotion_status'] ?? '') === 'Promoted' ? 'selected' : '' ?>>Promoted</option>
                    <option value="Repeated" <?= ($existingReport['promotion_status'] ?? '') === 'Repeated' ? 'selected' : '' ?>>Repeated</option>
                </select>
            </div>
            <div class="form-group" id="promotionClassGroup" style="display:none;">
                <label>Select Promotion Class</label>
                <select name="promotion_class_id" id="promotionClassId">
                    <option value="">Select Class...</option>
                    <?php foreach ($allClasses as $pc): ?>
                        <option value="<?= $pc['id'] ?>" <?= ($existingReport['promotion_class_id'] ?? '') == $pc['id'] ? 'selected' : '' ?>><?= sanitize($pc['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="repeatedClassGroup" style="display:none;">
                <label>Repeating Class</label>
                <input type="text" id="repeatedClassName" readonly style="background:#eee;" value="<?= sanitize($className) ?>">
                <input type="hidden" name="promotion_class_id" id="repeatedClassIdHidden" value="">
            </div>
            <div class="form-group">
                <label>Class Teacher's Remark</label>
                <textarea name="teacher_remark" rows="2" maxlength="200"><?= sanitize($existingReport['teacher_remark'] ?? '') ?></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" name="save_scores" class="btn btn-primary"><?= $isEditing ? 'Update Scores' : 'Save Scores' ?></button>
                <button type="submit" name="submit_report" class="btn btn-success" onclick="return confirm('Submit this report? It can still be edited later.')">Submit Report</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Right Preview -->
    <div class="ges-preview" id="reportPreview">
        <div class="ges-a4">
            <div class="ges-kente">
                <div class="ges-header">
                    <div class="logo-box">
<img id="previewLogo" src="<?= APP_URL ?>/assets/logos/logo.jpg" alt="Logo" onerror="this.style.display='none'">
                    </div>
                    <h3 class="ges-title">GHANA EDUCATION SERVICE</h3>
                    <h1 class="school-name"><?= sanitize($settings['school_name'] ?? 'ESSIENIMPONG M/A PRIMARY AND KG SCHOOL') ?></h1>
                    <div class="address">
                        <?= sanitize($settings['address'] ?? 'Post Office Box 30, Essienimpong, Ejisu-Ashanti') ?><br>
                        <strong>GPS:</strong> <?= sanitize($settings['gps_code'] ?? 'AE-0847-9957') ?> | <strong>Tel:</strong> <?= sanitize($settings['phone'] ?? '+233243819559') ?>
                    </div>
                </div>

                <div class="ges-student-info">
                    <div class="info-fields">
                        <?php
                        // Get the selected term's dates
                        $selectedTerm = null;
                        foreach ($terms as $t) {
                            if ($t['id'] == $termId) {
                                $selectedTerm = $t;
                                break;
                            }
                        }
                        $termEnds = $selectedTerm && !empty($selectedTerm['term_ends']) ? date('d/m/Y', strtotime($selectedTerm['term_ends'])) : '-';
                        $nextTermBegins = $selectedTerm && !empty($selectedTerm['next_term_begins']) ? date('d/m/Y', strtotime($selectedTerm['next_term_begins'])) : '-';
                        ?>
                        <div class="field-row"><div class="field-label">Name:</div><div class="field-val" id="previewName"><?= sanitize($learner['full_name'] ?? '') ?></div></div>
                        <div class="field-row"><div class="field-label">Age:</div><div class="field-val" id="previewAge"><?= sanitize($learner['age'] ?? '') ?></div></div>
                        <div class="field-row"><div class="field-label">Class:</div><div class="field-val" id="previewClass"><?= sanitize($className) ?></div></div>
                        <div class="field-row"><div class="field-label">No. on Roll:</div><div class="field-val" id="previewRoll"><?= sanitize($existingReport['no_on_roll'] ?? '') ?></div></div>
                        <div class="field-row"><div class="field-label">Teacher:</div><div class="field-val" id="previewTeacher"><?= sanitize($teacherRow['full_name'] ?? '') ?></div></div>
                        <div class="field-row"><div class="field-label">Term:</div><div class="field-val" id="previewTerm"><?= sanitize($selectedTerm['term_name'] ?? '') ?></div></div>
                        <div class="field-row"><div class="field-label">Academic Year:</div><div class="field-val" id="previewYear"><?= sanitize($settings['academic_year'] ?? '2025/2026') ?></div></div>
                        <div class="field-row"><div class="field-label">Position:</div><div class="field-val" id="previewPos"><?= sanitize($existingReport['position'] ?? '') ?></div></div>
                        <div class="field-row"><div class="field-label">Term Ends:</div><div class="field-val" id="previewEnd"><?= $termEnds ?></div></div>
                        <div class="field-row"><div class="field-label">Next Term Begins:</div><div class="field-val" id="previewNext"><?= $nextTermBegins ?></div></div>
                    </div>
                    <div class="ges-photo-box" id="previewPhotoContainer">
                        <?php if (!empty($learner['passport_photo'])): ?>
                            <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($learner['passport_photo']) ?>" alt="Photo">
                        <?php else: ?>
                            <span>Passport<br>Photo</span>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="ges-table">
                    <thead>
                        <tr>
                            <th width="35%">SUBJECTS</th>
                            <th width="10%">Class (50%)</th>
                            <th width="10%">Exam (50%)</th>
                            <th width="10%">Total (100%)</th>
                            <th width="10%">Grade</th>
                            <th width="15%">Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="previewScoreBody">
                        <?php
                        $grandTotal = 0;
                        $allAggregates = [];
                        if ($learnerId && $termId):
                            foreach ($subjects as $subj):
                                $sc = $existingScores[$subj['id']] ?? null;
                                $total = $sc ? ($sc['class_score'] + $sc['exam_score']) : 0;
                                $gradeInfo = getGradeRemark($total);
                                $grandTotal += $total;
                                $allAggregates[] = $gradeInfo['aggregate'];
                        ?>
                        <tr>
                            <td class="subject-col"><?= sanitize($subj['subject_name']) ?></td>
                            <td><?= $sc ? (float)$sc['class_score'] : '' ?></td>
                            <td><?= $sc ? (float)$sc['exam_score'] : '' ?></td>
                            <td style="font-weight:bold;"><?= $total ?: '' ?></td>
                            <td style="font-weight:bold;"><?= $total ? $gradeInfo['grade'] : '' ?></td>
                            <td><?= $total ? $gradeInfo['remark'] : '' ?></td>
                        </tr>
                        <?php
                            endforeach;
                        endif;
                        // Calculate aggregate
                        $coreAgg = array_slice($allAggregates, 0, 4);
                        $otherAgg = array_slice($allAggregates, 4);
                        sort($otherAgg);
                        $bestOther = array_slice($otherAgg, 0, 2);
                        $aggregateNumber = array_sum($coreAgg) + array_sum($bestOther);
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <th class="subject-col">TOTAL SCORE:</th>
                            <th>-</th><th>-</th>
                            <th id="previewGrandTotal"><?= $grandTotal ?: 0 ?></th>
                            <th>-</th><th>-</th>
                        </tr>
                        <tr class="aggregate-row">
                            <th class="subject-col">AGGREGATE NUMBER: (4 Core + 2 Best Other)</th>
                            <th>-</th><th>-</th>
                            <th id="previewAggregate"><?= $aggregateNumber ?: 0 ?></th>
                            <th>-</th><th>-</th>
                        </tr>
                    </tfoot>
                </table>

<div class="ges-attendance">
                    Attendance: <span id="previewPresent"><?= sanitize($existingReport['attendance_present'] ?? '_') ?></span> Present out of <span id="previewTotalDays"><?= sanitize($existingReport['attendance_total'] ?? '_') ?></span> Days
                </div>

                <div class="ges-footer">
                    <div class="footer-row"><div class="footer-label">Learner's Conduct:</div><div class="footer-line" id="previewConduct"><?= sanitize($existingReport['conduct'] ?? '') ?></div></div>
                    <div style="height:4px;"></div>
                    <div class="footer-row"><div class="footer-label">Learner's Interest:</div><div class="footer-line" id="previewInterest"><?= sanitize($existingReport['interest'] ?? '') ?></div></div>
                    <div style="height:6px;"></div>
                    <div class="footer-row"><div class="footer-label">Promoted To:</div><div class="footer-line" id="previewPromoted"><?php
                        $promoText = '';
                        if (!empty($existingReport['promotion_status'])) {
                            if ($existingReport['promotion_status'] === 'Promoted' && !empty($existingReport['promotion_class_id'])) {
                                // Look up class name
                                $pcStmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
                                $pcStmt->execute([$existingReport['promotion_class_id']]);
                                $pcName = $pcStmt->fetchColumn();
                                $promoText = 'Promoted to ' . ($pcName ?: 'Class ' . $existingReport['promotion_class_id']);
                            } elseif ($existingReport['promotion_status'] === 'Repeated') {
                                $promoText = 'Repeated ' . $className;
                            } else {
                                $promoText = $existingReport['promotion_status'];
                            }
                        }
                        echo sanitize($promoText);
                    ?></div></div>
                    <div style="height:6px;"></div>
                    <div class="footer-row"><div class="footer-label">Headteacher's Name:</div><div class="footer-line" id="previewHeadteacher"><?= sanitize($settings['headteacher_name'] ?? 'MR. EDWARD KWAME ADJEI') ?></div></div>
                    <div style="height:4px;"></div>
                    <div class="footer-row"><div class="footer-label">Class Teacher's Tel. No:</div><div class="footer-line" id="previewTel"><?= sanitize($teacherRow['phone'] ?? '') ?></div></div>
                    <div style="height:4px;"></div>
                    <div class="footer-row"><div class="footer-label">Class Teacher's Remark:</div><div class="footer-line" id="previewRemark"><?= sanitize($existingReport['teacher_remark'] ?? '') ?></div></div>
                    <div style="height:8px;"></div>
                    <div class="ges-signatures">
                        <div class="sig-block"><div class="sig-line"></div><span>Class Teacher's Signature:</span></div>
                        <div class="sig-block"><div class="sig-line"></div><span>Head Teacher's Signature:</span></div>
                    </div>
                    <div style="text-align:center; margin-top:8px; font-size:7pt; font-style:italic;">
                        System Generated Report - <?= sanitize($settings['school_name'] ?? 'Essienimpong M/A Primary SCHOOL') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview update for scores
function calcRow(input) {
    const row = input.closest('.form-group');
    const clsInp = row.querySelector('.cls-score');
    const exmInp = row.querySelector('.exm-score');
    let cls = parseFloat(clsInp.value) || 0;
    let exm = parseFloat(exmInp.value) || 0;
    cls = Math.max(0, Math.min(50, cls));
    exm = Math.max(0, Math.min(50, exm));
    clsInp.value = cls || '';
    exmInp.value = exm || '';
    // We'll recalculate everything via PHP reload on save.
    // For visual, just trigger print preview update
}

// Print function
function printReportCard() {
    window.print();
}

// Toggle promotion class dropdown
function togglePromotionClass() {
    const status = document.getElementById('promotionStatus').value;
    const promoGroup = document.getElementById('promotionClassGroup');
    const repeatGroup = document.getElementById('repeatedClassGroup');
    if (status === 'Promoted') {
        promoGroup.style.display = 'block';
        repeatGroup.style.display = 'none';
    } else if (status === 'Repeated') {
        promoGroup.style.display = 'none';
        repeatGroup.style.display = 'block';
    } else {
        promoGroup.style.display = 'none';
        repeatGroup.style.display = 'none';
    }
}
// Run on page load
document.addEventListener('DOMContentLoaded', togglePromotionClass);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
