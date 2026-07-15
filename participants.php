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
<div class="card-actions" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <div style="display: flex; gap: 10px;">
        <a href="participant-add" class="btn btn-primary">
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
        <a href="participant-import" class="btn btn-secondary">
            <!-- Upload/Import Icon -->
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Impor Excel
        </a>
        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-danger" onclick="confirmAction('Hapus Semua Peserta', 'Apakah Anda yakin ingin menghapus SELURUH data peserta dari sistem? Tindakan ini akan menghapus semua biodata, riwayat latihan, dan daftar kehadiran peserta, serta tidak dapat dibatalkan.', 'participant-delete-all.php?csrf_token=<?= generateCSRFToken() ?>')">
                <!-- Trash Icon -->
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
                Hapus Semua Peserta
            </button>
        <?php endif; ?>
    </div>

    <!-- Search Bar - Paling Kanan -->
    <form action="participants" method="GET" id="search-form" style="display: flex; gap: 8px; align-items: center;">
        <!-- Preserve hidden filter values from column headers -->
        <?php if (!empty($gender)): ?><input type="hidden" name="gender" value="<?= e($gender) ?>"><?php endif; ?>
        <?php if (!empty($status)): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>

        <input type="text" name="search" class="form-control" placeholder="Cari nama peserta..." value="<?= e($search) ?>" style="width: 220px;">
        <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Cari
        </button>
        <?php if (!empty($search) || !empty($gender) || !empty($status) || $sort !== 'newest'): ?>
            <a href="participants" class="btn btn-secondary" style="white-space: nowrap;">Reset</a>
        <?php endif; ?>
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

                        <!-- Gender Filter Header -->
                        <th>
                            <div style="display: flex; flex-direction: column; gap: 3px;">
                                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                                    <span>Gender</span>
                                    <div class="col-filter-wrap" style="position: relative; display: inline-block;">
                                        <button type="button" class="col-filter-btn <?= !empty($gender) ? 'col-filter-btn--active' : '' ?>" onclick="toggleColFilter('filter-gender')" title="Filter Gender">
                                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                        </button>
                                        <div id="filter-gender" class="col-filter-dropdown" style="display:none;">
                                            <?php
                                            $genderParams = array_merge($_GET, ['gender' => '']);
                                            $genderParamsL = array_merge($_GET, ['gender' => 'L']);
                                            $genderParamsP = array_merge($_GET, ['gender' => 'P']);
                                            unset($genderParams['page'], $genderParamsL['page'], $genderParamsP['page']);
                                            ?>
                                            <a href="participants.php?<?= http_build_query($genderParams) ?>" class="col-filter-option <?= empty($gender) ? 'active' : '' ?>">Semua</a>
                                            <a href="participants.php?<?= http_build_query($genderParamsL) ?>" class="col-filter-option <?= $gender === 'L' ? 'active' : '' ?>">Laki-laki</a>
                                            <a href="participants.php?<?= http_build_query($genderParamsP) ?>" class="col-filter-option <?= $gender === 'P' ? 'active' : '' ?>">Perempuan</a>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($gender)): ?>
                                    <a href="participants.php?<?= http_build_query(array_merge($_GET, ['gender' => ''])) ?>" class="col-filter-badge">
                                        <?= $gender === 'L' ? 'Laki-laki' : 'Perempuan' ?> ✕
                                    </a>
                                <?php endif; ?>
                            </div>
                        </th>

                        <!-- Tanggal Lahir Sort Header -->
                        <th>
                            <div style="display: flex; flex-direction: column; gap: 3px;">
                                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                                    <span>Tanggal Lahir</span>
                                    <div class="col-filter-wrap" style="position: relative; display: inline-block;">
                                        <button type="button" class="col-filter-btn <?= $sort !== 'newest' ? 'col-filter-btn--active' : '' ?>" onclick="toggleColFilter('filter-sort')" title="Urut Tanggal Lahir">
                                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                        </button>
                                        <div id="filter-sort" class="col-filter-dropdown" style="display:none;">
                                            <?php
                                            $sortParamsDefault = array_merge($_GET, ['sort' => 'newest']);
                                            $sortParamsYoung  = array_merge($_GET, ['sort' => 'youngest']);
                                            $sortParamsOld    = array_merge($_GET, ['sort' => 'oldest']);
                                            unset($sortParamsDefault['page'], $sortParamsYoung['page'], $sortParamsOld['page']);
                                            ?>
                                            <a href="participants.php?<?= http_build_query($sortParamsDefault) ?>" class="col-filter-option <?= $sort === 'newest' ? 'active' : '' ?>">Tidak ada</a>
                                            <a href="participants.php?<?= http_build_query($sortParamsYoung) ?>"  class="col-filter-option <?= $sort === 'youngest' ? 'active' : '' ?>">Termuda</a>
                                            <a href="participants.php?<?= http_build_query($sortParamsOld) ?>"   class="col-filter-option <?= $sort === 'oldest' ? 'active' : '' ?>">Tertua</a>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($sort !== 'newest'): ?>
                                    <?php $sortParamsReset = array_merge($_GET, ['sort' => 'newest']); unset($sortParamsReset['page']); ?>
                                    <a href="participants.php?<?= http_build_query($sortParamsReset) ?>" class="col-filter-badge">
                                        <?= $sort === 'youngest' ? 'Termuda' : 'Tertua' ?> ✕
                                    </a>
                                <?php endif; ?>
                            </div>
                        </th>

                        <th>Hari Latihan</th>

                        <!-- Status Filter Header -->
                        <th>
                            <div style="display: flex; flex-direction: column; gap: 3px;">
                                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                                    <span>Status</span>
                                    <div class="col-filter-wrap" style="position: relative; display: inline-block;">
                                        <button type="button" class="col-filter-btn <?= !empty($status) ? 'col-filter-btn--active' : '' ?>" onclick="toggleColFilter('filter-status')" title="Filter Status">
                                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                        </button>
                                        <div id="filter-status" class="col-filter-dropdown" style="display:none;">
                                            <?php
                                            $statusParamsAll      = array_merge($_GET, ['status' => '']);
                                            $statusParamsActive   = array_merge($_GET, ['status' => 'active']);
                                            $statusParamsInactive = array_merge($_GET, ['status' => 'inactive']);
                                            unset($statusParamsAll['page'], $statusParamsActive['page'], $statusParamsInactive['page']);
                                            ?>
                                            <a href="participants.php?<?= http_build_query($statusParamsAll) ?>"      class="col-filter-option <?= empty($status) ? 'active' : '' ?>">Semua</a>
                                            <a href="participants.php?<?= http_build_query($statusParamsActive) ?>"   class="col-filter-option <?= $status === 'active' ? 'active' : '' ?>">Aktif</a>
                                            <a href="participants.php?<?= http_build_query($statusParamsInactive) ?>" class="col-filter-option <?= $status === 'inactive' ? 'active' : '' ?>">Tidak Aktif</a>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($status)): ?>
                                    <?php $statusParamsReset = array_merge($_GET, ['status' => '']); unset($statusParamsReset['page']); ?>
                                    <a href="participants.php?<?= http_build_query($statusParamsReset) ?>" class="col-filter-badge">
                                        <?= $status === 'active' ? 'Aktif' : 'Tidak Aktif' ?> ✕
                                    </a>
                                <?php endif; ?>
                            </div>
                        </th>

                        <th style="text-align: center; width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['photo']): ?>
                                    <img src="<?= e(getPhotoSrc($p['photo'], 'uploads/participants/')) ?>" alt="<?= e($p['name']) ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background-color: var(--bg-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--accent); border: 1px solid rgba(255,255,255,0.05);">
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td><?= $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                            <td><?= $p['birth_date'] ? formatIndoDate($p['birth_date']) : '-' ?></td>
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

<!-- Column Filter Styles & Script -->
<style>
/* Filter button (chevron icon) */
.col-filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    background: transparent;
    border: 1px solid var(--bg-tertiary);
    border-radius: 4px;
    cursor: pointer;
    color: var(--text-secondary);
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    flex-shrink: 0;
}
.col-filter-btn:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}
.col-filter-btn--active {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}
.col-filter-btn--active:hover {
    background: var(--accent);
    color: #fff;
    opacity: 0.85;
}

/* Floating dropdown panel */
.col-filter-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-secondary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.35);
    min-width: 130px;
    z-index: 999;
    overflow: hidden;
    animation: dropdownFade 0.12s ease;
}
@keyframes dropdownFade {
    from { opacity: 0; transform: translateX(-50%) translateY(-4px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* Each option link */
.col-filter-option {
    display: block;
    padding: 9px 14px;
    font-size: 0.82rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: background 0.12s, color 0.12s;
    white-space: nowrap;
}
.col-filter-option:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}
.col-filter-option.active {
    color: var(--accent);
    font-weight: 600;
    background: rgba(var(--accent-rgb, 99, 179, 237), 0.08);
}

/* Active filter badge below column name */
.col-filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 2px 7px;
    border-radius: 20px;
    background: var(--accent);
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    letter-spacing: 0.02em;
    transition: opacity 0.15s;
    cursor: pointer;
    white-space: nowrap;
    align-self: flex-start;
}
.col-filter-badge:hover {
    opacity: 0.8;
}
</style>

<script>
function toggleColFilter(id) {
    var all = ['filter-gender', 'filter-sort', 'filter-status'];
    all.forEach(function(filterId) {
        var el = document.getElementById(filterId);
        if (!el) return;
        if (filterId === id) {
            el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
        } else {
            el.style.display = 'none';
        }
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    var isInsideWrap = e.target.closest('.col-filter-wrap');
    if (!isInsideWrap) {
        ['filter-gender', 'filter-sort', 'filter-status'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
