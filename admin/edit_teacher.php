<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$pageTitle = 'Edit Teacher';
$pdo = getDB();

$teacherId = (int)($_GET['id'] ?? 0);
if (!$teacherId) {
    flash('Invalid teacher ID.', 'error');
    redirect(APP_URL . '/admin/teachers.php');
}

// Fetch teacher
$stmt = $pdo->prepare("SELECT t.*, u.username, u.email as user_email, u.is_active FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    flash('Teacher not found.', 'error');
    redirect(APP_URL . '/admin/teachers.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $staffId = trim($_POST['staff_id'] ?? '');
    $dob = $_POST['date_of_birth'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $marital = $_POST['marital_status'] ?? 'Single';
    $rank = trim($_POST['current_rank'] ?? '');
    $license = trim($_POST['license_number'] ?? '');
    $gps = trim($_POST['gps'] ?? '');
    $ssnit = trim($_POST['ssnit_no'] ?? '');
    $emis = trim($_POST['emis_code'] ?? '');
    $bank = trim($_POST['bank_name'] ?? '');
    $account = trim($_POST['bank_account_number'] ?? '');
    $branch = trim($_POST['bank_branch'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $classTaught = trim($_POST['class_taught'] ?? '');

    if (empty($fullName) || empty($staffId) || empty($sex)) {
        $error = 'Please fill all required fields (Name, Staff ID, Sex).';
    } else {
        // Check staff ID uniqueness
        $check = $pdo->prepare("SELECT id FROM teachers WHERE staff_id = ? AND id != ?");
        $check->execute([$staffId, $teacherId]);
        if ($check->fetch()) {
            $error = 'Staff ID already exists for another teacher.';
        } else {
            $photo = $teacher['passport_photo'];
            if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
                $newPhoto = uploadFile($_FILES['passport_photo'], 'photos');
                if ($newPhoto) $photo = $newPhoto;
            }

            $stmt = $pdo->prepare("UPDATE teachers SET
                staff_id = ?, full_name = ?, date_of_birth = ?, sex = ?, marital_status = ?,
                current_rank = ?, license_number = ?, gps = ?, ssnit_no = ?, emis_code = ?,
                bank_name = ?, bank_account_number = ?, bank_branch = ?,
                email = ?, phone = ?, passport_photo = ?, class_taught = ?
                WHERE id = ?");
            $stmt->execute([
                $staffId, $fullName, $dob, $sex, $marital,
                $rank, $license, $gps, $ssnit, $emis,
                $bank, $account, $branch,
                $email, $phone, $photo, $classTaught,
                $teacherId
            ]);

            flash('Teacher updated successfully.');
            redirect(APP_URL . '/admin/teachers.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY id")->fetchAll();
?>

<div class="page-header">
    <h1>Edit Teacher: <?= sanitize($teacher['full_name']) ?></h1>
    <a href="<?= APP_URL ?>/admin/teachers.php" class="btn btn-dark">Back to Teachers</a>
</div>

<?php if ($error): ?>
    <div class="flash-message flash-error"><?= sanitize($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-group">
                <label class="required">Full Name</label>
                <input type="text" name="full_name" required value="<?= sanitize($_POST['full_name'] ?? $teacher['full_name']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Staff ID</label>
                <input type="text" name="staff_id" required value="<?= sanitize($_POST['staff_id'] ?? $teacher['staff_id']) ?>">
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" value="<?= sanitize($_POST['date_of_birth'] ?? $teacher['date_of_birth']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Sex</label>
                <select name="sex" required>
                    <option value="">Select...</option>
                    <option value="Male" <?= ($_POST['sex'] ?? $teacher['sex']) == 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($_POST['sex'] ?? $teacher['sex']) == 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label>Marital Status</label>
                <select name="marital_status">
                    <option value="Single" <?= ($_POST['marital_status'] ?? $teacher['marital_status']) == 'Single' ? 'selected' : '' ?>>Single</option>
                    <option value="Married" <?= ($_POST['marital_status'] ?? $teacher['marital_status']) == 'Married' ? 'selected' : '' ?>>Married</option>
                    <option value="Divorced" <?= ($_POST['marital_status'] ?? $teacher['marital_status']) == 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                    <option value="Widowed" <?= ($_POST['marital_status'] ?? $teacher['marital_status']) == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                </select>
            </div>
            <div class="form-group">
                <label>Current Rank</label>
                <input type="text" name="current_rank" value="<?= sanitize($_POST['current_rank'] ?? $teacher['current_rank']) ?>">
            </div>
            <div class="form-group">
                <label>License Number</label>
                <input type="text" name="license_number" value="<?= sanitize($_POST['license_number'] ?? $teacher['license_number']) ?>">
            </div>
            <div class="form-group">
                <label>GPS Address</label>
                <input type="text" name="gps" value="<?= sanitize($_POST['gps'] ?? $teacher['gps']) ?>">
            </div>
            <div class="form-group">
                <label>SSNIT No.</label>
                <input type="text" name="ssnit_no" value="<?= sanitize($_POST['ssnit_no'] ?? $teacher['ssnit_no']) ?>">
            </div>
            <div class="form-group">
                <label>EMIS Code</label>
                <input type="text" name="emis_code" value="<?= sanitize($_POST['emis_code'] ?? $teacher['emis_code']) ?>">
            </div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" value="<?= sanitize($_POST['bank_name'] ?? $teacher['bank_name']) ?>">
            </div>
            <div class="form-group">
                <label>Bank Account Number</label>
                <input type="text" name="bank_account_number" value="<?= sanitize($_POST['bank_account_number'] ?? $teacher['bank_account_number']) ?>">
            </div>
            <div class="form-group">
                <label>Bank Branch</label>
                <input type="text" name="bank_branch" value="<?= sanitize($_POST['bank_branch'] ?? $teacher['bank_branch']) ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= sanitize($_POST['email'] ?? $teacher['email']) ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?= sanitize($_POST['phone'] ?? $teacher['phone']) ?>">
            </div>
            <div class="form-group">
                <label>Class Taught</label>
                <select name="class_taught">
                    <option value="">Select...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['class_name'] ?>" <?= ($_POST['class_taught'] ?? $teacher['class_taught']) == $c['class_name'] ? 'selected' : '' ?>><?= sanitize($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Passport Photo</label>
                <?php if ($teacher['passport_photo']): ?>
                    <div style="margin-bottom:4px;">
                        <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($teacher['passport_photo']) ?>" alt="Current" style="width:60px;height:70px;object-fit:cover;border-radius:4px;">
                    </div>
                <?php endif; ?>
                <input type="file" name="passport_photo" accept="image/*">
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Update Teacher</button>
            <a href="<?= APP_URL ?>/admin/teachers.php" class="btn btn-dark">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


