<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('teacher');
$pageTitle = 'Teacher Dashboard';
$pdo = getDB();
$teacherId = $_SESSION['teacher_id'] ?? 0;

// Get assigned classes
$stmt = $pdo->prepare("SELECT c.id, c.class_name,
    (SELECT COUNT(*) FROM learners l WHERE l.class_id = c.id AND l.is_active = 1) as learner_count
    FROM class_teachers ct
    JOIN classes c ON ct.class_id = c.id
    WHERE ct.teacher_id = ?
    ORDER BY c.id");
$stmt->execute([$teacherId]);
$myClasses = $stmt->fetchAll();

$totalLearners = 0;
foreach ($myClasses as $c) $totalLearners += $c['learner_count'];

$pendingDrafts = $pdo->prepare("SELECT COUNT(*) FROM report_submissions WHERE teacher_id = ? AND status = 'draft'");
$pendingDrafts->execute([$teacherId]);
$draftCount = $pendingDrafts->fetchColumn();

$submitted = $pdo->prepare("SELECT COUNT(*) FROM report_submissions WHERE teacher_id = ? AND status IN ('submitted','approved','printed')");
$submitted->execute([$teacherId]);
$submittedCount = $submitted->fetchColumn();

// Get terms for broadsheet selector
$terms = $pdo->query("SELECT id, term_name FROM terms ORDER BY id")->fetchAll();

// Get selected class for broadsheet preview (default to first class)
$selectedClassId = (int)($_GET['broadsheet_class_id'] ?? $myClasses[0]['id'] ?? 0);
$selectedTermId = (int)($_GET['broadsheet_term_id'] ?? 0);
if (!$selectedTermId) {
    foreach ($terms as $t) {
        if ($t['is_current'] ?? false) { $selectedTermId = $t['id']; break; }
    }
    if (!$selectedTermId && !empty($terms)) $selectedTermId = $terms[0]['id'];
}

$broadsheetData = [];
if ($selectedClassId && $selectedTermId) {
    $bsStmt = $pdo->prepare(
        "SELECT l.id, l.full_name, l.admission_no, rs.grand_total, rs.position, rs.status, rs.aggregate_number
         FROM learners l
         LEFT JOIN report_submissions rs ON rs.learner_id = l.id AND rs.term_id = ? AND rs.teacher_id = ?
         WHERE l.class_id = ? AND l.is_active = 1
         ORDER BY l.full_name"
    );
    $bsStmt->execute([$selectedTermId, $teacherId, $selectedClassId]);
    $broadsheetData = $bsStmt->fetchAll();

    // Get class average
    $gtVals = array_filter(array_column($broadsheetData, 'grand_total'), function($v) { return $v !== null && $v > 0; });
    $classAvg = !empty($gtVals) ? round(array_sum($gtVals) / count($gtVals), 1) : 0;

    // Get counts
    $bsSubmitted = 0;
    $bsDraft = 0;
    $bsNoReport = 0;
    $aboveAvg = 0; $atAvg = 0; $belowAvg = 0;
    foreach ($gtVals as $gt) {
        if ($gt > $classAvg) $aboveAvg++;
        elseif ($gt == $classAvg) $atAvg++;
        else $belowAvg++;
    }
    foreach ($broadsheetData as $b) {
        if (empty($b['status'])) $bsNoReport++;
        elseif ($b['status'] === 'draft') $bsDraft++;
        else $bsSubmitted++;
    }

    // Get class name
    $cNameStmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $cNameStmt->execute([$selectedClassId]);
    $selectedClassName = $cNameStmt->fetchColumn();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-bg" style="background-image:url('<?= APP_URL ?>/assets/images/admin_dashboard.jpg');"></div>

<div class="page-header">
    <h1>Teacher Dashboard</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/teacher/report_card.php" class="btn btn-primary">+ New Report Card</a>
        <a href="<?= APP_URL ?>/teacher/submit_report.php" class="btn btn-dark">View Submitted Reports</a>
    </div>
</div>

<div class="quick-actions-grid">
    <a href="<?= APP_URL ?>/teacher/report_card.php" class="quick-action-card">
        <div class="qa-icon">📝</div>
        <div class="qa-title">Enter Scores</div>
        <div class="qa-desc">Create or edit report card scores for learners</div>
    </a>
    <a href="<?= APP_URL ?>/teacher/broadsheet.php" class="quick-action-card">
        <div class="qa-icon">📊</div>
        <div class="qa-title">Class Broadsheet</div>
        <div class="qa-desc">View class performance summary &amp; submit all reports</div>
    </a>
    <a href="<?= APP_URL ?>/teacher/submit_report.php" class="quick-action-card">
        <div class="qa-icon">📋</div>
        <div class="qa-title">My Submissions</div>
        <div class="qa-desc">Track all submitted, approved &amp; printed reports</div>
    </a>
    <a href="<?= APP_URL ?>/teacher/my_class.php" class="quick-action-card">
        <div class="qa-icon">👥</div>
        <div class="qa-title">My Learners</div>
        <div class="qa-desc">View learner profiles in your assigned classes</div>
    </a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <img src="<?= APP_URL ?>/assets/images/classes.jpg" alt="Assigned Classes" class="stat-card-img">
        <div class="stat-number"><?= count($myClasses) ?></div>
        <div class="stat-label">Assigned Classes</div>
    </div>
    <div class="stat-card gold">
        <img src="<?= APP_URL ?>/assets/images/active_learners.jpg" alt="Total Learners" class="stat-card-img">
        <div class="stat-number"><?= $totalLearners ?></div>
        <div class="stat-label">Total Learners</div>
    </div>
    <div class="stat-card purple">
        <img src="<?= APP_URL ?>/assets/images/draft_report.jpg" alt="Draft Reports" class="stat-card-img">
        <div class="stat-number"><?= $draftCount ?></div>
        <div class="stat-label">Draft Reports</div>
    </div>
    <div class="stat-card red">
        <img src="<?= APP_URL ?>/assets/images/pending_reports.jpg" alt="Submitted Reports" class="stat-card-img">
        <div class="stat-number"><?= $submittedCount ?></div>
        <div class="stat-label">Submitted Reports</div>
    </div>
</div>

<div class="card">
    <h3>My Assigned Classes</h3>
    <?php if (empty($myClasses)): ?>
        <p style="color:#888;">You have not been assigned to any class yet. Please contact the administrator.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Class</th><th>Learners</th><th>Drafts</th><th>Submitted</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($myClasses as $c): 
                    $cDraft = $pdo->prepare("SELECT COUNT(*) FROM report_submissions rs JOIN learners l ON rs.learner_id = l.id WHERE l.class_id = ? AND rs.teacher_id = ? AND rs.status = 'draft'");
                    $cDraft->execute([$c['id'], $teacherId]);
                    $cDraftCount = $cDraft->fetchColumn();
                    $cSub = $pdo->prepare("SELECT COUNT(*) FROM report_submissions rs JOIN learners l ON rs.learner_id = l.id WHERE l.class_id = ? AND rs.teacher_id = ? AND rs.status IN ('submitted','approved','printed')");
                    $cSub->execute([$c['id'], $teacherId]);
                    $cSubCount = $cSub->fetchColumn();
                ?>
                    <tr>
                        <td><strong><?= sanitize($c['class_name']) ?></strong></td>
                        <td><?= $c['learner_count'] ?></td>
                        <td><span class="badge badge-warning"><?= $cDraftCount ?></span></td>
                        <td><span class="badge badge-info"><?= $cSubCount ?></span></td>
                        <td>
                            <a href="<?= APP_URL ?>/teacher/report_card.php?class_id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Enter Scores</a>
                            <a href="<?= APP_URL ?>/teacher/my_class.php?class_id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Learners</a>
                            <a href="<?= APP_URL ?>/teacher/broadsheet.php?class_id=<?= $c['id'] ?>&term_id=<?= $selectedTermId ?>" class="btn btn-sm btn-gold">Broadsheet</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint" style="margin-top:10px;">If multiple teachers are assigned to the same class, you all share the same class dashboard and learner list.</p>
    <?php endif; ?>
</div>

<?php if (!empty($myClasses)): ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:15px;">
        <h3 style="margin:0;border:none;padding:0;">Class Performance Summary</h3>
        <a href="<?= APP_URL ?>/teacher/broadsheet.php?class_id=<?= $selectedClassId ?>&term_id=<?= $selectedTermId ?>" class="btn btn-sm btn-gold">View Full Broadsheet</a>
    </div>
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:15px;padding:12px;background:#f9f9f9;border-radius:6px;border:1px solid #eee;">
        <div class="form-group" style="margin-bottom:0;min-width:160px;">
            <label style="font-size:12px;font-weight:bold;">Class</label>
            <select name="broadsheet_class_id" onchange="this.form.submit()" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;">
                <?php foreach ($myClasses as $mc): ?>
                    <option value="<?= $mc['id'] ?>" <?= $selectedClassId == $mc['id'] ? 'selected' : '' ?>><?= sanitize($mc['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;min-width:160px;">
            <label style="font-size:12px;font-weight:bold;">Term</label>
            <select name="broadsheet_term_id" onchange="this.form.submit()" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;">
                <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTermId == $t['id'] ? 'selected' : '' ?>><?= sanitize($t['term_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (!empty($broadsheetData)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:15px;">
            <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:8px;border-left:4px solid #008000;">
                <div style="font-size:1.5rem;font-weight:bold;color:#008000;"><?= count($broadsheetData) ?></div>
                <div style="font-size:0.75rem;color:#555;margin-top:2px;">Total Learners</div>
            </div>
            <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#e3f2fd,#bbdefb);border-radius:8px;border-left:4px solid #021a41;">
                <div style="font-size:1.5rem;font-weight:bold;color:#021a41;"><?= $classAvg ?: '-' ?></div>
                <div style="font-size:0.75rem;color:#555;margin-top:2px;">Class Average</div>
            </div>
            <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#d4edda,#c3e6cb);border-radius:8px;border-left:4px solid #28a745;">
                <div style="font-size:1.5rem;font-weight:bold;color:#28a745;"><?= $aboveAvg ?></div>
                <div style="font-size:0.75rem;color:#555;margin-top:2px;">Above Avg</div>
            </div>
            <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#fff3cd,#ffeeba);border-radius:8px;border-left:4px solid #856404;">
                <div style="font-size:1.5rem;font-weight:bold;color:#856404;"><?= $atAvg ?></div>
                <div style="font-size:0.75rem;color:#555;margin-top:2px;">At Avg</div>
            </div>
            <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#f8d7da,#f5c6cb);border-radius:8px;border-left:4px solid #dc3545;">
                <div style="font-size:1.5rem;font-weight:bold;color:#dc3545;"><?= $belowAvg ?></div>
                <div style="font-size:0.75rem;color:#555;margin-top:2px;">Below Avg</div>
            </div>
            <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#e8eaf6,#c5cae9);border-radius:8px;border-left:4px solid #3f51b5;">
                <div style="font-size:1.5rem;font-weight:bold;color:#3f51b5;"><?= $bsSubmitted ?>/<?= count($broadsheetData) ?></div>
                <div style="font-size:0.75rem;color:#555;margin-top:2px;">Submitted</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" style="font-size:0.85rem;">
                <thead>
                    <tr><th>#</th><th>Learner</th><th>Total Score</th><th>Position</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($broadsheetData as $i => $b): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= sanitize($b['full_name']) ?></td>
                        <td><strong><?= $b['grand_total'] ? (float)$b['grand_total'] : '-' ?></strong></td>
                        <td><?= sanitize($b['position'] ?? '-') ?></td>
                        <td>
                            <?php if (empty($b['status'])): ?>
                                <span class="badge badge-danger">No Report</span>
                            <?php elseif ($b['status'] === 'draft'): ?>
                                <span class="badge badge-warning">Draft</span>
                            <?php elseif ($b['status'] === 'submitted'): ?>
                                <span class="badge badge-info">Submitted</span>
                            <?php elseif ($b['status'] === 'approved'): ?>
                                <span class="badge badge-success">Approved</span>
                            <?php else: ?>
                                <span class="badge"><?= ucfirst($b['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= APP_URL ?>/teacher/report_card.php?class_id=<?= $selectedClassId ?>&learner_id=<?= $b['id'] ?>&term_id=<?= $selectedTermId ?>" class="btn btn-sm btn-primary">Edit Scores</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= APP_URL ?>/teacher/broadsheet.php?class_id=<?= $selectedClassId ?>&term_id=<?= $selectedTermId ?>" class="btn btn-primary btn-sm">View Full Broadsheet &amp; Submit All</a>
            <a href="<?= APP_URL ?>/teacher/report_card.php?class_id=<?= $selectedClassId ?>&term_id=<?= $selectedTermId ?>" class="btn btn-dark btn-sm">Enter Scores</a>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:30px;background:#f9f9f9;border-radius:8px;border:1px dashed #ccc;">
            <p style="font-size:1.1rem;color:#888;margin-bottom:10px;">No learners or reports found for this class/term.</p>
            <a href="<?= APP_URL ?>/teacher/report_card.php?class_id=<?= $selectedClassId ?>&term_id=<?= $selectedTermId ?>" class="btn btn-primary">Start Entering Scores</a>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
