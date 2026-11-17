<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('headteacher');
$pageTitle = 'Enroll Pupil';
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission = trim($_POST['admission_no'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    $dob = $_POST['date_of_birth'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $classId = (int)($_POST['class_id'] ?? 0);
    $nhis = trim($_POST['nhis_no'] ?? '');
    $ghana = trim($_POST['ghana_card_no'] ?? '');
    $prev = trim($_POST['previous_school'] ?? '');
    $mother = trim($_POST['mother_name'] ?? '');
    $motherOcc = trim($_POST['mother_occupation'] ?? '');
    $motherContact = trim($_POST['mother_contact'] ?? '');
    $father = trim($_POST['father_name'] ?? '');
    $fatherOcc = trim($_POST['father_occupation'] ?? '');
    $fatherContact = trim($_POST['father_contact'] ?? '');
    $resident = trim($_POST['parents_resident'] ?? '');
    $guardian = trim($_POST['guardian_name'] ?? '');
    $guardOcc = trim($_POST['guardian_occupation'] ?? '');
    $guardContact = trim($_POST['guardian_contact'] ?? '');

    if (empty($admission) || empty($name) || empty($dob) || empty($sex) || !$classId) {
        $error = 'Please fill all required fields (Admission No, Name, DOB, Sex, Class).';
    } else {
        $check = $pdo->prepare("SELECT id FROM learners WHERE admission_no = ?");
        $check->execute([$admission]);
        if ($check->fetch()) {
            $error = 'Admission number already exists.';
        } else {
            $age = calculateAge($dob);
            $photo = uploadFile($_FILES['passport_photo'] ?? null, 'photos');

            $stmt = $pdo->prepare("INSERT INTO learners
                (admission_no, full_name, date_of_birth, age, sex, nhis_no, ghana_card_no, class_id, previous_school,
                 mother_name, mother_occupation, mother_contact, father_name, father_occupation, father_contact,
                 parents_resident, guardian_name, guardian_occupation, guardian_contact, passport_photo, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $admission, $name, $dob, $age, $sex, $nhis, $ghana, $classId, $prev,
                $mother, $motherOcc, $motherContact, $father, $fatherOcc, $fatherContact,
                $resident, $guardian, $guardOcc, $guardContact, $photo, $_SESSION['user_id']
            ]);
            flash('Pupil enrolled successfully. Admission No: ' . $admission);
            redirect(APP_URL . '/headteacher/learners.php');
        }
    }
}

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY id")->fetchAll();
$backLink = APP_URL . '/headteacher/learners.php';
$cancelLink = APP_URL . '/headteacher/dashboard.php';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-bg" style="background-image:url('<?= APP_URL ?>/assets/images/enroll_new_pupil.jpg');"></div>

<div class="page-header">
    <h1 style="color:#fff;">Enroll New Pupil</h1>
    <a href="<?= $backLink ?>" class="btn btn-dark">View All Learners</a>
</div>

<?php if ($error): ?>
    <div class="flash-message flash-error"><?= sanitize($error) ?></div>
<?php endif; ?>

<div class="card enroll-card">
    <form method="POST" enctype="multipart/form-data">
        <h3>Pupil Information</h3>
        <div class="form-grid">
            <div class="form-group">
                <label class="required">Admission No.</label>
                <input type="text" name="admission_no" required value="<?= sanitize($_POST['admission_no'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="required">Pupil Name (Full Name)</label>
                <input type="text" name="full_name" required value="<?= sanitize($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="required">Date of Birth</label>
                <input type="date" name="date_of_birth" required data-age-target="ageField" value="<?= sanitize($_POST['date_of_birth'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Age (auto-calculated)</label>
                <input type="number" id="ageField" name="age" readonly style="background:#eee;" value="<?= sanitize($_POST['age'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="required">Sex</label>
                <select name="sex" required>
                    <option value="">Select...</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Class</label>
                <select name="class_id" required>
                    <option value="">Select Class...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>NHIS No.</label>
                <input type="text" name="nhis_no" value="<?= sanitize($_POST['nhis_no'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Ghana Card No.</label>
                <input type="text" name="ghana_card_no" value="<?= sanitize($_POST['ghana_card_no'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Previous School</label>
                <input type="text" name="previous_school" value="<?= sanitize($_POST['previous_school'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Passport Photo</label>
                <input type="file" name="passport_photo" accept="image/*" data-preview="pupilPhoto">
                <div class="photo-preview" id="pupilPhoto" style="margin-top:6px;">Photo</div>
            </div>
        </div>

        <h3 style="margin-top:1.5rem;">Parents / Guardian Information</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Mother Name</label>
                <input type="text" name="mother_name" value="<?= sanitize($_POST['mother_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Mother Occupation</label>
                <input type="text" name="mother_occupation" value="<?= sanitize($_POST['mother_occupation'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Mother Contact</label>
                <input type="text" name="mother_contact" value="<?= sanitize($_POST['mother_contact'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Father Name</label>
                <input type="text" name="father_name" value="<?= sanitize($_POST['father_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Father Occupation</label>
                <input type="text" name="father_occupation" value="<?= sanitize($_POST['father_occupation'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Father Contact</label>
                <input type="text" name="father_contact" value="<?= sanitize($_POST['father_contact'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Parents Resident</label>
                <input type="text" name="parents_resident" value="<?= sanitize($_POST['parents_resident'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Guardian Name</label>
                <input type="text" name="guardian_name" value="<?= sanitize($_POST['guardian_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Guardian Occupation</label>
                <input type="text" name="guardian_occupation" value="<?= sanitize($_POST['guardian_occupation'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Guardian Contact</label>
                <input type="text" name="guardian_contact" value="<?= sanitize($_POST['guardian_contact'] ?? '') ?>">
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Enroll Pupil</button>
            <a href="<?= $cancelLink ?>" class="btn btn-dark">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
