<?php
/**
 * Authentication and Authorization System
 * Crown Basketball Academy
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the user is authenticated
 * 
 * @return bool
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Require the user to be authenticated, redirect to login otherwise
 */
function requireAuth() {
    if (!isAuthenticated()) {
        $_SESSION['error_message'] = "Silakan login terlebih dahulu untuk mengakses halaman tersebut.";
        header("Location: login.php");
        exit;
    }
}

/**
 * Require a specific role (e.g. 'admin')
 * 
 * @param string|array $roles
 */
function requireRole($roles) {
    requireAuth();
    
    $userRole = $_SESSION['role'];
    $allowed = false;
    
    if (is_array($roles)) {
        $allowed = in_array($userRole, $roles);
    } else {
        $allowed = ($userRole === $roles);
    }
    
    if (!$allowed) {
        http_response_code(403);
        // Redirect to dashboard or show forbidden
        $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk membuka halaman tersebut.";
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Require Admin role
 */
function requireAdmin() {
    requireRole('admin');
}

/**
 * Require Coach or Admin role
 */
function requireCoachOrAdmin() {
    requireRole(['admin', 'coach']);
}

/**
 * Save user session data after successful login
 * 
 * @param array $user
 */
function loginUser($user) {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // If user is a coach, fetch and store coach_id in session
    if ($user['role'] === 'coach') {
        require_once __DIR__ . '/../config/database.php';
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, name FROM coaches WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $coach = $stmt->fetch();
        if ($coach) {
            $_SESSION['coach_id'] = $coach['id'];
            $_SESSION['coach_name'] = $coach['name'];
        }
    }
}

/**
 * Logs out the user by destroying session variables
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
