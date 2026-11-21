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

/**
 * Render assessment predicate badge
 * 
 * @param string $predicate
 * @return string
 */
function renderPredicateBadge($predicate) {
    $class = 'badge-secondary';
    if ($predicate === 'Sangat Baik') {
        $class = 'badge-success';
    } elseif ($predicate === 'Baik') {
        $class = 'badge-info';
    } elseif ($predicate === 'Cukup') {
        $class = 'badge-warning';
    } else {
        $class = 'badge-danger';
    }
    return '<span class="badge ' . $class . '">' . e($predicate) . '</span>';
}

/**
 * Ensure assessment-related database tables and columns exist (safe auto-migration).
 *
 * @param PDO $db
 */
function ensureAssessmentTables($db) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $tAssess = $db->query("SHOW TABLES LIKE 'participant_assessments'")->rowCount() > 0;
        $tGroups = $db->query("SHOW TABLES LIKE 'assessment_groups'")->rowCount() > 0;
        $tCrit   = $db->query("SHOW TABLES LIKE 'assessment_criteria'")->rowCount() > 0;

        if (!$tAssess) {
            $db->exec("CREATE TABLE IF NOT EXISTS participant_assessments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                participant_id INT NOT NULL,
                user_id INT NOT NULL,
                coach_id INT DEFAULT NULL,
                coach_name VARCHAR(100) NOT NULL,
                assessment_date DATE NOT NULL,
                criteria_data LONGTEXT NULL DEFAULT NULL,
                chest_pass_prep ENUM('E', 'IE') NOT NULL DEFAULT 'E',
                chest_pass_acc ENUM('E', 'IE') NOT NULL DEFAULT 'E',
                dribble_control ENUM('E', 'IE') NOT NULL DEFAULT 'E',
                dribble_vision ENUM('E', 'IE') NOT NULL DEFAULT 'E',
                shooting_elbow ENUM('E', 'IE') NOT NULL DEFAULT 'E',
                shooting_wrist ENUM('E', 'IE') NOT NULL DEFAULT 'E',
                score_e INT NOT NULL DEFAULT 6,
                score_ie INT NOT NULL DEFAULT 0,
                final_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
                predicate VARCHAR(50) NOT NULL DEFAULT 'Sangat Baik',
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (coach_id) REFERENCES coaches(id) ON DELETE SET NULL,
                INDEX idx_assessment_participant (participant_id),
                INDEX idx_assessment_date (assessment_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            if ($db->query("SHOW COLUMNS FROM participant_assessments LIKE 'criteria_data'")->rowCount() === 0) {
                $db->exec("ALTER TABLE participant_assessments ADD COLUMN criteria_data LONGTEXT NULL DEFAULT NULL AFTER assessment_date");
            }
        }

        if (!$tGroups) {
            $db->exec("CREATE TABLE IF NOT EXISTS assessment_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!$tCrit) {
            $db->exec("CREATE TABLE IF NOT EXISTS assessment_criteria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NULL,
                slot_key VARCHAR(50) NULL,
                category_name VARCHAR(100) NOT NULL,
                criterion_label TEXT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            if ($db->query("SHOW COLUMNS FROM assessment_criteria LIKE 'group_id'")->rowCount() === 0) {
                $db->exec("ALTER TABLE assessment_criteria ADD COLUMN group_id INT NULL AFTER id");
            }
        }

        // Seed default groups & criteria if assessment_groups is empty
        if ((int)$db->query("SELECT COUNT(*) FROM assessment_groups")->fetchColumn() === 0) {
            $critCount = (int)$db->query("SELECT COUNT(*) FROM assessment_criteria")->fetchColumn();
            if ($critCount > 0) {
                $catNames = $db->query("SELECT category_name, MIN(sort_order) as min_sort FROM assessment_criteria GROUP BY category_name ORDER BY min_sort ASC")->fetchAll();
                $insG = $db->prepare("INSERT INTO assessment_groups (name, sort_order) VALUES (?, ?)");
                $updC = $db->prepare("UPDATE assessment_criteria SET group_id = ? WHERE category_name = ?");
                $gSort = 1;
                foreach ($catNames as $cn) {
                    $insG->execute([$cn['category_name'], $gSort++]);
                    $newGid = $db->lastInsertId();
                    $updC->execute([$newGid, $cn['category_name']]);
                }
            } else {
                $defaults = [
                    'Chest Pass (Umpan Dada)' => [
                        ['chest_pass_prep', 'Awalan bola di depan dada, kaki dibuka lebar, badan condong'],
                        ['chest_pass_acc',  'Akurasi lemparan ke arah kawan'],
                    ],
                    'Dribbling' => [
                        ['dribble_control', 'Bola dipantulkan setinggi pinggang dengan ujung jari'],
                        ['dribble_vision',  'Pandangan ke depan, tidak terpaku pada bola'],
                    ],
                    'Shooting' => [
                        ['shooting_elbow',  'Siku lurus di bawah bola saat menembak'],
                        ['shooting_wrist',  'Lepasan bola diikuti gerakan pergelangan tangan'],
                    ],
                ];
                $insG = $db->prepare("INSERT INTO assessment_groups (name, sort_order) VALUES (?, ?)");
                $insC = $db->prepare("INSERT INTO assessment_criteria (group_id, slot_key, category_name, criterion_label, sort_order) VALUES (?, ?, ?, ?, ?)");
                $gOrder = 1;
                $cOrder = 1;
                foreach ($defaults as $gName => $crits) {
                    $insG->execute([$gName, $gOrder++]);
                    $gid = $db->lastInsertId();
                    foreach ($crits as $c) {
                        $insC->execute([$gid, $c[0], $gName, $c[1], $cOrder++]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Ignore non-fatal migration errors
    }
}



