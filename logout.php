<?php
/**
 * User Logout Processor
 * Crown Basketball Academy
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

logoutUser();

session_start();
setFlashMessage('success', 'Anda berhasil logout.');
header("Location: login.php");
exit;
