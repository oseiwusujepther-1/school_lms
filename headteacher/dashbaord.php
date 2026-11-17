<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('headteacher');
$pageTitle = 'Headteacher Dashboard';
$pdo = getDB();

$totalLearners = $pdo->query("SELECT COUNT(*) FROM learners WHERE is_active = 1")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_approved = 1")->fetchColumn();

// Pending reports count (submitted by teachers, awaiting approval)
$pendingReports = $pdo->query("SELECT COUNT(*) FROM report_submissions WHERE status = 'submitted'")->fetchColumn();
$approvedReports = $pdo->query("SELECT COUNT(*) FROM report_submissions WHERE status = 'approved'")->fetchColumn();
$draftReports = $pdo->query("SELECT COUNT(*) FROM report_submissions WHERE status = 'draft'")->fetchColumn();

// Get recent submitted reports
$recentReports = $pdo->query("SELECT rs.*, l.full_name as learner_name, l.admission_no, c.class_name, t.full_name as teacher_name, tm.term_name
    FROM report_submissions rs
    JOIN learners l ON rs.learner_id = l.id
    JOIN classes c ON rs.class_id = c.id
    JOIN teachers t ON rs.teacher_id = t.id
    JOIN terms tm ON rs.term_id = tm.id
    WHERE rs.status = 'submitted'
    ORDER BY rs.submitted_at DESC
    LIMIT 10")->fetchAll();

// Learner counts per class (total, boys, girls)
$learnersPerClass = $pdo->query("
    SELECT c.class_name,
           COUNT(l.id) AS total,
           SUM(CASE WHEN l.sex = 'Male' THEN 1 ELSE 0 END) AS boys,
           SUM(CASE WHEN l.sex = 'Female' THEN 1 ELSE 0 END) AS girls
    FROM classes c
    LEFT JOIN learners l ON l.class_id = c.id AND l.is_active = 1
    GROUP BY c.id, c.class_name
    ORDER BY c.id
")->fetchAll();

// === Weekly Enrollment Tracking ===
// Handle form submission to save weekly enrollment data
$enrollMsg = '';
$enrollMsgType = '';
$classesList = $pdo->query("SELECT id, class_name FROM classes ORDER BY id")->fetchAll();
$currentTerm = $pdo->query("SELECT term_name, academic_year FROM terms WHERE is_current = 1 ORDER BY id LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_weekly'])) {
    $term_name = trim($_POST['term_name'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    $week_date = trim($_POST['week_date'] ?? '');
    $week_no = (int)($_POST['week_no'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $active_boys = (int)($_POST['active_boys'] ?? 0);
    $active_girls = (int)($_POST['active_girls'] ?? 0);
    $dropout_boys = (int)($_POST['dropout_boys'] ?? 0);
    $dropout_girls = (int)($_POST['dropout_girls'] ?? 0);

    if ($term_name !== '' && $academic_year !== '' && $week_date !== '' && $week_no > 0 && $class_id > 0) {
        try {
            // Check if a record already exists for this term/year/date/week/class
            $check = $pdo->prepare("SELECT id FROM weekly_enrollment WHERE term_name=? AND academic_year=? AND week_date=? AND week_no=? AND class_id=?");
            $check->execute([$term_name, $academic_year, $week_date, $week_no, $class_id]);
            if ($check->fetch()) {
                // Update existing
                $upd = $pdo->prepare("UPDATE weekly_enrollment SET active_boys=?, active_girls=?, dropout_boys=?, dropout_girls=? WHERE term_name=? AND academic_year=? AND week_date=? AND week_no=? AND class_id=?");
                $upd->execute([$active_boys, $active_girls, $dropout_boys, $dropout_girls, $term_name, $academic_year, $week_date, $week_no, $class_id]);
                $enrollMsg = 'Weekly enrollment updated.';
            } else {
                $ins = $pdo->prepare("INSERT INTO weekly_enrollment (term_name, academic_year, week_date, week_no, class_id, active_boys, active_girls, dropout_boys, dropout_girls) VALUES (?,?,?,?,?,?,?,?,?)");
                $ins->execute([$term_name, $academic_year, $week_date, $week_no, $class_id, $active_boys, $active_girls, $dropout_boys, $dropout_girls]);
                $enrollMsg = 'Weekly enrollment saved.';
            }
            $enrollMsgType = 'success';
        } catch (Exception $e) {
            $enrollMsg = 'Error saving: ' . $e->getMessage();
            $enrollMsgType = 'error';
        }
    } else {
        $enrollMsg = 'Please fill all required fields (term, year, date, week no, class).';
        $enrollMsgType = 'error';
    }
}

// Delete action
if (isset($_GET['del_week'])) {
    $delId = (int)$_GET['del_week'];
    $pdo->prepare("DELETE FROM weekly_enrollment WHERE id = ?")->execute([$delId]);
    $enrollMsg = 'Weekly enrollment deleted.';
    $enrollMsgType = 'success';
}

// Fetch weekly enrollment records (joined with classes)
$weeklyRecords = $pdo->query("
    SELECT we.*, c.class_name
    FROM weekly_enrollment we
    JOIN classes c ON we.class_id = c.id
    ORDER BY we.week_date DESC, we.week_no DESC
    LIMIT 100
")->fetchAll();

// === Staff Daily Attendance Tracking (Teachers + Headteacher) ===
$attMsg = '';
$attMsgType = '';

if (!$currentTerm) {
    $currentTerm = $pdo->query("SELECT term_name, academic_year FROM terms ORDER BY id LIMIT 1")->fetch();
}
$attTerm = trim($_POST['att_term'] ?? $_GET['term'] ?? ($currentTerm['term_name'] ?? 'Term 1'));
$attYear = trim($_POST['att_year'] ?? $_GET['year'] ?? ($currentTerm['academic_year'] ?? '2025/2026'));
$weekRef = trim($_POST['week_ref'] ?? $_GET['week_ref'] ?? date('Y-m-d'));
$weekNo = (int)($_POST['att_week_no'] ?? $_GET['week_no'] ?? 1);
if ($weekNo < 1) $weekNo = 1;
$weekRefTs = strtotime($weekRef);
$dow = (int)date('N', $weekRefTs);
$mondayTs = strtotime('-' . ($dow - 1) . ' days', $weekRefTs);
$monday = date('Y-m-d', $mondayTs);
$weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$weekDates = [];
foreach ($weekDays as $i => $day) {
    $weekDates[$day] = date('Y-m-d', strtotime("+$i days", $mondayTs));
}

// Staff roster: headteacher (users) + approved teachers
$staffList = [];
$hts = $pdo->query("SELECT id, full_name FROM users WHERE role='headteacher' AND is_active=1 ORDER BY full_name")->fetchAll();
foreach ($hts as $h) {
    $staffList[] = ['staff_id' => 'HT-' . str_pad($h['id'], 3, '0', STR_PAD_LEFT), 'staff_name' => $h['full_name']];
}
$ts = $pdo->query("SELECT staff_id, full_name FROM teachers WHERE is_approved=1 ORDER BY full_name")->fetchAll();
foreach ($ts as $t) {
    $staffList[] = ['staff_id' => $t['staff_id'], 'staff_name' => $t['full_name']];
}

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $sStaffIds = $_POST['staff_id'] ?? [];
    $sStaffNames = $_POST['staff_name'] ?? [];

    if ($attTerm !== '' && $attYear !== '' && $weekNo > 0 && $monday !== '' && !empty($sStaffIds)) {
        try {
            $pdo->beginTransaction();
            $upsert = $pdo->prepare(
                "INSERT INTO staff_attendance
                 (term_name, academic_year, week_no, week_start_date, staff_id, staff_name, attendance_date, day_name, present, arrival_time, departure_time)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   staff_name = VALUES(staff_name),
                   present = VALUES(present),
                   arrival_time = VALUES(arrival_time),
                   departure_time = VALUES(departure_time)"
            );
            $count = 0;
            foreach ($sStaffIds as $idx => $sid) {
                $sname = $sStaffNames[$idx] ?? '';
                foreach ($weekDays as $day) {
                    $date = $weekDates[$day] ?? '';
                    if ($date === '') continue;
                    $present = isset($_POST['present'][$sid][$day]) ? 1 : 0;
                    $arrival = ($_POST['arrival'][$sid][$day] ?? '') !== '' ? $_POST['arrival'][$sid][$day] : null;
                    $departure = ($_POST['departure'][$sid][$day] ?? '') !== '' ? $_POST['departure'][$sid][$day] : null;
                    $upsert->execute([$attTerm, $attYear, $weekNo, $monday, $sid, $sname, $date, $day, $present, $arrival, $departure]);
                    $count++;
                }
            }
            $pdo->commit();
            $attMsg = 'Attendance saved successfully (' . $count . ' day-record(s)).';
            $attMsgType = 'success';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $attMsg = 'Error saving attendance: ' . $e->getMessage();
            $attMsgType = 'error';
        }
    } else {
        $attMsg = 'Please select term, year and ensure staff are available.';
        $attMsgType = 'error';
    }
}

// Fetch existing attendance rows for the selected week
$attRows = [];
if ($monday) {
    $attStmt = $pdo->prepare("SELECT * FROM staff_attendance WHERE term_name=? AND academic_year=? AND week_start_date=?");
    $attStmt->execute([$attTerm, $attYear, $monday]);
    foreach ($attStmt->fetchAll() as $r) {
        $attRows[$r['staff_id']][$r['day_name']] = $r;
    }
}

// === Matron / Cook Daily Feeding Records ===
$matronMsg = '';
$matronMsgType = '';

// Save matron record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_matron'])) {
    $record_date = trim($_POST['record_date'] ?? '');
    $day_name = trim($_POST['day_name'] ?? '');
    $matron_name = trim($_POST['matron_name'] ?? '');
    $meal_type = trim($_POST['meal_type'] ?? 'Lunch');
    $food_type = trim($_POST['food_type'] ?? '');
    $portions = (int)($_POST['portions'] ?? 0);

    if ($record_date !== '' && $day_name !== '' && $matron_name !== '' && $food_type !== '') {
        try {
            $ins = $pdo->prepare("INSERT INTO matron_records (record_date, day_name, matron_name, meal_type, food_type, portions) VALUES (?,?,?,?,?,?)");
            $ins->execute([$record_date, $day_name, $matron_name, $meal_type, $food_type, $portions]);
            $matronMsg = 'Matron record saved successfully.';
            $matronMsgType = 'success';
        } catch (Exception $e) {
            $matronMsg = 'Error saving: ' . $e->getMessage();
            $matronMsgType = 'error';
        }
    } else {
        $matronMsg = 'Please fill date, day, matron name and food type.';
        $matronMsgType = 'error';
    }
}

// Delete matron record
if (isset($_GET['del_matron'])) {
    $delId = (int)$_GET['del_matron'];
    $pdo->prepare("DELETE FROM matron_records WHERE id = ?")->execute([$delId]);
    $matronMsg = 'Matron record deleted.';
    $matronMsgType = 'success';
}

// Fetch matron records
$matronRecords = $pdo->query("SELECT * FROM matron_records ORDER BY record_date DESC, id DESC LIMIT 100")->fetchAll();

// Auto-calculated totals (per food type and overall)
$matronTotals = [
    'food_items' => [],
    'total_portions' => 0,
    'total_days' => 0
];
foreach ($matronRecords as $mr) {
    $matronTotals['total_portions'] += (int)$mr['portions'];
    $matronTotals['total_days']++;
    $ft = $mr['food_type'];
    if (!isset($matronTotals['food_items'][$ft])) {
        $matronTotals['food_items'][$ft] = ['count' => 0, 'portions' => 0];
    }
    $matronTotals['food_items'][$ft]['count']++;
    $matronTotals['food_items'][$ft]['portions'] += (int)$mr['portions'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-bg" style="background-image:url('<?= APP_URL ?>/assets/images/admin_dashboard.jpg');"></div>

<div class="page-header">
    <h1>Headteacher Dashboard</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/headteacher/enroll.php" class="btn btn-primary">Enroll New Pupil</a>
        <a href="<?= APP_URL ?>/headteacher/reports.php" class="btn btn-gold">Review Reports</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <img src="<?= APP_URL ?>/assets/images/active_learners.jpg" alt="Total Learners" class="stat-card-img">
        <div class="stat-number"><?= $totalLearners ?></div>
        <div class="stat-label">Total Learners</div>
    </div>
    <div class="stat-card gold">
        <img src="<?= APP_URL ?>/assets/images/classes.jpg" alt="Classes" class="stat-card-img">
        <div class="stat-number"><?= $totalClasses ?></div>
        <div class="stat-label">Classes</div>
    </div>
    <div class="stat-card purple">
        <img src="<?= APP_URL ?>/assets/images/teachers_card.jpg" alt="Active Teachers" class="stat-card-img">
        <div class="stat-number"><?= $totalTeachers ?></div>
        <div class="stat-label">Active Teachers</div>
    </div>
    <div class="stat-card red">
        <img src="<?= APP_URL ?>/assets/images/pending_approval.jpg" alt="Pending Report Approval" class="stat-card-img">
        <div class="stat-number"><?= $pendingReports ?></div>
        <div class="stat-label">Pending Report Approval</div>
    </div>
    <div class="stat-card">
        <img src="<?= APP_URL ?>/assets/images/approved_report.jpg" alt="Approved Reports" class="stat-card-img">
        <div class="stat-number"><?= $approvedReports ?></div>
        <div class="stat-label">Approved Reports</div>
    </div>
    <div class="stat-card" style="border-top-color:#6c757d;">
        <img src="<?= APP_URL ?>/assets/images/draft_report.jpg" alt="Draft Reports" class="stat-card-img">
        <div class="stat-number"><?= $draftReports ?></div>
        <div class="stat-label">Draft Reports</div>
    </div>
</div>

<?php if ($pendingReports > 0): ?>
<div class="flash-message flash-info" style="margin:0 0 1.5rem 0;">
    <span><strong><?= $pendingReports ?></strong> report(s) submitted by teachers awaiting your review and approval.</span>
    <a href="<?= APP_URL ?>/headteacher/reports.php" class="btn btn-sm btn-primary">Review Now</a>
</div>
<?php endif; ?>

<div class="card">
    <h3>Recent Submitted Reports (Awaiting Approval)</h3>
    <?php if (empty($recentReports)): ?>
        <p style="color:#888;text-align:center;padding:20px;">No reports pending approval. All submitted reports have been reviewed.</p>
    <?php else: ?>
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
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReports as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= sanitize($r['learner_name']) ?></td>
                        <td><?= sanitize($r['admission_no']) ?></td>
                        <td><?= sanitize($r['class_name']) ?></td>
                        <td><?= sanitize($r['term_name']) ?></td>
                        <td><?= sanitize($r['teacher_name']) ?></td>
                        <td><strong><?= sanitize($r['grand_total']) ?></strong></td>
                        <td><?= $r['submitted_at'] ? date('d/m/Y H:i', strtotime($r['submitted_at'])) : '-' ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/admin/view_report.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <a href="<?= APP_URL ?>/headteacher/reports.php?approve=<?= $r['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this report?')">Approve</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:10px;">
            <a href="<?= APP_URL ?>/headteacher/reports.php" class="btn btn-primary btn-sm">View All Reports</a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Learners per Class</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Class</th>
                    <th>Total Learners</th>
                    <th>Boys</th>
                    <th>Girls</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($learnersPerClass)): ?>
                <tr><td colspan="5">No classes yet.</td></tr>
            <?php else:
                $grandTotal = 0; $grandBoys = 0; $grandGirls = 0;
                foreach ($learnersPerClass as $i => $lc):
                    $grandTotal += (int)$lc['total'];
                    $grandBoys += (int)$lc['boys'];
                    $grandGirls += (int)$lc['girls'];
                ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= sanitize($lc['class_name']) ?></td>
                        <td><strong><?= (int)$lc['total'] ?></strong></td>
                        <td><?= (int)$lc['boys'] ?></td>
                        <td><?= (int)$lc['girls'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#eef7ee;font-weight:bold;">
                    <td colspan="2">Grand Total</td>
                    <td><?= $grandTotal ?></td>
                    <td><?= $grandBoys ?></td>
                    <td><?= $grandGirls ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Weekly Enrollment Tracking</h3>

    <?php if ($enrollMsg): ?>
        <div class="flash-message flash-<?= $enrollMsgType ?>" style="margin-bottom:1rem;"><?= sanitize($enrollMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-grid">
            <div class="form-group">
                <label class="required">Term</label>
                <select name="term_name" required>
                    <option value="">Select Term...</option>
                    <?php foreach (['Term 1', 'Term 2', 'Term 3'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($_POST['term_name'] ?? ($currentTerm['term_name'] ?? '')) === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Academic Year</label>
                <input type="text" name="academic_year" required value="<?= sanitize($_POST['academic_year'] ?? ($currentTerm['academic_year'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label class="required">Date</label>
                <input type="date" name="week_date" required value="<?= sanitize($_POST['week_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label class="required">Week No.</label>
                <input type="number" name="week_no" required min="1" value="<?= sanitize($_POST['week_no'] ?? '') ?>" placeholder="e.g. 1, 2, 3...">
            </div>
            <div class="form-group">
                <label class="required">Class</label>
                <select name="class_id" required>
                    <option value="">Select Class...</option>
                    <?php foreach ($classesList as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($_POST['class_id'] ?? '') == $cl['id'] ? 'selected' : '' ?>><?= sanitize($cl['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Active Boys</label>
                <input type="number" name="active_boys" min="0" value="<?= sanitize($_POST['active_boys'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label class="required">Active Girls</label>
                <input type="number" name="active_girls" min="0" value="<?= sanitize($_POST['active_girls'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label class="required">Dropout Boys</label>
                <input type="number" name="dropout_boys" min="0" value="<?= sanitize($_POST['dropout_boys'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label class="required">Dropout Girls</label>
                <input type="number" name="dropout_girls" min="0" value="<?= sanitize($_POST['dropout_girls'] ?? 0) ?>">
            </div>
        </div>
        <div class="btn-group">
            <button type="submit" name="save_weekly" class="btn btn-primary">Save Weekly Record</button>
        </div>
    </form>

    <h3 style="margin-top:2rem;">Weekly Enrollment Records</h3>
    <div class="table-responsive" style="overflow-x:auto;">
        <table class="data-table" style="font-size:0.82rem;">
            <thead>
                <tr>
                    <th>Term</th>
                    <th>Year</th>
                    <th>Date</th>
                    <th>Week No.</th>
                    <th>Class</th>
                    <th>Active Boys</th>
                    <th>Active Girls</th>
                    <th>Active Total</th>
                    <th>Dropout Boys</th>
                    <th>Dropout Girls</th>
                    <th>Dropout Total</th>
                    <th>Grand Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($weeklyRecords)): ?>
                <tr><td colspan="13">No weekly enrollment records yet. Use the form above to add one.</td></tr>
            <?php else: foreach ($weeklyRecords as $w):
                $activeTotal = (int)$w['active_boys'] + (int)$w['active_girls'];
                $dropTotal = (int)$w['dropout_boys'] + (int)$w['dropout_girls'];
                $grand = $activeTotal + $dropTotal;
            ?>
                <tr>
                    <td><?= sanitize($w['term_name']) ?></td>
                    <td><?= sanitize($w['academic_year']) ?></td>
                    <td><?= date('d/m/Y', strtotime($w['week_date'])) ?></td>
                    <td><?= (int)$w['week_no'] ?></td>
                    <td><?= sanitize($w['class_name']) ?></td>
                    <td><?= (int)$w['active_boys'] ?></td>
                    <td><?= (int)$w['active_girls'] ?></td>
                    <td><strong><?= $activeTotal ?></strong></td>
                    <td><?= (int)$w['dropout_boys'] ?></td>
                    <td><?= (int)$w['dropout_girls'] ?></td>
                    <td><strong><?= $dropTotal ?></strong></td>
                    <td><strong style="color:#008000;"><?= $grand ?></strong></td>
                    <td><a href="?del_week=<?= $w['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this weekly record?')">Delete</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Staff Daily Attendance (Teachers &amp; Headteacher)</h3>

    <?php if ($attMsg): ?>
        <div class="flash-message flash-<?= $attMsgType ?>" style="margin-bottom:1rem;"><?= sanitize($attMsg) ?></div>
    <?php endif; ?>

    <!-- Week selector -->
    <form method="GET" action="" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;padding:12px;background:#f9f9f9;border-radius:6px;border:1px solid #eee;">
        <div class="form-group" style="margin-bottom:0;min-width:130px;">
            <label style="font-size:12px;font-weight:bold;">Term</label>
            <select name="term" onchange="this.form.submit()" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;">
                <?php foreach (['Term 1', 'Term 2', 'Term 3'] as $t): ?>
                    <option value="<?= $t ?>" <?= $attTerm === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;min-width:130px;">
            <label style="font-size:12px;font-weight:bold;">Year</label>
            <input type="text" name="year" value="<?= sanitize($attYear) ?>" onchange="this.form.submit()" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;">
        </div>
<div class="form-group" style="margin-bottom:0;min-width:170px;">
            <label style="font-size:12px;font-weight:bold;">Week Date (Mon–Fri auto)</label>
            <input type="date" name="week_ref" value="<?= sanitize($weekRef) ?>" onchange="this.form.submit()" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;">
        </div>
        <div class="form-group" style="margin-bottom:0;min-width:100px;">
            <label style="font-size:12px;font-weight:bold;">Week No. (manual)</label>
            <input type="number" name="week_no" min="1" value="<?= (int)$weekNo ?>" onchange="this.form.submit()" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;">
        </div>
        <div style="display:flex;gap:6px;">
            <a href="?<?= http_build_query(array_merge($_GET, ['week_ref' => date('Y-m-d', strtotime('-7 days', $mondayTs))])) ?>" class="btn btn-sm btn-secondary">◀ Prev Week</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['week_ref' => date('Y-m-d', strtotime('+7 days', $mondayTs))])) ?>" class="btn btn-sm btn-secondary">Next Week ▶</a>
        </div>
    </form>

    <p class="hint" style="margin-bottom:1rem;">
        Week No. <strong><?= $weekNo ?></strong> &nbsp;|&nbsp; Week starting <strong><?= date('l, d/m/Y', strtotime($monday)) ?></strong>
        &nbsp;|&nbsp; <strong>Present = 1</strong> (ticked) / <strong>Absent = 0</strong> (unticked).
        Tick a day as present and optionally enter arrival/departure times; the <strong>Total</strong> column is auto-calculated.
    </p>

<form method="POST" action="">
        <input type="hidden" name="att_term" value="<?= sanitize($attTerm) ?>">
        <input type="hidden" name="att_year" value="<?= sanitize($attYear) ?>">
        <input type="hidden" name="week_ref" value="<?= sanitize($weekRef) ?>">
        <input type="hidden" name="att_week_no" value="<?= (int)$weekNo ?>">

        <div class="table-responsive" style="overflow-x:auto;">
            <table class="data-table" style="font-size:0.8rem;">
                <thead>
                    <tr>
                        <th rowspan="2" style="text-align:center;">Term</th>
                        <th rowspan="2" style="text-align:center;">Year</th>
                        <th rowspan="2" style="text-align:center;">Week No.</th>
                        <th rowspan="2">Staff ID</th>
                        <th rowspan="2">Fullname</th>
                        <?php foreach ($weekDates as $day => $d): ?>
                            <th colspan="2" style="text-align:center;"><?= $day ?><br><small style="font-weight:normal;"><?= date('d/m/Y', strtotime($d)) ?></small></th>
                        <?php endforeach; ?>
                        <th rowspan="2" style="text-align:center;">Total</th>
                    </tr>
                    <tr>
                        <?php foreach ($weekDays as $day): ?>
                            <th style="text-align:center;font-size:0.7rem;">Arrival<br><small style="font-weight:normal;">(P=1/A=0)</small></th>
                            <th style="text-align:center;font-size:0.7rem;">Departure</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($staffList)): ?>
                    <tr><td colspan="17">No staff available. Please add teachers or a headteacher first.</td></tr>
                <?php else: ?>
                    <?php foreach ($staffList as $i => $st):
                        $sid = $st['staff_id'];
                        $totalPresent = 0;
                        foreach ($weekDays as $day) {
                            if (!empty($attRows[$sid][$day]['present'])) $totalPresent++;
                        }
                    ?>
                    <tr>
                        <td style="text-align:center;"><?= sanitize($attTerm) ?></td>
                        <td style="text-align:center;"><?= sanitize($attYear) ?></td>
                        <td style="text-align:center;"><?= $weekNo ?></td>
                        <td>
                            <?= sanitize($sid) ?>
                            <input type="hidden" name="staff_id[]" value="<?= sanitize($sid) ?>">
                            <input type="hidden" name="staff_name[]" value="<?= sanitize($st['staff_name']) ?>">
                        </td>
                        <td><strong><?= sanitize($st['staff_name']) ?></strong></td>
                        <?php foreach ($weekDays as $day):
                            $row = $attRows[$sid][$day] ?? null;
                            $present = $row['present'] ?? 0;
                            $arr = $row['arrival_time'] ?? '';
                            $dep = $row['departure_time'] ?? '';
                        ?>
                            <td style="text-align:center;white-space:nowrap;">
                                <input type="time" name="arrival[<?= sanitize($sid) ?>][<?= $day ?>]" value="<?= sanitize($arr) ?>" style="width:82px;padding:3px;font-size:0.75rem;" <?= $present ? '' : 'disabled' ?>>
                                <br>
                                <label style="font-size:0.7rem;cursor:pointer;">
                                    <input type="checkbox" name="present[<?= sanitize($sid) ?>][<?= $day ?>]" value="1" <?= $present ? 'checked' : '' ?> onchange="toggleDay(this, <?= $i ?>, '<?= $day ?>')">
                                    <span class="att-status"><?= $present ? 'P(1)' : 'A(0)' ?></span>
                                </label>
                            </td>
                            <td style="text-align:center;">
                                <input type="time" name="departure[<?= sanitize($sid) ?>][<?= $day ?>]" value="<?= sanitize($dep) ?>" style="width:82px;padding:3px;font-size:0.75rem;" <?= $present ? '' : 'disabled' ?>>
                            </td>
                        <?php endforeach; ?>
                        <td style="text-align:center;"><strong style="color:#008000;font-size:1rem;"><?= $totalPresent ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

<div class="btn-group">
            <button type="submit" name="save_attendance" class="btn btn-primary">Save Weekly Attendance</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Matron / Cook Daily Feeding Records</h3>

    <?php if ($matronMsg): ?>
        <div class="flash-message flash-<?= $matronMsgType ?>" style="margin-bottom:1rem;"><?= sanitize($matronMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-grid">
            <div class="form-group">
                <label class="required">Date</label>
                <input type="date" name="record_date" required value="<?= sanitize($_POST['record_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label class="required">Day</label>
                <select name="day_name" required>
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $d): ?>
                        <option value="<?= $d ?>" <?= ($_POST['day_name'] ?? date('l')) === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Matron Name</label>
                <input type="text" name="matron_name" required value="<?= sanitize($_POST['matron_name'] ?? '') ?>" placeholder="e.g. Madam Ama Mensah">
            </div>
            <div class="form-group">
                <label class="required">Meal Type</label>
                <select name="meal_type">
                    <?php foreach (['Lunch', 'Breakfast', 'Snack', 'Supper'] as $m): ?>
                        <option value="<?= $m ?>" <?= ($_POST['meal_type'] ?? 'Lunch') === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Food Cooked (Type)</label>
                <input type="text" name="food_type" required value="<?= sanitize($_POST['food_type'] ?? '') ?>" placeholder="e.g. Jollof Rice, Banku & Tilapia, ...">
            </div>
            <div class="form-group">
                <label>Portions Served</label>
                <input type="number" name="portions" min="0" value="<?= sanitize($_POST['portions'] ?? 0) ?>">
            </div>
        </div>
        <div class="btn-group">
            <button type="submit" name="save_matron" class="btn btn-primary">Save Matron Record</button>
        </div>
    </form>

    <h3 style="margin-top:2rem;">Daily Feeding Records</h3>
    <div class="table-responsive" style="overflow-x:auto;">
        <table class="data-table" style="font-size:0.82rem;">
<thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Matron Name</th>
                    <th>Meal Type</th>
                    <th>Food Cooked</th>
                    <th>Portions</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($matronRecords)): ?>
                <tr><td colspan="8">No feeding records yet. Use the form above to add today's meal.</td></tr>
            <?php else: foreach ($matronRecords as $i => $mr): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= date('d/m/Y', strtotime($mr['record_date'])) ?></td>
                    <td><?= sanitize($mr['day_name']) ?></td>
                    <td><strong><?= sanitize($mr['matron_name']) ?></strong></td>
                    <td><?= sanitize($mr['meal_type']) ?></td>
                    <td><?= sanitize($mr['food_type']) ?></td>
                    <td><?= (int)$mr['portions'] ?></td>
                    <td><a href="?del_matron=<?= $mr['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this feeding record?">Delete</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Auto-calculated summary -->
    <h3 style="margin-top:2rem;">Auto-Calculated Summary</h3>
    <div class="table-responsive" style="overflow-x:auto;">
        <table class="data-table" style="font-size:0.82rem;">
            <thead>
                <tr>
                    <th>Food Cooked</th>
                    <th>Times Served</th>
                    <th>Total Portions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($matronTotals['food_items'])): ?>
                <tr><td colspan="3">No data to summarize.</td></tr>
            <?php else: foreach ($matronTotals['food_items'] as $ft => $fi): ?>
                <tr>
                    <td><strong><?= sanitize($ft) ?></strong></td>
                    <td><?= $fi['count'] ?></td>
                    <td><?= $fi['portions'] ?></td>
                </tr>
            <?php endforeach; ?>
                <tr style="background:#eef7ee;font-weight:bold;">
                    <td colspan="2">Grand Total (<?= $matronTotals['total_days'] ?> day(s))</td>
                    <td><?= $matronTotals['total_portions'] ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="form-grid">
    <div class="card">
        <h3>Quick Links</h3>
        <ul style="list-style:none;padding:0;">
            <li style="padding:8px 0;border-bottom:1px solid #eee;"><a href="<?= APP_URL ?>/headteacher/learners.php" style="color:var(--kente-green);">View All Learners</a></li>
            <li style="padding:8px 0;border-bottom:1px solid #eee;"><a href="<?= APP_URL ?>/headteacher/enroll.php" style="color:var(--kente-green);">Enroll New Pupil</a></li>
            <li style="padding:8px 0;border-bottom:1px solid #eee;"><a href="<?= APP_URL ?>/headteacher/reports.php" style="color:var(--kente-green);">Review & Approve Reports</a></li>
            <li style="padding:8px 0;border-bottom:1px solid #eee;"><a href="<?= APP_URL ?>/admin/settings.php" style="color:var(--kente-green);">School Settings</a></li>
        </ul>
    </div>
    <div class="card">
        <h3>Report Status Overview</h3>
        <div style="text-align:center;padding:10px;">
            <div style="display:inline-block;width:120px;height:120px;border-radius:50%;border:6px solid #008000;display:flex;flex-direction:column;align-items:center;justify-content:center;margin:0 auto;">
                <div style="font-size:2rem;font-weight:bold;color:#008000;"><?= $pendingReports + $approvedReports ?></div>
                <div style="font-size:0.7rem;color:#666;">Total Reports</div>
            </div>
            <div style="display:flex;justify-content:center;gap:20px;margin-top:15px;">
                <div><span class="badge badge-warning" style="font-size:0.9rem;"><?= $pendingReports ?></span> Pending</div>
                <div><span class="badge badge-success" style="font-size:0.9rem;"><?= $approvedReports ?></span> Approved</div>
                <div><span class="badge badge-danger" style="font-size:0.9rem;"><?= $draftReports ?></span> Drafts</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

