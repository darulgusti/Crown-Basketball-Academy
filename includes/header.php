<?php
/**
 * Shared Header Layout Template
 * Crown Basketball Academy
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../config/database.php';

// Every page using header.php requires authentication by default
requireAuth();

// Fetch avatar from database if not set in session yet
if (isset($_SESSION['user_id']) && !isset($_SESSION['avatar'])) {
    try {
        $db = getDBConnection();
        $stmtAv = $db->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmtAv->execute([$_SESSION['user_id']]);
        $_SESSION['avatar'] = $stmtAv->fetchColumn() ?: null;
    } catch (Exception $e) {
        $_SESSION['avatar'] = null;
    }
}

// Current page filename for active menu highlights
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>Crown Basketball Academy</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23f97316' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><path d='M6.2 6.2c2.4 2.4 2.4 6.4 0 8.8M17.8 6.2c-2.4 2.4-2.4 6.4 0 8.8M2 12h20M12 2v20'/></svg>">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99; backdrop-filter: blur(2px);"></div>

    <div class="app-container" id="app-container">
        
        <?php include_once __DIR__ . '/sidebar.php'; ?>
        
        <div class="main-wrapper">
            
            <header class="top-navbar">
                <div class="nav-left">
                    <button class="hamburger-btn" id="hamburger-menu" aria-label="Open Menu">
                        &#9776;
                    </button>
                    <button class="toggle-sidebar-btn" id="toggle-sidebar" aria-label="Toggle Sidebar">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                        </svg>
                    </button>
                    <h1 class="page-title"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></h1>
                </div>
                
                <div class="nav-right">
                    <span class="user-role badge badge-active"><?= e($_SESSION['role'] === 'admin' ? 'Admin' : 'Pelatih') ?></span>
                    <div class="user-profile-summary" style="background: none; padding: 0;">
                        <div class="user-avatar" style="position: relative; overflow: hidden;">
                            <?php 
                            $headerAvatarUrl = '';
                            if (!empty($_SESSION['avatar'])) {
                                if (strpos($_SESSION['avatar'], 'data:') === 0) {
                                    $headerAvatarUrl = $_SESSION['avatar'];
                                } else {
                                    $headerFilePath = __DIR__ . '/../uploads/avatars/' . $_SESSION['avatar'];
                                    if (file_exists($headerFilePath)) {
                                        $headerAvatarUrl = 'uploads/avatars/' . $_SESSION['avatar'] . '?v=' . filemtime($headerFilePath);
                                    }
                                }
                            }
                            ?>
                            <?php if (!empty($headerAvatarUrl)): ?>
                                <img src="<?= e($headerAvatarUrl) ?>" alt="Avatar"
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info" style="display: block;">
                            <div class="user-name" style="font-size: 0.9rem;"><?= e($_SESSION['username']) ?></div>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="content-container">
