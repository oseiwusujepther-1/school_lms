<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$pageTitle = 'School Settings';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['school_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gps = trim($_POST['gps'] ?? '');
    $emis = trim($_POST['emis_code'] ?? '');
    $tel = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $head = trim($_POST['headteacher_name'] ?? '');
    $year = trim($_POST['academic_year'] ?? '2025/2026');

    $stmt = $pdo->prepare("UPDATE school_settings SET school_name=?, address=?, gps=?, emis_code=?, telephone=?, email=?, headteacher_name=?, academic_year=? WHERE id=1");
    $stmt->execute([$name, $address, $gps, $emis, $tel, $email, $head, $year]);
    flash('Settings updated successfully.');
    redirect(APP_URL . '/admin/settings.php');
}

$settings = $pdo->query("SELECT * FROM school_settings WHERE id=1")->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-bg" style="background-image:url('<?= APP_URL ?>/assets/images/school_settings.jpg');"></div>

<div class="page-header">
    <h1 style="color:#fff;">School Settings</h1>
</div>

<div class="card settings-card" style="max-width:700px;">
    <form method="POST">
        <div class="form-group">
            <label>School Name</label>
            <input type="text" name="school_name" value="<?= sanitize($settings['school_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="address" rows="2"><?= sanitize($settings['address'] ?? '') ?></textarea>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>GPS</label>
                <input type="text" name="gps" value="<?= sanitize($settings['gps'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>EMIS Code</label>
                <input type="text" name="emis_code" value="<?= sanitize($settings['emis_code'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Telephone</label>
                <input type="text" name="telephone" value="<?= sanitize($settings['telephone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= sanitize($settings['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Headteacher Name</label>
                <input type="text" name="headteacher_name" value="<?= sanitize($settings['headteacher_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <input type="text" name="academic_year" value="<?= sanitize($settings['academic_year'] ?? '') ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
