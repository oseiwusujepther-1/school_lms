<?php
require_once __DIR__ . '/../includes/config.php';
requireRole(['admin', 'headteacher']);
$pageTitle = 'Edit Pupil';
$pdo = getDB();

$learnerId = (int)($_GET['id'] ?? 0);
if (!$learnerId) {
    flash('Invalid learner ID.', 'error');
    redirect(APP_URL . '/admin/learners.php');
}

// Fetch learner
$stmt = $pdo->prepare("SELECT * FROM learners WHERE id = ?");
$stmt->execute([$learnerId]);
$learner = $stmt->fetch();

if (!$learner) {
    flash('Learner not found.', 'error');
    redirect(APP_URL . '/admin/learners.php');
}

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
        // Check if admission no already taken by another learner
        $check = $pdo->prepare("SELECT id FROM learners WHERE admission_no = ? AND id != ?");
        $check->execute([$admission, $learnerId]);
        if ($check->fetch()) {
            $error = 'Admission number already exists for another pupil.';
        } else {
            $age = calculateAge($dob);
            $photo = $learner['passport_photo']; // Keep existing photo by default

            // Only upload if a new file was provided
            if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
                $newPhoto = uploadFile($_FILES['passport_photo'], 'photos');
                if ($newPhoto) $photo = $newPhoto;
            }

            $stmt = $pdo->prepare("UPDATE learners SET
                admission_no = ?, full_name = ?, date_of_birth = ?, age = ?, sex = ?,
                nhis_no = ?, ghana_card_no = ?, class_id = ?, previous_school = ?,
                mother_name = ?, mother_occupation = ?, mother_contact = ?,
                father_name = ?, father_occupation = ?, father_contact = ?,
                parents_resident = ?, guardian_name = ?, guardian_occupation = ?, guardian_contact = ?,
                passport_photo = ?
                WHERE id = ?");
            $stmt->execute([
                $admission, $name, $dob, $age, $sex, $nhis, $ghana, $classId, $prev,
                $mother, $motherOcc, $motherContact, $father, $fatherOcc, $fatherContact,
                $resident, $guardian, $guardOcc, $guardContact, $photo, $learnerId
            ]);

            flash('Pupil updated successfully.');
            $redirectUrl = ($_SESSION['role'] ?? '') === 'admin'
                ? APP_URL . '/admin/learners.php'
                : APP_URL . '/headteacher/learners.php';
            redirect($redirectUrl);
        }
    }
}

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY id")->fetchAll();
$backLink = ($_SESSION['role'] ?? '') === 'admin'
    ? APP_URL . '/admin/learners.php'
    : APP_URL . '/headteacher/learners.php';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Edit Pupil: <?= sanitize($learner['full_name']) ?></h1>
    <a href="<?= $backLink ?>" class="btn btn-dark">Back to Learners</a>
</div>

<?php if ($error): ?>
    <div class="flash-message flash-error"><?= sanitize($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST" enctype="multipart/form-data">
        <h3>Pupil Information</h3>
        <div class="form-grid">
            <div class="form-group">
                <label class="required">Admission No.</label>
                <input type="text" name="admission_no" required value="<?= sanitize($_POST['admission_no'] ?? $learner['admission_no']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Pupil Name (Full Name)</label>
                <input type="text" name="full_name" required value="<?= sanitize($_POST['full_name'] ?? $learner['full_name']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Date of Birth</label>
                <input type="date" name="date_of_birth" required data-age-target="ageFieldEdit" value="<?= sanitize($_POST['date_of_birth'] ?? $learner['date_of_birth']) ?>">
            </div>
            <div class="form-group">
                <label>Age (auto-calculated)</label>
                <input type="number" id="ageFieldEdit" name="age" readonly style="background:#eee;" value="<?= sanitize($_POST['age'] ?? $learner['age']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Sex</label>
                <select name="sex" required>
                    <option value="">Select...</option>
                    <option value="Male" <?= ($_POST['sex'] ?? $learner['sex']) == 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($_POST['sex'] ?? $learner['sex']) == 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Class</label>
                <select name="class_id" required>
                    <option value="">Select Class...</option>
                    <?php $selClass = $_POST['class_id'] ?? $learner['class_id']; ?>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selClass == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>NHIS No.</label>
                <input type="text" name="nhis_no" value="<?= sanitize($_POST['nhis_no'] ?? $learner['nhis_no']) ?>">
            </div>
            <div class="form-group">
                <label>Ghana Card No.</label>
                <input type="text" name="ghana_card_no" value="<?= sanitize($_POST['ghana_card_no'] ?? $learner['ghana_card_no']) ?>">
            </div>
            <div class="form-group">
                <label>Previous School</label>
                <input type="text" name="previous_school" value="<?= sanitize($_POST['previous_school'] ?? $learner['previous_school']) ?>">
            </div>
            <div class="form-group">
                <label>Passport Photo</label>
                <?php if ($learner['passport_photo']): ?>
                    <div style="margin-bottom:4px;">
                        <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($learner['passport_photo']) ?>" alt="Current Photo" style="width:60px;height:70px;object-fit:cover;border-radius:4px;">
                        <span style="font-size:11px;color:#888;"> (current)</span>
                    </div>
                <?php endif; ?>
                <input type="file" name="passport_photo" accept="image/*" data-preview="pupilPhotoEdit">
                <div class="photo-preview" id="pupilPhotoEdit" style="margin-top:6px;">New Photo</div>
            </div>
        </div>

        <h3 style="margin-top:1.5rem;">Parents / Guardian Information</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Mother Name</label>
                <input type="text" name="mother_name" value="<?= sanitize($_POST['mother_name'] ?? $learner['mother_name']) ?>">
            </div>
            <div class="form-group">
                <label>Mother Occupation</label>
                <input type="text" name="mother_occupation" value="<?= sanitize($_POST['mother_occupation'] ?? $learner['mother_occupation']) ?>">
            </div>
            <div class="form-group">
                <label>Mother Contact</label>
                <input type="text" name="mother_contact" value="<?= sanitize($_POST['mother_contact'] ?? $learner['mother_contact']) ?>">
            </div>
            <div class="form-group">
                <label>Father Name</label>
                <input type="text" name="father_name" value="<?= sanitize($_POST['father_name'] ?? $learner['father_name']) ?>">
            </div>
            <div class="form-group">
                <label>Father Occupation</label>
                <input type="text" name="father_occupation" value="<?= sanitize($_POST['father_occupation'] ?? $learner['father_occupation']) ?>">
            </div>
            <div class="form-group">
                <label>Father Contact</label>
                <input type="text" name="father_contact" value="<?= sanitize($_POST['father_contact'] ?? $learner['father_contact']) ?>">
            </div>
            <div class="form-group">
                <label>Parents Resident</label>
                <input type="text" name="parents_resident" value="<?= sanitize($_POST['parents_resident'] ?? $learner['parents_resident']) ?>">
            </div>
            <div class="form-group">
                <label>Guardian Name</label>
                <input type="text" name="guardian_name" value="<?= sanitize($_POST['guardian_name'] ?? $learner['guardian_name']) ?>">
            </div>
            <div class="form-group">
                <label>Guardian Occupation</label>
                <input type="text" name="guardian_occupation" value="<?= sanitize($_POST['guardian_occupation'] ?? $learner['guardian_occupation']) ?>">
            </div>
            <div class="form-group">
                <label>Guardian Contact</label>
                <input type="text" name="guardian_contact" value="<?= sanitize($_POST['guardian_contact'] ?? $learner['guardian_contact']) ?>">
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Update Pupil</button>
            <a href="<?= $backLink ?>" class="btn btn-dark">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


