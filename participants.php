<?php
/**
 * Participant List Management Page
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php'; // requires auth

$db = getDBConnection();
$isAdmin = ($_SESSION['role'] === 'admin');

// Fetch query filters
$search = trim($_GET['search'] ?? '');
$gender = $_GET['gender'] ?? '';
$day = $_GET['day'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Base SQL query
$queryStr = "SELECT p.*, GROUP_CONCAT(td.day_name ORDER BY td.id SEPARATOR ', ') as training_days
             FROM participants p
             LEFT JOIN participant_training_days ptd ON p.id = ptd.participant_id
             LEFT JOIN training_days td ON ptd.training_day_id = td.id
             WHERE 1=1";
$params = [];

if (!empty($search)) {
    $queryStr .= " AND (p.name LIKE ? OR p.registration_number LIKE ?)";
    $params[] = "%{$search}%";
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

// Pagination logic
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch total records count
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM ($queryStr) AS sub");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch page items
$queryStr .= " LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($queryStr);
$stmt->execute($params);
$participants = $stmt->fetchAll();

// Fetch auxiliary options
$trainingDays = $db->query("SELECT * FROM training_days ORDER BY id ASC")->fetchAll();
?>

<!-- Header Toolbar Actions -->
<div class="card-actions" style="margin-bottom: 24px;">
    <div style="display: flex; gap: 10px;">
        <a href="participant-add.php" class="btn btn-primary">
            <!-- Add icon -->
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Peserta
        </a>
        <a href="participant-export.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary">
            <!-- Excel Icon -->
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
            </svg>
            Ekspor Excel
        </a>
        <a href="participant-import.php" class="btn btn-secondary">
            <!-- Upload/Import Icon -->
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Impor Excel
        </a>
    </div>
</div>

<!-- Filters Panel -->
<div class="data-card">
    <form action="participants.php" method="GET">
        <div class="card-actions" style="margin-bottom: 0;">
            <div class="search-filter-box">
                <input type="text" name="search" class="form-control" placeholder="Cari Nama ..." value="<?= e($search) ?>" style="max-width: 250px;">
                
                <select name="gender" class="form-control" style="max-width: 150px;">
                    <option value="">Semua Gender</option>
                    <option value="L" <?= $gender === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= $gender === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
                

                <select name="day" class="form-control" style="max-width: 150px;">
                    <option value="">Hari Latihan</option>
                    <?php foreach ($trainingDays as $td): ?>
                        <option value="<?= $td['id'] ?>" <?= (string)$day === (string)$td['id'] ? 'selected' : '' ?>><?= e($td['day_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="form-control" style="max-width: 150px;">
                    <option value="">Semua Status</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
                
                <select name="sort" class="form-control" style="max-width: 180px;">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Pendaftaran Terbaru</option>
                    <option value="youngest" <?= $sort === 'youngest' ? 'selected' : '' ?>>Termuda</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Tertua</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="participants.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>
</div>

<!-- Participant List Table -->
<div class="data-card">
    <?php if (count($participants) > 0): ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Foto</th>
                        <th>Nama Lengkap</th>
                        <th>Gender</th>
                        <th>Tanggal Lahir</th>
                        <th>Hari Latihan</th>
                        <th>Status</th>
                        <th style="text-align: center; width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['photo']): ?>
                                    <img src="uploads/participants/<?= e($p['photo']) ?>" alt="<?= e($p['name']) ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background-color: var(--bg-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--accent); border: 1px solid rgba(255,255,255,0.05);">
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td><?= $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                            <td><?= formatIndoDate($p['birth_date']) ?></td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <?= $p['training_days'] ? e($p['training_days']) : '-' ?>
                                </span>
                            </td>
                            <td><?= renderStatusBadge($p['status'] === 'active' ? 'Aktif' : 'Nonaktif') ?></td>
                            <td>
                                <div class="action-buttons" style="justify-content: center;">
                                    <a href="participant-detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Detail Lengkap">
                                        <!-- Eye Icon -->
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </a>
                                    
                                    <a href="participant-edit.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm btn-icon" style="background-color: var(--info); box-shadow: none;" title="Edit Data">
                                        <!-- Edit Icon -->
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                    
                                    <a href="participant-print.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon" title="Cetak Biodata">
                                        <!-- Print Icon -->
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6 9 6 2 18 2 18 9"/>
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                            <rect x="6" y="14" width="12" height="8"/>
                                        </svg>
                                    </a>
                                    
                                    <?php if ($isAdmin): ?>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="confirmAction('Hapus Peserta', 'Apakah Anda yakin ingin menghapus peserta bernama <?= e(addslashes($p['name'])) ?>? Tindakan ini tidak dapat dibatalkan.', 'participant-delete.php?id=<?= $p['id'] ?>&csrf_token=<?= generateCSRFToken() ?>')" title="Hapus">
                                            <!-- Trash Icon -->
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                <line x1="10" y1="11" x2="10" y2="17"/>
                                                <line x1="14" y1="11" x2="14" y2="17"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
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
                    Menampilkan Halaman <?= $page ?> dari <?= $totalPages ?> (Total: <?= $totalRecords ?> Peserta)
                </div>
                <nav class="pagination-nav">
                    <?= renderPaginationLinks($page, $totalPages, $_GET) ?>
                </nav>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <p style="text-align: center; color: var(--text-secondary); padding: 20px 0;">Tidak ada data peserta ditemukan.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
