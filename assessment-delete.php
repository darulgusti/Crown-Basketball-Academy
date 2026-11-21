<?php
/**
 * Delete Participant Assessment Controller
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

requireAuth();

$db = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdmin = ($_SESSION['role'] === 'admin');
$currentUserId = $_SESSION['user_id'];

// Validate CSRF token
$token = $_GET['csrf_token'] ?? '';
if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die("Security Validation Failed: Invalid CSRF Token in delete request.");
}

if ($id > 0) {
    $stmt = $db->prepare("SELECT pa.id, pa.user_id, pa.assessment_date, p.name as participant_name
                          FROM participant_assessments pa
                          JOIN participants p ON pa.participant_id = p.id
                          WHERE pa.id = ?");
    $stmt->execute([$id]);
    $assessment = $stmt->fetch();
    
    if ($assessment) {
        if (!$isAdmin && $assessment['user_id'] != $currentUserId) {
            setFlashMessage('danger', 'Anda tidak memiliki hak akses untuk menghapus lembar penilaian ini.');
            header("Location: assessments.php");
            exit;
        }
        
        try {
            $stmtDel = $db->prepare("DELETE FROM participant_assessments WHERE id = ?");
            $stmtDel->execute([$id]);
            setFlashMessage('success', 'Data lembar penilaian untuk ' . $assessment['participant_name'] . ' berhasil dihapus.');
        } catch (Exception $e) {
            setFlashMessage('danger', 'Gagal menghapus penilaian: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('danger', 'Data penilaian tidak ditemukan.');
    }
}

header("Location: assessments.php");
exit;

