<?php
/**
 * Add Coach Form (Creates Coach Profile & User Account)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

// Check authorization (Admin only)
requireAdmin();

$db = getDBConnection();
$errors = [];

// Fetch training days
$trainingDays = $db->query("SELECT * FROM training_days ORDER BY id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    checkCSRF();
    
    // Retrieve inputs
    $name = trim($_POST['name'] ?? '');
    $birth_place = trim($_POST['birth_place'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $selectedDays = $_POST['training_days'] ?? [];
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Server-side validation
    if (empty($name)) $errors[] = "Nama lengkap wajib diisi.";
    if (empty($birth_place)) $errors[] = "Tempat lahir wajib diisi.";
    if (empty($birth_date)) $errors[] = "Tanggal lahir wajib diisi.";
    if (!in_array($gender, ['L', 'P'])) $errors[] = "Pilih jenis kelamin yang valid.";
    if (empty($phone)) $errors[] = "Nomor HP wajib diisi.";
    if (empty($address)) $errors[] = "Alamat wajib diisi.";
    if (empty($selectedDays)) $errors[] = "Pilih minimal satu hari jadwal melatih.";
    
    if (empty($username)) {
        $errors[] = "Username akun wajib diisi.";
    } elseif (preg_match('/[^a-zA-Z0-9_]/', $username)) {
        $errors[] = "Username hanya boleh berisi huruf, angka, dan underscore.";
    }
    
    if (empty($email)) {
        $errors[] = "Email akun wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    
    if (empty($password)) {
        $errors[] = "Password akun wajib diisi.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password minimal terdiri dari 6 karakter.";
    }
    
    // Verify user uniqueness
    if (empty($errors)) {
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmtCheck->execute([$username, $email]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errors[] = "Username atau Email sudah terdaftar di sistem.";
        }
    }
    
    // Handle image upload
    $photoName = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $photoName = handlePhotoUpload($_FILES['photo'], __DIR__ . '/uploads/coaches');
        if (!$photoName) {
            $errors[] = "Gagal mengunggah foto pelatih.";
        }
    }
    
    // Process creation inside transaction
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // 1. Create User login account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'coach')");
            $stmtUser->execute([$username, $email, $hashedPassword]);
            $userId = $db->lastInsertId();
            
            // 2. Create Coach profile
            $stmtCoach = $db->prepare("INSERT INTO coaches (user_id, name, birth_place, birth_date, gender, phone, address, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtCoach->execute([$userId, $name, $birth_place, $birth_date, $gender, $phone, $address, $photoName, $status]);
            $coachId = $db->lastInsertId();
            
            // 3. Map selected training days schedule
            $stmtDay = $db->prepare("INSERT INTO coach_training_days (coach_id, training_day_id) VALUES (?, ?)");
            foreach ($selectedDays as $dayId) {
                $stmtDay->execute([$coachId, $dayId]);
            }
            
            $db->commit();
            setFlashMessage('success', 'Pelatih ' . $name . ' beserta akun login berhasil didaftarkan.');
            header("Location: coaches.php");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Terjadi kesalahan database: " . $e->getMessage();
            // Clean up uploaded image if failed
            if ($photoName && file_exists(__DIR__ . '/uploads/coaches/' . $photoName)) {
                unlink(__DIR__ . '/uploads/coaches/' . $photoName);
            }
        }
    }
}
// Load page header layout here, after redirects are completed
$pageTitle = "Tambah Pelatih";
require_once __DIR__ . '/includes/header.php';
?>

<div class="data-card">
    <div style="margin-bottom: 20px;">
        <a href="coaches.php" class="btn btn-secondary btn-sm">&larr; Kembali</a>
    </div>

    <h2 style="margin-bottom: 24px; color: var(--accent);">Form Pendaftaran Pelatih Baru</h2>

    <?php if (!empty($errors)): ?>
        <div class="error-alert" style="margin-bottom: 24px;">
            <strong style="display:block; margin-bottom: 6px;">Perbaiki kesalahan berikut:</strong>
            <ul style="padding-left: 20px; font-size: 0.85rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="coach-add.php" method="POST" enctype="multipart/form-data">
        <?= csrfField(); ?>

        <!-- Photo field -->
        <div class="upload-avatar-container">
            <div class="avatar-preview-box" id="avatar-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div>
                <label for="photo" class="btn btn-secondary btn-sm" style="cursor: pointer;">Unggah Foto Pelatih</label>
                <input type="file" name="photo" id="photo" style="display: none;" accept="image/jpeg,image/png,image/webp">
                <div class="form-helper" style="margin-top: 6px;">Format JPG, JPEG, PNG, atau WebP. Maksimal 2MB.</div>
            </div>
        </div>

        <h3 style="margin-bottom: 20px; color: var(--accent); font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Biodata Profil</h3>

        <div class="form-grid">
            <!-- Full Name -->
            <div class="form-group">
                <label for="name" class="required">Nama Lengkap</label>
                <input type="text" name="name" id="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" placeholder="Nama lengkap pelatih" required>
            </div>

            <!-- Gender -->
            <div class="form-group">
                <label for="gender" class="required">Jenis Kelamin</label>
                <select name="gender" id="gender" class="form-control" required>
                    <option value="">-- Pilih Jenis Kelamin --</option>
                    <option value="L" <?= ($_POST['gender'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= ($_POST['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>

            <!-- Birth Place -->
            <div class="form-group">
                <label for="birth_place" class="required">Tempat Lahir</label>
                <input type="text" name="birth_place" id="birth_place" class="form-control" value="<?= e($_POST['birth_place'] ?? '') ?>" placeholder="Kota tempat lahir" required>
            </div>

            <!-- Birth Date -->
            <div class="form-group">
                <label for="birth_date" class="required">Tanggal Lahir</label>
                <input type="date" name="birth_date" id="birth_date" class="form-control" value="<?= e($_POST['birth_date'] ?? '') ?>" required>
            </div>

            <!-- Phone -->
            <div class="form-group">
                <label for="phone" class="required">Nomor HP / WhatsApp</label>
                <input type="tel" name="phone" id="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="Contoh: 08123456789" required>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label for="status" class="required">Status Keaktifan</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="active" <?= ($_POST['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif (Tidak dapat login)</option>
                </select>
            </div>

            <!-- Training Schedules (Multi Select) -->
            <div class="form-group">
                <label class="required">Jadwal Hari Melatih</label>
                <div class="multi-select-container">
                    <div class="form-control multi-select-trigger" data-placeholder="Pilih Jadwal Hari...">
                        <span class="multi-select-value">Pilih Hari...</span>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                    <div class="multi-select-options">
                        <?php foreach ($trainingDays as $day): 
                            $checked = in_array($day['id'], $_POST['training_days'] ?? []) ? 'checked' : '';
                        ?>
                            <label class="multi-select-option">
                                <input type="checkbox" name="training_days[]" value="<?= $day['id'] ?>" <?= $checked ?>>
                                <span><?= e($day['day_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-group full-width">
                <label for="address" class="required">Alamat Lengkap</label>
                <textarea name="address" id="address" class="form-control" rows="3" placeholder="Alamat lengkap pelatih" required><?= e($_POST['address'] ?? '') ?></textarea>
            </div>
        </div>

        <h3 style="margin: 30px 0 20px; color: var(--info); font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Kredensial Akun Login</h3>

        <div class="form-grid">
            <!-- Username -->
            <div class="form-group">
                <label for="username" class="required">Username</label>
                <input type="text" name="username" id="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" placeholder="Username login" required autocomplete="off">
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email" class="required">Email</label>
                <input type="email" name="email" id="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" placeholder="email@crown.com" required autocomplete="off">
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password" class="required">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 6 karakter" required autocomplete="new-password">
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="coaches.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Pelatih & Akun</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Photo Preview
        const photoInput = document.getElementById('photo');
        const avatarBox = document.getElementById('avatar-box');

        photoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarBox.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
                reader.readAsDataURL(file);
            }
        });
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
