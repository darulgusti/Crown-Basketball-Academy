<?php
/**
 * Edit Participant Form
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

// Check authorization (Admin & Coach can edit)
requireCoachOrAdmin();

$db = getDBConnection();
$errors = [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch participant data
$stmt = $db->prepare("SELECT * FROM participants WHERE id = ?");
$stmt->execute([$id]);
$participant = $stmt->fetch();

if (!$participant) {
    setFlashMessage('danger', 'Data peserta tidak ditemukan.');
    header("Location: participants.php");
    exit;
}

// Fetch training days
$trainingDays = $db->query("SELECT * FROM training_days ORDER BY id ASC")->fetchAll();

// Fetch already selected training day IDs for participant
$stmtDaysSelected = $db->prepare("SELECT training_day_id FROM participant_training_days WHERE participant_id = ?");
$stmtDaysSelected->execute([$id]);
$selectedDayIds = $stmtDaysSelected->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    checkCSRF();
    
    // Retrieve inputs
    $name = trim($_POST['name'] ?? '');
    $birth_place = trim($_POST['birth_place'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $height = (int)($_POST['height'] ?? 0);
    $weight = (int)($_POST['weight'] ?? 0);
    $school_name = trim($_POST['school_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $basketball_experience = $_POST['basketball_experience'] ?? 'Tidak';
    $previous_club = trim($_POST['previous_club'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_job = trim($_POST['parent_job'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $selectedDays = $_POST['training_days'] ?? []; // Array of day IDs
    
    // Server-side validation
    if (empty($name)) $errors[] = "Nama lengkap wajib diisi.";
    if (empty($birth_place)) $errors[] = "Tempat lahir wajib diisi.";
    if (empty($birth_date)) $errors[] = "Tanggal lahir wajib diisi.";
    if (!in_array($gender, ['L', 'P'])) $errors[] = "Pilih jenis kelamin yang valid.";
    if ($height <= 0) $errors[] = "Tinggi badan harus berupa angka lebih dari 0.";
    if ($weight <= 0) $errors[] = "Berat badan harus berupa angka lebih dari 0.";
    if (empty($school_name)) $errors[] = "Nama sekolah wajib diisi.";
    if (empty($phone)) $errors[] = "Nomor HP wajib diisi.";
    if (empty($address)) $errors[] = "Alamat wajib diisi.";
    if ($basketball_experience === 'Ya' && empty($previous_club)) {
        $errors[] = "Nama klub wajib diisi jika memiliki pengalaman bermain basket.";
    }
    if (empty($parent_name)) $errors[] = "Nama orang tua/wali wajib diisi.";
    if (empty($parent_job)) $errors[] = "Pekerjaan orang tua/wali wajib diisi.";
    if (empty($parent_phone)) $errors[] = "Nomor HP orang tua/wali wajib diisi.";
    if (empty($selectedDays)) $errors[] = "Pilih minimal satu hari latihan.";
    
    // Handle image upload
    $photoName = $participant['photo'];
    $newPhotoUploaded = false;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $newPhoto = handlePhotoUpload($_FILES['photo'], __DIR__ . '/uploads/participants');
        if ($newPhoto) {
            $photoName = $newPhoto;
            $newPhotoUploaded = true;
        } else {
            $errors[] = "Gagal mengunggah foto baru.";
        }
    }
    
    // Update if no errors
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $sql = "UPDATE participants SET 
                        photo = ?, name = ?, birth_place = ?, birth_date = ?, gender = ?, 
                        height = ?, weight = ?, school_name = ?, phone = ?, address = ?, 
                        basketball_experience = ?, previous_club = ?, parent_name = ?, 
                        parent_job = ?, parent_phone = ?, status = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $photoName, $name, $birth_place, $birth_date, $gender,
                $height, $weight, $school_name, $phone, $address,
                $basketball_experience, ($basketball_experience === 'Ya' ? $previous_club : null),
                $parent_name, $parent_job, $parent_phone, $status,
                $id
            ]);
            
            // Delete previous training days
            $stmtDelDays = $db->prepare("DELETE FROM participant_training_days WHERE participant_id = ?");
            $stmtDelDays->execute([$id]);
            
            // Insert newly selected training days
            $stmtDay = $db->prepare("INSERT INTO participant_training_days (participant_id, training_day_id) VALUES (?, ?)");
            foreach ($selectedDays as $dayId) {
                $stmtDay->execute([$id, $dayId]);
            }
            
            $db->commit();
            
            // Clean up old image if a new one was successfully uploaded
            if ($newPhotoUploaded && $participant['photo'] && file_exists(__DIR__ . '/uploads/participants/' . $participant['photo'])) {
                unlink(__DIR__ . '/uploads/participants/' . $participant['photo']);
            }
            
            setFlashMessage('success', 'Data peserta ' . $name . ' berhasil diperbarui.');
            header("Location: participants.php");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Terjadi kesalahan database: " . $e->getMessage();
            // Delete new file if failed
            if ($newPhotoUploaded && $photoName && file_exists(__DIR__ . '/uploads/participants/' . $photoName)) {
                unlink(__DIR__ . '/uploads/participants/' . $photoName);
            }
        }
    }
}
// Load page header layout here, after redirects are completed
$pageTitle = "Edit Peserta";
require_once __DIR__ . '/includes/header.php';
?>

<div class="data-card">
    <div style="margin-bottom: 20px;">
        <a href="participants" class="btn btn-secondary btn-sm">&larr; Kembali</a>
    </div>

    <h2 style="margin-bottom: 24px; color: var(--accent);">Edit Data Peserta: <?= e($participant['name']) ?></h2>

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

    <form action="participant-edit.php?id=<?= $id ?>" method="POST" enctype="multipart/form-data">
        <?= csrfField(); ?>

        <!-- Photo Edit field -->
        <div class="upload-avatar-container">
            <div class="avatar-preview-box" id="avatar-box">
                <?php if ($participant['photo']): ?>
                    <img src="<?= e(getPhotoSrc($participant['photo'], 'uploads/participants/')) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                <?php endif; ?>
            </div>
            <div>
                <label for="photo" class="btn btn-secondary btn-sm" style="cursor: pointer;">Ganti Foto Peserta</label>
                <input type="file" name="photo" id="photo" style="display: none;" accept="image/jpeg,image/png,image/webp">
                <div class="form-helper" style="margin-top: 6px;">Format JPG, JPEG, PNG, atau WebP. Maksimal 2MB.</div>
            </div>
        </div>

        <div class="form-grid">
            <!-- Full Name -->
            <div class="form-group">
                <label for="name" class="required">Nama Lengkap</label>
                <input type="text" name="name" id="name" class="form-control" value="<?= e($_POST['name'] ?? $participant['name']) ?>" required>
            </div>

            <!-- Gender -->
            <div class="form-group">
                <label for="gender" class="required">Jenis Kelamin</label>
                <select name="gender" id="gender" class="form-control" required>
                    <option value="L" <?= ($_POST['gender'] ?? $participant['gender']) === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= ($_POST['gender'] ?? $participant['gender']) === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>

            <!-- Birth Place -->
            <div class="form-group">
                <label for="birth_place" class="required">Tempat Lahir</label>
                <input type="text" name="birth_place" id="birth_place" class="form-control" value="<?= e($_POST['birth_place'] ?? $participant['birth_place']) ?>" required>
            </div>

            <!-- Birth Date -->
            <div class="form-group">
                <label for="birth_date" class="required">Tanggal Lahir</label>
                <input type="date" name="birth_date" id="birth_date" class="form-control" value="<?= e($_POST['birth_date'] ?? $participant['birth_date']) ?>" required>
            </div>

            <!-- Height -->
            <div class="form-group">
                <label for="height" class="required">Tinggi Badan (cm)</label>
                <input type="number" name="height" id="height" class="form-control" min="1" value="<?= e($_POST['height'] ?? $participant['height']) ?>" required>
            </div>

            <!-- Weight -->
            <div class="form-group">
                <label for="weight" class="required">Berat Badan (kg)</label>
                <input type="number" name="weight" id="weight" class="form-control" min="1" value="<?= e($_POST['weight'] ?? $participant['weight']) ?>" required>
            </div>

            <!-- School -->
            <div class="form-group">
                <label for="school_name" class="required">Nama Sekolah</label>
                <input type="text" name="school_name" id="school_name" class="form-control" value="<?= e($_POST['school_name'] ?? $participant['school_name']) ?>" required>
            </div>

            <!-- Phone -->
            <div class="form-group">
                <label for="phone" class="required">Nomor HP / WhatsApp</label>
                <input type="tel" name="phone" id="phone" class="form-control" value="<?= e($_POST['phone'] ?? $participant['phone']) ?>" required>
            </div>

            <!-- Address -->
            <div class="form-group full-width">
                <label for="address" class="required">Alamat Tempat Tinggal</label>
                <textarea name="address" id="address" class="form-control" rows="3" required><?= e($_POST['address'] ?? $participant['address']) ?></textarea>
            </div>

            <!-- Experience -->
            <div class="form-group">
                <label for="basketball_experience" class="required">Pengalaman Basket</label>
                <select name="basketball_experience" id="basketball_experience" class="form-control" required>
                    <option value="Tidak" <?= ($_POST['basketball_experience'] ?? $participant['basketball_experience']) === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                    <option value="Ya" <?= ($_POST['basketball_experience'] ?? $participant['basketball_experience']) === 'Ya' ? 'selected' : '' ?>>Ya</option>
                </select>
            </div>

            <!-- Previous Club -->
            <div class="form-group" id="club-group">
                <label for="previous_club" id="club-label">Nama Klub Sebelumnya</label>
                <input type="text" name="previous_club" id="previous_club" class="form-control" value="<?= e($_POST['previous_club'] ?? $participant['previous_club']) ?>" placeholder="Nama klub (wajib jika Ya)">
            </div>

            <!-- Training Days (Multi Select) -->
            <div class="form-group">
                <label class="required">Hari Latihan</label>
                <div class="multi-select-container">
                    <div class="form-control multi-select-trigger" data-placeholder="Pilih Hari Latihan...">
                        <span class="multi-select-value">Pilih Hari...</span>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                    <div class="multi-select-options">
                        <?php foreach ($trainingDays as $day): 
                            $isSelected = in_array($day['id'], $_POST['training_days'] ?? $selectedDayIds);
                            $checked = $isSelected ? 'checked' : '';
                        ?>
                            <label class="multi-select-option">
                                <input type="checkbox" name="training_days[]" value="<?= $day['id'] ?>" <?= $checked ?>>
                                <span><?= e($day['day_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label for="status" class="required">Status Keanggotaan</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="active" <?= ($_POST['status'] ?? $participant['status']) === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= ($_POST['status'] ?? $participant['status']) === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
        </div>

        <h3 style="margin: 30px 0 20px; color: var(--info); font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Data Orang Tua / Wali</h3>
        
        <div class="form-grid">
            <!-- Parent Name -->
            <div class="form-group">
                <label for="parent_name" class="required">Nama Orang Tua / Wali</label>
                <input type="text" name="parent_name" id="parent_name" class="form-control" value="<?= e($_POST['parent_name'] ?? $participant['parent_name']) ?>" required>
            </div>

            <!-- Parent Job -->
            <div class="form-group">
                <label for="parent_job" class="required">Pekerjaan Orang Tua / Wali</label>
                <input type="text" name="parent_job" id="parent_job" class="form-control" value="<?= e($_POST['parent_job'] ?? $participant['parent_job']) ?>" required>
            </div>

            <!-- Parent Phone -->
            <div class="form-group">
                <label for="parent_phone" class="required">No HP Orang Tua / Wali</label>
                <input type="tel" name="parent_phone" id="parent_phone" class="form-control" value="<?= e($_POST['parent_phone'] ?? $participant['parent_phone']) ?>" required>
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="participants" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Perbarui Data</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Toggle Club Name validation based on Experience
        const expSelect = document.getElementById('basketball_experience');
        const clubGroup = document.getElementById('club-group');
        const clubInput = document.getElementById('previous_club');
        const clubLabel = document.getElementById('club-label');

        function toggleClubField() {
            if (expSelect.value === 'Ya') {
                clubGroup.style.opacity = '1';
                clubInput.removeAttribute('disabled');
                clubInput.setAttribute('required', 'required');
                clubLabel.classList.add('required');
            } else {
                clubGroup.style.opacity = '0.5';
                clubInput.setAttribute('disabled', 'disabled');
                clubInput.removeAttribute('required');
                clubLabel.classList.remove('required');
                // Don't clear value immediately in edit unless experience changed from initial state,
                // but client side is safer to follow same behavior
            }
        }

        expSelect.addEventListener('change', toggleClubField);
        // Run on load
        toggleClubField();

        // Photo Upload Preview
        const photoInput = document.getElementById('photo');
        const avatarBox = document.getElementById('avatar-box');

        photoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validate file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showToast('danger', 'Ukuran foto terlalu besar! Maksimal 2 MB.');
                    this.value = '';
                    return;
                }
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
