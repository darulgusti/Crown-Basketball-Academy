<?php
/**
 * Utility Helpers
 * Crown Basketball Academy
 */

/**
 * Escape output html safely
 * 
 * @param string $val
 * @return string
 */
function e($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a unique registration number
 * Format: CBA-YYYYMMDD-XXXX where XXXX is random
 * 
 * @return string
 */
function generateRegistrationNumber() {
    $datePart = date('Ymd');
    $randPart = sprintf("%04d", mt_rand(1, 9999));
    return "CBA-" . $datePart . "-" . $randPart;
}

/**
 * Set flash session message
 * 
 * @param string $type - 'success' or 'danger'
 * @param string $message
 */
function setFlashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Render flash messages to the view as toasts
 */
function renderFlashMessages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $type = e($msg['type']);
        $message = e($msg['message']);
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', () => {
                showToast('{$type}', '{$message}');
            });
        </script>";
    }
    
    // Check for auth.php compatibility error messages
    if (isset($_SESSION['error_message'])) {
        $message = e($_SESSION['error_message']);
        unset($_SESSION['error_message']);
        echo "<script>
            document.addEventListener('DOMContentLoaded', () => {
                showToast('danger', '{$message}');
            });
        </script>";
    }
}

/**
 * Validate and handle uploaded photo
 * 
 * @param array $file - The $_FILES item
 * @param string $targetDir - Target directory for uploads
 * @return string|false - Returns relative filepath or false on error
 */
function handlePhotoUpload($file, $targetDir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Allowed properties
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    // 1. Verify file size
    if ($file['size'] > $maxSize) {
        setFlashMessage('danger', 'Ukuran foto maksimal 2MB.');
        return false;
    }
    
    // 2. Verify extension
    $fileInfo = pathinfo($file['name']);
    $ext = strtolower($fileInfo['extension'] ?? '');
    if (!in_array($ext, $allowedExts)) {
        setFlashMessage('danger', 'Format foto harus JPG, JPEG, PNG, atau WebP.');
        return false;
    }
    
    // 3. Verify MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowedMimes)) {
        setFlashMessage('danger', 'Tipe file tidak valid.');
        return false;
    }
    
    // 4. Create safe, unique filename
    // Sanitize original name first, strip weird characters
    $safeName = preg_replace("/[^a-zA-Z0-9_\.-]/", "", $fileInfo['filename']);
    $fileName = time() . '_' . uniqid() . '_' . $safeName . '.' . $ext;
    
    // Make sure target directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $destPath = rtrim($targetDir, '/') . '/' . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        return $fileName;
    }
    
    setFlashMessage('danger', 'Gagal memproses file upload.');
    return false;
}

/**
 * Format date to Indonesian text format (e.g. 23 Juni 2026)
 * 
 * @param string $dateStr - YYYY-MM-DD
 * @return string
 */
function formatIndoDate($dateStr) {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    if (!$time) return '-';
    
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $d = date('j', $time);
    $m = $months[(int)date('n', $time)];
    $y = date('Y', $time);
    
    return "{$d} {$m} {$y}";
}

/**
 * Render status badge HTML
 * 
 * @param string $status
 * @return string
 */
function renderStatusBadge($status) {
    $statusClass = strtolower(str_replace(' ', '-', $status));
    return '<span class="badge badge-' . $statusClass . '">' . e($status) . '</span>';
}

/**
 * Render sliding pagination navigation
 * 
 * @param int $page - Current page
 * @param int $totalPages - Total pages
 * @param array $getParams - $_GET parameters to merge
 * @return string - HTML string of pagination-nav links
 */
function renderPaginationLinks($page, $totalPages, $getParams) {
    if ($totalPages <= 1) return '';
    
    $html = '';
    
    // Left Arrow
    $prevPage = $page - 1;
    $prevUrl = '?' . http_build_query(array_merge($getParams, ['page' => $prevPage]));
    $prevDisabled = $page <= 1 ? 'disabled' : '';
    $html .= '<a href="' . $prevUrl . '" class="page-link ' . $prevDisabled . '">&larr;</a>';
    
    // Determine which page numbers to show
    $range = [];
    if ($totalPages <= 6) {
        $range = range(1, $totalPages);
    } else {
        if ($page <= 3) {
            $middle = [2, 3, 4];
        } elseif ($page >= $totalPages - 2) {
            $middle = [$totalPages - 3, $totalPages - 2, $totalPages - 1];
        } else {
            $middle = [$page - 1, $page, $page + 1];
        }
        
        $range[] = 1;
        if ($middle[0] > 2) {
            $range[] = '...';
        }
        foreach ($middle as $m) {
            $range[] = $m;
        }
        if ($middle[2] < $totalPages - 1) {
            $range[] = '...';
        }
        $range[] = $totalPages;
    }
    
    // Render links
    foreach ($range as $item) {
        if ($item === '...') {
            $html .= '<span class="page-link-ellipsis" style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; color: var(--text-secondary);">...</span>';
        } else {
            $url = '?' . http_build_query(array_merge($getParams, ['page' => $item]));
            $active = $page === $item ? 'active' : '';
            $html .= '<a href="' . $url . '" class="page-link ' . $active . '">' . $item . '</a>';
        }
    }
    
    // Right Arrow
    $nextPage = $page + 1;
    $nextUrl = '?' . http_build_query(array_merge($getParams, ['page' => $nextPage]));
    $nextDisabled = $page >= $totalPages ? 'disabled' : '';
    $html .= '<a href="' . $nextUrl . '" class="page-link ' . $nextDisabled . '">&rarr;</a>';
    
    return $html;
}

