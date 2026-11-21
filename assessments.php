<?php
/**
 * Participant Assessments List & Rekap (GPAI)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$db = getDBConnection();

$isAdmin = ($_SESSION['role'] === 'admin');
$currentUserId = $_SESSION['user_id'];

// Filter inputs
$search = trim($_GET['search'] ?? '');

// Base query
$queryStr = "SELECT pa.*, p.name as participant_name, p.photo as participant_photo, p.gender as participant_gender, p.school_name
             FROM participant_assessments pa
             JOIN participants p ON pa.participant_id = p.id
             WHERE 1=1";
$params = [];

if (!empty($search)) {
    $queryStr .= " AND p.name LIKE ?";
    $params[] = '%' . $search . '%';
}

$queryStr .= " ORDER BY pa.assessment_date DESC, pa.id DESC";

// Export to CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $stmtExp = $db->prepare($queryStr);
    $stmtExp->execute($params);
    $records = $stmtExp->fetchAll();
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $filename = "Rekap_Penilaian_GPAI_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM
    
    fputcsv($output, [
        'Tanggal Observasi',
        'Nama Atlet',
        'Jenis Kelamin',
        'Asal Sekolah',
        'Coach Penilai',
        'Chest Pass (Awalan)',
        'Chest Pass (Akurasi)',
        'Dribbling (Kontrol)',
        'Dribbling (Pandangan)',
        'Shooting (Siku)',
        'Shooting (Follow-Through)',
        'Skor Efektif (E)',
        'Skor In-Efektif (IE)',
        'Nilai Akhir (%)',
        'Predikat',
        'Catatan Pelatih'
    ]);
    
    foreach ($records as $r) {
        fputcsv($output, [
            $r['assessment_date'],
            $r['participant_name'],
            $r['participant_gender'] === 'L' ? 'Laki-laki' : 'Perempuan',
            $r['school_name'] ?: '-',
            $r['coach_name'],
            $r['chest_pass_prep'],
            $r['chest_pass_acc'],
            $r['dribble_control'],
            $r['dribble_vision'],
            $r['shooting_elbow'],
            $r['shooting_wrist'],
            $r['score_e'],
            $r['score_ie'],
            $r['final_score'],
            $r['predicate'],
            $r['notes'] ?: '-'
        ]);
    }
    
    fclose($output);
    exit;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total
$stmtCount = $db->prepare("SELECT COUNT(*) FROM ($queryStr) AS total_count");
$stmtCount->execute($params);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch records with limit
$queryStrLimit = $queryStr . " LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($queryStrLimit);
$stmt->execute($params);
$assessments = $stmt->fetchAll();

// Metrics summary (hanya atlet dinilai)
$totalAssessedParticipants = (int)$db->query("SELECT COUNT(DISTINCT participant_id) FROM participant_assessments")->fetchColumn();

$pageTitle = "Penilaian Atlet";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Action Bar -->
<div class="card-actions" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Penilaian Atlet (GPAI)
        </h2>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">
            Game Performance Assessment Instrument &middot; Evaluasi Kemampuan Bola Basket
        </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="assessment-add" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>Input Penilaian Baru</span>
        </a>
        
        <?php if ($isAdmin): ?>
        <a href="assessment-settings" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;" title="Pengaturan Kriteria Penilaian">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            <span>Pengaturan Kriteria</span>
        </a>
        <?php endif; ?>

        <?php if ($totalRecords > 0): ?>
            <?php 
                $exportParams = $_GET;
                $exportParams['action'] = 'export';
                $exportUrl = '?' . http_build_query($exportParams);
            ?>
            <a href="<?= e($exportUrl) ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span>Export CSV</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary & Filter Bar -->
<div class="data-card" style="margin-bottom: 24px; padding: 18px 22px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px;">
        <!-- Compact Stat Badge -->
        <div style="display: flex; align-items: center; gap: 14px; padding-right: 22px; border-right: 1px solid var(--bg-tertiary);">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 22px; height: 22px;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div>
                <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em;">
                    Atlet Dinilai
                </div>
                <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 2px;">
                    <span style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); line-height: 1.1;">
                        <?= number_format($totalAssessedParticipants) ?>
                    </span>
                    <span style="font-size: 0.82rem; font-weight: 600; color: var(--accent);">
                        Peserta dengan evaluasi
                    </span>
                </div>
            </div>
        </div>

        <!-- Search & Filter Form -->
        <form action="assessments" method="GET" class="search-filter-form" style="flex: 1; min-width: 260px; margin: 0;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="search" class="form-control" placeholder="Cari Nama Atlet ..." value="<?= e($search) ?>" style="flex: 1; min-width: 180px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="assessments" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="data-card">
    <?php if (count($assessments) > 0): ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Nama Atlet</th>
                        <th>Coach Penilai</th>
                        <th style="text-align: center;">Skor (E)</th>
                        <th style="text-align: center;">Nilai</th>
                        <th>Predikat</th>
                        <th style="text-align: right; width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = $offset + 1; ?>
                    <?php foreach ($assessments as $a): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong><?= formatIndoDate($a['assessment_date']) ?></strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($a['participant_photo'])): ?>
                                        <img src="<?= e(getPhotoSrc($a['participant_photo'], 'uploads/participants/')) ?>" alt="<?= e($a['participant_name']) ?>" style="width: 34px; height: 34px; border-radius: 6px; object-fit: cover; border: 1px solid rgba(255,255,255,0.1);">
                                    <?php else: ?>
                                        <div style="width: 34px; height: 34px; border-radius: 6px; background-color: var(--bg-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--accent); font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.05);">
                                            <?= strtoupper(substr($a['participant_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="participant-detail.php?id=<?= $a['participant_id'] ?>" style="font-weight: 700; color: var(--text-primary); text-decoration: none;">
                                            <?= e($a['participant_name']) ?>
                                        </a>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                            <?= $a['participant_gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?> <?= !empty($a['school_name']) ? '&middot; ' . e($a['school_name']) : '' ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--text-primary);"><?= e($a['coach_name']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <strong style="color: var(--accent); font-size: 0.95rem;"><?= $a['score_e'] ?></strong>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);"> / <?= ($a['score_e'] + $a['score_ie']) ?: 6 ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span style="font-weight: 700; font-size: 0.95rem;"><?= number_format($a['final_score'], 0) ?>%</span>
                            </td>
                            <td>
                                <?= renderPredicateBadge($a['predicate']) ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                    <a href="assessment-detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" title="Lihat Lembar Observasi" style="padding: 6px 9px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </a>
                                    
                                    <a href="assessment-print.php?id=<?= $a['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Cetak Lembar Observasi" style="padding: 6px 9px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                            <polyline points="6 9 6 2 18 2 18 9"/>
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                            <rect x="6" y="14" width="12" height="8"/>
                                        </svg>
                                    </a>

                                    <?php if ($isAdmin || $a['user_id'] == $currentUserId): ?>
                                        <a href="assessment-edit.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" title="Edit" style="padding: 6px 9px;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>

                                        <button type="button" class="btn btn-danger btn-sm" title="Hapus" style="padding: 6px 9px;" onclick="confirmAction('Hapus Penilaian', 'Apakah Anda yakin ingin menghapus data lembar penilaian untuk <?= e(addslashes($a['participant_name'])) ?> tanggal <?= e(formatIndoDate($a['assessment_date'])) ?>?', 'assessment-delete.php?id=<?= $a['id'] ?>&csrf_token=<?= generateCSRFToken() ?>')">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
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
            <div class="pagination-wrapper" style="margin-top: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    Menampilkan <?= $offset + 1 ?> &ndash; <?= min($offset + $limit, $totalRecords) ?> dari <?= $totalRecords ?> data penilaian
                </div>
                <div class="pagination-nav" style="display: flex; gap: 6px;">
                    <?= renderPaginationLinks($page, $totalPages, $_GET) ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div style="text-align: center; padding: 48px 16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 56px; height: 56px; color: var(--text-secondary); margin-bottom: 16px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
                Belum Ada Data Penilaian
            </h3>
            <p style="color: var(--text-secondary); font-size: 0.9rem; max-width: 420px; margin: 0 auto 20px;">
                Data lembar observasi GPAI peserta belum tersedia atau tidak cocok dengan filter pencarian Anda.
            </p>
            <a href="assessment-add" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Input Penilaian Sekarang</span>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
