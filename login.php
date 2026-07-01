<?php
/**
 * User Login Portal
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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF validation
    if (!validateCSRFToken()) {
        $error = "Verifikasi keamanan (CSRF) gagal.";
    } else {
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($identity) || empty($password)) {
            $error = "Username/Email dan Password wajib diisi.";
        } else {
            $db = getDBConnection();
            
            // Search user by username OR email
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if user is a coach and inactive
                if ($user['role'] === 'coach') {
                    $stmtCoach = $db->prepare("SELECT status FROM coaches WHERE user_id = ? LIMIT 1");
                    $stmtCoach->execute([$user['id']]);
                    $coach = $stmtCoach->fetch();
                    if ($coach && $coach['status'] === 'inactive') {
                        $error = "Akun pelatih Anda dinonaktifkan oleh Admin.";
                    }
                }
                
                if (!$error) {
                    loginUser($user);
                    setFlashMessage('success', 'Selamat datang kembali, ' . $user['username'] . '!');
                    header("Location: dashboard.php");
                    exit;
                }
            } else {
                $error = "Username/Email atau Password salah.";
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
    <title>Login - Crown Basketball Academy</title>
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
            <!-- Basketball Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2h2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H9v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 1.54-.37 2.99-1.1 4.29z"/>
            </svg>
        </div>
        
        <div class="login-header">
            <h2>CROWN BASKETBALL</h2>
            <p>Akademi Basket Professional</p>
        </div>

        <?php if ($error): ?>
            <div class="error-alert">
                <?= e($error); ?>
            </div>
        <?php endif; ?>

        <form action="login" method="POST" class="login-form">
            <?= csrfField(); ?>
            
            <div class="form-group">
                <label for="identity">Username / Email</label>
                <input type="text" name="identity" id="identity" class="form-control" placeholder="Masukkan username atau email" value="<?= e($_POST['identity'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>
        </form>



        <a href="index" class="back-link" style="margin-top: 15px;">&larr; Kembali ke Portal Publik</a>
    </div>

</body>
</html>
