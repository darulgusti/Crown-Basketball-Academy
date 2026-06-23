<?php
/**
 * Delete Participant Controller
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Requires Admin privileges
requireAdmin();

$db = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate CSRF from GET parameter (since confirmation modal triggers redirection link)
$token = $_GET['csrf_token'] ?? '';
if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die("Security Validation Failed: Invalid CSRF Token in delete request.");
}

if ($id > 0) {
    // Fetch photo to clean up file from storage
    $stmtPhoto = $db->prepare("SELECT name, photo FROM participants WHERE id = ?");
    $stmtPhoto->execute([$id]);
    $p = $stmtPhoto->fetch();
    
    if ($p) {
        try {
            $db->beginTransaction();
            
            // Delete participant (foreign key CASCADE deletes mappings and attendance automatically)
            $stmtDel = $db->prepare("DELETE FROM participants WHERE id = ?");
            $stmtDel->execute([$id]);
            
            $db->commit();
            
            // Unlink photo if exists
            if ($p['photo'] && file_exists(__DIR__ . '/uploads/participants/' . $p['photo'])) {
                unlink(__DIR__ . '/uploads/participants/' . $p['photo']);
            }
            
            setFlashMessage('success', 'Data peserta ' . $p['name'] . ' berhasil dihapus dari sistem.');
        } catch (Exception $e) {
            $db->rollBack();
            setFlashMessage('danger', 'Gagal menghapus data peserta: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('danger', 'Data peserta tidak ditemukan.');
    }
} else {
    setFlashMessage('danger', 'ID peserta tidak valid.');
}

header("Location: participants.php");
exit;
