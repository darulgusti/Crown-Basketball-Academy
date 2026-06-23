<?php
/**
 * CSRF Protection Helpers
 * Crown Basketball Academy
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token and store in session
 * 
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get input HTML field containing CSRF token
 * 
 * @return string
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate POST request CSRF token against session
 * 
 * @return bool
 */
function validateCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    return true;
}

/**
 * Terminate request if CSRF token is invalid
 */
function checkCSRF() {
    if (!validateCSRFToken()) {
        http_response_code(403);
        die("Security Validation Failed: Invalid CSRF Token.");
    }
}
