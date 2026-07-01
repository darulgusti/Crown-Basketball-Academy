<?php
/**
 * User Registration Portal (For testing/development)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

// If already logged in, redirect to dashboard
if (isAuthenticated()) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF
    if (!validateCSRFToken()) {
        $errors[] = "Verifikasi keamanan (CSRF) gagal.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($username)) $errors[] = "Username wajib diisi.";
        if (empty($email)) $errors[] = "Email wajib diisi.";
        if (empty($password)) $errors[] = "Password wajib diisi.";
        if ($password !== $confirmPassword) $errors[] = "Konfirmasi password tidak cocok.";
        
        if (!empty($username) && preg_match('/[^a-zA-Z0-9_]/', $username)) {
            $errors[] = "Username hanya boleh berisi huruf, angka, dan underscore.";
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format email tidak valid.";
        }
        
        if (!empty($password) && strlen($password) < 6) {
            $errors[] = "Password minimal terdiri dari 6 karakter.";
        }
        
        if (empty($errors)) {
            $db = getDBConnection();
            
            try {
                // Verify uniqueness
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmtCheck->execute([$username, $email]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $errors[] = "Username atau Email sudah terdaftar.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new admin user for testing purposes
                    $stmtInsert = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
                    $stmtInsert->execute([$username, $email, $hashedPassword]);
                    
                    setFlashMessage('success', 'Registrasi berhasil! Silakan login menggunakan akun baru Anda.');
                    header("Location: login.php");
                    exit;
                }
            } catch (Exception $e) {
                $errors[] = "Gagal mendaftarkan akun: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Crown Basketball Academy</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at center, #ffffff 0%, #cbd5e1 100%);
            padding: 20px;
        }
        .login-card {
            background-color: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-lg), 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--bg-tertiary);
            text-align: center;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px rgba(249, 115, 22, 0.3);
            flex-shrink: 0;
        }
        .login-logo svg {
            width: 48px;
            height: 48px;
            fill: #ffffff;
        }
        .login-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(to right, var(--text-primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 30px;
        }
        .error-alert {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: var(--text-primary);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            text-align: left;
        }
        .login-form .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .login-form .btn-primary {
            width: 100%;
            margin-top: 10px;
            padding: 12px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-logo">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2h2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H9v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 1.54-.37 2.99-1.1 4.29z"/>
            </svg>
        </div>
        
        <div class="login-header">
            <h2>BUAT AKUN BARU</h2>
            <p>Registrasi Akun Admin Pengujian</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-alert">
                <ul style="padding-left: 15px; margin: 0;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register" method="POST" class="login-form">
            <?= csrfField(); ?>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" value="<?= e($_POST['username'] ?? ''); ?>" required autocomplete="off">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Masukkan email" value="<?= e($_POST['email'] ?? ''); ?>" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password (min. 6 karakter)" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ulangi password" required>
            </div>

            <button type="submit" class="btn btn-primary">Daftar Akun</button>
        </form>

        <a href="login" class="back-link">&larr; Kembali ke Login</a>
    </div>

</body>
</html>
