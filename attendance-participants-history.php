<?php
/**
 * Participant Attendance History & Export Rekap
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Requires auth
requireCoachOrAdmin();

$db = getDBConnection();

// Fetch filter inputs
$participantId = $_GET['participant_id'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$monthFilter = $_GET['month'] ?? '';
$yearFilter = $_GET['year'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build dynamic query
$queryStr = "SELECT pa.*, p.name, p.registration_number, u.username as recorder_name 
             FROM participant_attendances pa
             JOIN participants p ON pa.participant_id = p.id
             LEFT JOIN users u ON pa.recorded_by = u.id
             WHERE 1=1";
$params = [];

if (!empty($participantId)) {
    $queryStr .= " AND pa.participant_id = ?";
    $params[] = $participantId;
}

if (!empty($dateFilter)) {
    $queryStr .= " AND pa.date = ?";
    $params[] = $dateFilter;
}

if (!empty($monthFilter)) {
    $queryStr .= " AND MONTH(pa.date) = ?";
    $params[] = $monthFilter;
}

if (!empty($yearFilter)) {
    $queryStr .= " AND YEAR(pa.date) = ?";
    $params[] = $yearFilter;
}

if (!empty($statusFilter)) {
    $queryStr .= " AND pa.status = ?";
    $params[] = $statusFilter;
}

$queryStr .= " ORDER BY pa.date DESC, pa.session ASC, p.name ASC";

// ----------------------------------------------------
// INTEGRATED EXPORT ACTION DETECTOR
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $stmtExp = $db->prepare($queryStr);
    $stmtExp->execute($params);
    $records = $stmtExp->fetchAll();
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $filename = "Rekap_Absensi_Peserta_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8
    
    // Headers
    fputcsv($output, [
        'Tanggal Latihan',
        'No. Pendaftaran',
        'Nama Lengkap',
        'Sesi Latihan',
        'Status Kehadiran',
        'Catatan',
        'Dicatat Oleh'
    ]);
    
    foreach ($records as $r) {
        fputcsv($output, [
            $r['date'],
            $r['registration_number'],
            $r['name'],
            $r['session'],
            $r['status'],
            $r['notes'] ?: '-',
            $r['recorder_name'] ?: '-'
        ]);
    }
    
    fclose($output);
    exit;
}

// Otherwise, load page with header layout
$pageTitle = "Riwayat Absensi Peserta";
require_once __DIR__ . '/includes/header.php';

// Pagination setup
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch total records for pagination
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM ($queryStr) AS sub");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch items for current page
$queryPaginated = $queryStr . " LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($queryPaginated);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Fetch filter selections listing
$participantsList = $db->query("SELECT id, name, registration_number FROM participants ORDER BY name ASC")->fetchAll();
$yearsList = $db->query("SELECT DISTINCT YEAR(date) as year FROM participant_attendances ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Month names helper mapping
$monthsList = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<!-- Tab Actions -->
<div class="card-actions" style="margin-bottom: 24px;">
    <div style="display: flex; gap: 8px;">
        <a href="attendance-participants.php" class="btn btn-secondary">
            Pencatatan Absensi
        </a>
        <a href="attendance-participants-history.php" class="btn btn-primary">
            Riwayat & Rekap Absensi
        </a>
    </div>
</div>

<!-- Filters Panel -->
<div class="data-card">
    <form action="attendance-participants-history.php" method="GET">
        <div class="card-actions" style="margin-bottom: 0;">
            <div class="search-filter-box">
                
                <!-- Participant select -->
                <div class="form-group" style="min-width: 220px;">
                    <label for="participant_id">Peserta</label>
                    <select name="participant_id" id="participant_id" class="form-control">
                        <option value="">-- Semua Peserta --</option>
                        <?php foreach ($participantsList as $pl): ?>
                            <option value="<?= $pl['id'] ?>" <?= (string)$participantId === (string)$pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date picker -->
                <div class="form-group" style="min-width: 150px;">
                    <label for="date">Tanggal</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?= e($dateFilter) ?>">
                </div>

                <!-- Month select -->
                <div class="form-group" style="min-width: 150px;">
                    <label for="month">Bulan</label>
                    <select name="month" id="month" class="form-control">
                        <option value="">-- Semua Bulan --</option>
                        <?php foreach ($monthsList as $num => $name): ?>
                            <option value="<?= $num ?>" <?= (string)$monthFilter === (string)$num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Year select -->
                <div class="form-group" style="min-width: 120px;">
                    <label for="year">Tahun</label>
                    <select name="year" id="year" class="form-control">
                        <option value="">-- Semua --</option>
                        <?php foreach ($yearsList as $yr): ?>
                            <option value="<?= $yr ?>" <?= (string)$yearFilter === (string)$yr ? 'selected' : '' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status select -->
                <div class="form-group" style="min-width: 150px;">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">-- Semua Status --</option>
                        <option value="Hadir" <?= $statusFilter === 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                        <option value="Izin" <?= $statusFilter === 'Izin' ? 'selected' : '' ?>>Izin</option>
                        <option value="Sakit" <?= $statusFilter === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                        <option value="Tanpa Keterangan" <?= $statusFilter === 'Tanpa Keterangan' ? 'selected' : '' ?>>Tanpa Keterangan</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 8px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="attendance-participants-history.php" class="btn btn-secondary">Reset</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'export'])) ?>" class="btn btn-success">Ekspor CSV</a>
            </div>
        </div>
    </form>
</div>

<!-- History Data Table -->
<div class="data-card">
    <?php if (count($history) > 0): ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Tanggal Latihan</th>
                        <th>Nama Lengkap</th>
                        <th>Sesi</th>
                        <th>Status Kehadiran</th>
                        <th>Catatan</th>
                        <th>Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><strong><?= formatIndoDate($h['date']) ?></strong></td>
                            <td><strong><?= e($h['name']) ?></strong></td>
                            <td><?= e($h['session']) ?></td>
                            <td><?= renderStatusBadge($h['status']) ?></td>
                            <td style="white-space: normal; max-width: 250px; font-size: 0.9rem; color: var(--text-secondary);">
                                <?= $h['notes'] ? e($h['notes']) : '-' ?>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; font-weight: 500; color: var(--info);"><?= e($h['recorder_name'] ?: '-') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan Halaman <?= $page ?> dari <?= $totalPages ?> (Total: <?= $totalRecords ?> Catatan)
                </div>
                <nav class="pagination-nav">
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">&larr;</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>">&rarr;</a>
                </nav>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-secondary); padding: 20px 0;">Tidak ada riwayat absensi ditemukan.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
