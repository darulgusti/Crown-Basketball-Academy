<?php
/**
 * User Profile Management
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Requires authentication
requireAuth();

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch current user details
$stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$currentUser = $stmtUser->fetch();

if (!$currentUser) {
    die("User not found.");
}

$errors = [];
$adminErrors = [];
$action = '';

// Handle Post Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    checkCSRF();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_avatar') {
        // Handle profile photo upload
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Harap pilih file foto.";
        } else {
            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Gagal mengunggah foto.";
            } elseif (!in_array($file['type'], $allowedTypes)) {
                $errors[] = "Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.";
            } elseif ($file['size'] > $maxSize) {
                $errors[] = "Ukuran foto maksimal 2MB.";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/uploads/avatars/';
                $uploadPath = $uploadDir . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    // Delete old avatar if exists
                    if (!empty($currentUser['avatar'])) {
                        $oldPath = $uploadDir . $currentUser['avatar'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    $stmtAv = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmtAv->execute([$newFileName, $userId]);
                    $_SESSION['avatar'] = $newFileName;
                    setFlashMessage('success', 'Foto profil berhasil diperbarui.');
                    header("Location: profile.php");
                    exit;
                } else {
                    $errors[] = "Gagal menyimpan foto ke server.";
                }
            }
        }
        $action = 'update_avatar';

    } elseif ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmNewPassword = $_POST['confirm_new_password'] ?? '';
        
        // Basic validations
        if (empty($username)) {
            $errors[] = "Username tidak boleh kosong.";
        }
        if (empty($email)) {
            $errors[] = "Email tidak boleh kosong.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format email tidak valid.";
        }
        if (empty($currentPassword)) {
            $errors[] = "Password saat ini wajib diisi untuk mengonfirmasi perubahan.";
        }
        
        // Verify current password
        if (empty($errors)) {
            if (!password_verify($currentPassword, $currentUser['password'])) {
                $errors[] = "Password saat ini salah.";
            }
        }
        
        // Check uniqueness for username and email if changed
        if (empty($errors)) {
            if ($username !== $currentUser['username']) {
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmtCheck->execute([$username]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $errors[] = "Username '$username' sudah digunakan.";
                }
            }
            
            if ($email !== $currentUser['email']) {
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $errors[] = "Email '$email' sudah digunakan.";
                }
            }
        }
        
        // Validate new password if provided
        $updatePassword = false;
        if (empty($errors) && !empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $errors[] = "Password baru minimal 6 karakter.";
            } elseif ($newPassword !== $confirmNewPassword) {
                $errors[] = "Konfirmasi password baru tidak cocok.";
            } else {
                $updatePassword = true;
            }
        }
        
        // Save changes if no errors
        if (empty($errors)) {
            try {
                if ($updatePassword) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtUpdate = $db->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
                    $stmtUpdate->execute([$username, $email, $hashedPassword, $userId]);
                } else {
                    $stmtUpdate = $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    $stmtUpdate->execute([$username, $email, $userId]);
                }
                
                // Update session
                $_SESSION['username'] = $username;
                
                setFlashMessage('success', 'Profil Anda berhasil diperbarui.');
                header("Location: profile.php");
                exit;
                
            } catch (PDOException $e) {
                $errors[] = "Terjadi kesalahan database: " . $e->getMessage();
            }
        }
        
    } elseif ($action === 'add_admin') {
        // Only Admin can add other Admin accounts
        if ($role !== 'admin') {
            die("Unauthorized access.");
        }
        
        $adminUsername = trim($_POST['admin_username'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminConfirmPassword = $_POST['admin_confirm_password'] ?? '';
        
        if (empty($adminUsername)) {
            $adminErrors[] = "Username admin baru wajib diisi.";
        }
        if (empty($adminEmail)) {
            $adminErrors[] = "Email admin baru wajib diisi.";
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $adminErrors[] = "Format email admin baru tidak valid.";
        }
        if (empty($adminPassword)) {
            $adminErrors[] = "Password admin baru wajib diisi.";
        } elseif (strlen($adminPassword) < 6) {
            $adminErrors[] = "Password admin baru minimal 6 karakter.";
        }
        if ($adminPassword !== $adminConfirmPassword) {
            $adminErrors[] = "Konfirmasi password admin baru tidak cocok.";
        }
        
        // Check uniqueness
        if (empty($adminErrors)) {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmtCheck->execute([$adminUsername]);
            if ($stmtCheck->fetchColumn() > 0) {
                $adminErrors[] = "Username '$adminUsername' sudah digunakan.";
            }
            
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmtCheck->execute([$adminEmail]);
            if ($stmtCheck->fetchColumn() > 0) {
                $adminErrors[] = "Email '$adminEmail' sudah digunakan.";
            }
        }
        
        // Insert new Admin account if no errors
        if (empty($adminErrors)) {
            try {
                $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
                $stmtInsert = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
                $stmtInsert->execute([$adminUsername, $adminEmail, $hashedPassword]);
                
                setFlashMessage('success', "Akun admin baru '$adminUsername' berhasil ditambahkan.");
                header("Location: profile.php");
                exit;
                
            } catch (PDOException $e) {
                $adminErrors[] = "Terjadi kesalahan database: " . $e->getMessage();
            }
        }
    }
}

// Load page layout
$pageTitle = "Profil Saya";
require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-layout" style="display: grid; grid-template-columns: 1fr 2.2fr; gap: 24px; align-items: start;">
    
    <!-- Left Column: User Card -->
    <div class="data-card" style="text-align: center; padding: 30px 20px;">

        <!-- Avatar with upload trigger -->
        <form action="profile.php" method="POST" enctype="multipart/form-data" id="avatar-form">
            <?= csrfField(); ?>
            <input type="hidden" name="action" value="update_avatar">
            <input type="file" name="avatar" id="avatar-input" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="document.getElementById('avatar-form').submit()">
        </form>

        <div onclick="document.getElementById('avatar-input').click()" title="Klik untuk ganti foto profil"
             style="position: relative; width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 12px auto; cursor: pointer; display: flex; align-items: center; justify-content: center;">
            <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/uploads/avatars/' . $currentUser['avatar'])): ?>
                <img src="uploads/avatars/<?= e($currentUser['avatar']) ?>?v=<?= time() ?>" alt="Avatar"
                     style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); box-shadow: 0 4px 15px rgba(249,115,22,0.3);">
            <?php else: ?>
                <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: #fff; box-shadow: 0 4px 15px rgba(249,115,22,0.3);">
                    <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <!-- Camera overlay -->
            <div style="position: absolute; bottom: 2px; right: 2px; width: 28px; height: 28px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; border: 2px solid var(--bg-secondary);">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#fff" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <circle cx="12" cy="13" r="4"/>
                </svg>
            </div>
        </div>
        <p style="font-size: 0.72rem; color: var(--text-secondary); margin-bottom: 16px;">Klik foto untuk mengganti</p>

        <h3 style="margin-bottom: 8px; font-size: 1.3rem; font-weight: 600;"><?= e($currentUser['username']) ?></h3>
        <div style="margin-bottom: 20px;">
            <span class="badge badge-active" style="padding: 6px 12px; font-size: 0.8rem; background: var(--accent-light); color: var(--accent); font-weight: 600; text-transform: uppercase;"><?= e($currentUser['role'] === 'admin' ? 'Administrator' : 'Pelatih') ?></span>
        </div>

        <div style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px; text-align: left;">
            <div style="margin-bottom: 12px; font-size: 0.9rem;">
                <strong style="color: var(--text-secondary); display: block; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 4px;">Alamat Email</strong>
                <span><?= e($currentUser['email']) ?></span>
            </div>
            <div style="font-size: 0.9rem;">
                <strong style="color: var(--text-secondary); display: block; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 4px;">Tanggal Terdaftar</strong>
                <span><?= date('d-m-Y', strtotime($currentUser['created_at'])) ?></span>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Forms -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Form Card 1: Edit Profile -->
        <div class="data-card">
            <h3 style="margin-bottom: 20px; color: var(--accent); font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Ubah Profil & Password</h3>
            
            <?php if ($action === 'update_profile' && !empty($errors)): ?>
                <div class="error-alert" style="margin-bottom: 24px;">
                    <strong style="display:block; margin-bottom: 6px;">Perbaiki kesalahan berikut:</strong>
                    <ul style="padding-left: 20px; font-size: 0.85rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form action="profile.php" method="POST">
                <?= csrfField(); ?>
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label for="username" class="required">Username</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?= e($_POST['username'] ?? $currentUser['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="required">Alamat Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= e($_POST['email'] ?? $currentUser['email']) ?>" required>
                    </div>
                </div>
                
                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                    <div class="form-group">
                        <label for="new_password">Password Baru (Opsional)</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_password">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                </div>
                
                <div style="border-top: 1px solid rgba(255,255,255,0.05); margin-top: 24px; padding-top: 20px;">
                    <div class="form-group" style="max-width: 50%;">
                        <label for="current_password" class="required">Password Saat Ini</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Wajib diisi untuk menyimpan" required>
                    </div>
                </div>
                
                <div style="margin-top: 24px; text-align: right;">
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
        
        <!-- Form Card 2: Create Admin (Admin Only) -->
        <?php if ($role === 'admin'): ?>
            <div class="data-card">
                <h3 style="margin-bottom: 20px; color: var(--accent); font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Tambah Akun Admin Baru</h3>
                
                <?php if ($action === 'add_admin' && !empty($adminErrors)): ?>
                    <div class="error-alert" style="margin-bottom: 24px;">
                        <strong style="display:block; margin-bottom: 6px;">Perbaiki kesalahan berikut:</strong>
                        <ul style="padding-left: 20px; font-size: 0.85rem;">
                            <?php foreach ($adminErrors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="profile.php" method="POST">
                    <?= csrfField(); ?>
                    <input type="hidden" name="action" value="add_admin">
                    
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="admin_username" class="required">Username Admin Baru</label>
                            <input type="text" name="admin_username" id="admin_username" class="form-control" value="<?= e($_POST['admin_username'] ?? '') ?>" placeholder="Username admin baru" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_email" class="required">Email Admin Baru</label>
                            <input type="email" name="admin_email" id="admin_email" class="form-control" value="<?= e($_POST['admin_email'] ?? '') ?>" placeholder="Email admin baru" required>
                        </div>
                    </div>
                    
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                        <div class="form-group">
                            <label for="admin_password" class="required">Password</label>
                            <input type="password" name="admin_password" id="admin_password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_confirm_password" class="required">Konfirmasi Password</label>
                            <input type="password" name="admin_confirm_password" id="admin_confirm_password" class="form-control" placeholder="Ulangi password" required>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; text-align: right;">
                        <button type="submit" class="btn btn-primary">Tambah Admin</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
