<?php
/**
 * Export Participants to Excel (.xlsx)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Requires authentication
requireAuth();

$db = getDBConnection();

// Fetch query filters (same as participants.php list)
$search = trim($_GET['search'] ?? '');
$gender = $_GET['gender'] ?? '';
$day = $_GET['day'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Base SQL query
$queryStr = "SELECT p.*, GROUP_CONCAT(td.day_name ORDER BY td.id SEPARATOR ' & ') as training_days
             FROM participants p
             LEFT JOIN participant_training_days ptd ON p.id = ptd.participant_id
             LEFT JOIN training_days td ON ptd.training_day_id = td.id
             WHERE 1=1";
$params = [];

if (!empty($search)) {
    $queryStr .= " AND p.name LIKE ?";
    $params[] = "%{$search}%";
}

if (!empty($gender)) {
    $queryStr .= " AND p.gender = ?";
    $params[] = $gender;
}

if (!empty($day)) {
    $queryStr .= " AND p.id IN (SELECT participant_id FROM participant_training_days WHERE training_day_id = ?)";
    $params[] = $day;
}

if (!empty($status)) {
    $queryStr .= " AND p.status = ?";
    $params[] = $status;
}

$queryStr .= " GROUP BY p.id";

// Sort condition
if ($sort === 'youngest') {
    $queryStr .= " ORDER BY p.birth_date DESC";
} elseif ($sort === 'oldest') {
    $queryStr .= " ORDER BY p.birth_date ASC";
} else {
    $queryStr .= " ORDER BY p.created_at DESC";
}

$stmt = $db->prepare($queryStr);
$stmt->execute($params);
$participants = $stmt->fetchAll();

// Export as XLSX using SimpleXLSXGen
require_once __DIR__ . '/includes/SimpleXLSXGen.php';

$excelData = [];
// Define Headers
$excelData[] = [
    'Nama Lengkap',
    'Tempat Lahir',
    'Tanggal Lahir',
    'Jenis Kelamin',
    'Tinggi Badan (cm)',
    'Berat Badan (kg)',
    'Asal Sekolah',
    'No. HP',
    'Alamat Lengkap',
    'Pengalaman Basket',
    'Nama Klub Sebelumnya',
    'Hari Latihan',
    'Nama Orang Tua / Wali',
    'Pekerjaan Orang Tua',
    'No. HP Orang Tua / Wali',
    'Status Anggota'
];

foreach ($participants as $p) {
    $excelData[] = [
        $p['name'],
        $p['birth_place'],
        $p['birth_date'],
        $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan',
        (int)$p['height'],
        (int)$p['weight'],
        $p['school_name'],
        $p['phone'],
        $p['address'],
        $p['basketball_experience'],
        $p['previous_club'] ?: '',
        $p['training_days'] ?: '',
        $p['parent_name'],
        $p['parent_job'],
        $p['parent_phone'],
        $p['status'] === 'active' ? 'Aktif' : 'Nonaktif'
    ];
}

if (ob_get_level()) {
    ob_end_clean();
}

$filename = "Peserta_CBA_" . date('Ymd_His') . ".xlsx";
\Shuchkin\SimpleXLSXGen::fromArray($excelData)->downloadAs($filename);
exit;
