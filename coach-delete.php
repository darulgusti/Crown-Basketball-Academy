<?php
/**
 * Delete Coach Controller
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Requires Admin privileges
requireAdmin();

$db = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate CSRF from GET parameter
$token = $_GET['csrf_token'] ?? '';
if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die("Security Validation Failed: Invalid CSRF Token in delete request.");
}

if ($id > 0) {
    // Fetch profile to identify user_id and photo
    $stmtCoach = $db->prepare("SELECT name, user_id, photo FROM coaches WHERE id = ?");
    $stmtCoach->execute([$id]);
    $coach = $stmtCoach->fetch();
    
    if ($coach) {
        try {
            $db->beginTransaction();
            
            // Delete corresponding user (cascades to coaches profile and scheduled training days)
            $stmtDelUser = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmtDelUser->execute([$coach['user_id']]);
            
            $db->commit();
            
            // Delete physical image file
            if ($coach['photo'] && file_exists(__DIR__ . '/uploads/coaches/' . $coach['photo'])) {
                unlink(__DIR__ . '/uploads/coaches/' . $coach['photo']);
            }
            
            setFlashMessage('success', 'Data pelatih ' . $coach['name'] . ' beserta akun loginnya berhasil dihapus.');
        } catch (Exception $e) {
            $db->rollBack();
            setFlashMessage('danger', 'Gagal menghapus data pelatih: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('danger', 'Data pelatih tidak ditemukan.');
    }
} else {
    setFlashMessage('danger', 'ID pelatih tidak valid.');
}

header("Location: coaches.php");
exit;
