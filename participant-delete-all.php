<?php
/**
 * Delete All Participants Controller
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Requires Admin privileges
requireAdmin();

// Validate CSRF from GET parameter (since confirmation modal triggers redirection link)
$token = $_GET['csrf_token'] ?? '';
if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die("Security Validation Failed: Invalid CSRF Token in delete request.");
}

$db = getDBConnection();

try {
    $db->beginTransaction();
    
    // 1. Fetch all participants with photos to delete them from storage
    $stmtPhotos = $db->query("SELECT photo FROM participants WHERE photo IS NOT NULL AND photo != ''");
    $participants = $stmtPhotos->fetchAll();
    
    // 2. Delete all records from participants table (cascade will handle child tables)
    $db->query("DELETE FROM participants");
    
    $db->commit();
    
    // 3. Unlink photos from filesystem
    foreach ($participants as $p) {
        $photoPath = __DIR__ . '/uploads/participants/' . $p['photo'];
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }
    
    setFlashMessage('success', 'Semua data peserta berhasil dihapus dari sistem.');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    setFlashMessage('danger', 'Gagal menghapus semua data peserta: ' . $e->getMessage());
}

header("Location: participants.php");
exit;
