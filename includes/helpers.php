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
 * Stores the photo as a base64 data URI (compatible with read-only filesystems like Vercel).
 *
 * @param array $file - The $_FILES item
 * @param string $targetDir - Ignored (kept for backward compatibility)
 * @return string|false - Returns base64 data URI string or false on error
 */
function handlePhotoUpload($file, $targetDir = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Allowed properties
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
    $maxSize      = 2 * 1024 * 1024; // 2MB

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
    $mime  = finfo_file($finfo, $file['tmp_name']);
    // finfo_close() removed — deprecated since PHP 8.5, objects are freed automatically

    if (!in_array($mime, $allowedMimes)) {
        setFlashMessage('danger', 'Tipe file tidak valid.');
        return false;
    }

    // 4. Convert to base64 data URI (works on read-only filesystems like Vercel)
    $fileData = file_get_contents($file['tmp_name']);
    if ($fileData === false) {
        setFlashMessage('danger', 'Gagal membaca file foto.');
        return false;
    }

    return 'data:' . $mime . ';base64,' . base64_encode($fileData);
}

/**
 * Resolve a stored photo value to a usable <img src="..."> string.
 * Supports both:
 *   - base64 data URIs  (new format: "data:image/jpeg;base64,...")
 *   - Legacy file paths (old format: "filename.jpg") with a path prefix
 *
 * @param string|null $photo      - Value stored in the database
 * @param string      $filePrefix - URL prefix for legacy file-based photos (e.g. "uploads/participants/")
 * @return string|null            - Ready-to-use src value, or null if empty
 */
function getPhotoSrc($photo, $filePrefix = '') {
    if (empty($photo)) return null;
    // Already a data URI — return as-is
    if (strpos($photo, 'data:') === 0) return $photo;
    // Legacy file path — prepend the directory prefix
    return $filePrefix . $photo;
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

