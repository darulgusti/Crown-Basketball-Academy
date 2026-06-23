<?php
/**
 * Shared Header Layout Template
 * Crown Basketball Academy
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

// Every page using header.php requires authentication by default
requireAuth();

// Current page filename for active menu highlights
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>Crown Basketball Academy</title>
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
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                        </div>
                        <div class="user-info" style="display: block;">
                            <div class="user-name" style="font-size: 0.9rem;"><?= e($_SESSION['username']) ?></div>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="content-container">
