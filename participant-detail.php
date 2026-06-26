<?php
/**
 * Participant Full Detail View
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php'; // requires auth

$db = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdmin = ($_SESSION['role'] === 'admin');

// Fetch participant with training days
$stmt = $db->prepare("SELECT p.*, GROUP_CONCAT(td.day_name ORDER BY td.id SEPARATOR ', ') as training_days
                      FROM participants p
                      LEFT JOIN participant_training_days ptd ON p.id = ptd.participant_id
                      LEFT JOIN training_days td ON ptd.training_day_id = td.id
                      WHERE p.id = ?
                      GROUP BY p.id");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    setFlashMessage('danger', 'Data peserta tidak ditemukan.');
    header("Location: participants.php");
    exit;
}

// Calculate age
$birthDate = new DateTime($p['birth_date']);
$today = new DateTime();
$age = $today->diff($birthDate)->y;
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <a href="participants.php" class="btn btn-secondary btn-sm">&larr; Kembali ke Daftar</a>
    
    <div style="display: flex; gap: 8px;">
        <a href="participant-print.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-secondary">
            <!-- Print Icon -->
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Cetak Biodata
        </a>
        <a href="participant-edit.php?id=<?= $p['id'] ?>" class="btn btn-primary" style="background-color: var(--info); box-shadow: none;">
            Edit Data
        </a>
        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-danger" onclick="confirmAction('Hapus Peserta', 'Apakah Anda yakin ingin menghapus peserta bernama <?= e(addslashes($p['name'])) ?>? Tindakan ini tidak dapat dibatalkan.', 'participant-delete.php?id=<?= $p['id'] ?>')">
                Hapus
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Header Card Profile Summary -->
<div class="data-card">
    <div class="profile-detail-header" style="margin-bottom: 0;">
        <?php if ($p['photo']): ?>
            <img src="uploads/participants/<?= e($p['photo']) ?>" alt="<?= e($p['name']) ?>" class="profile-detail-avatar">
        <?php else: ?>
            <div class="profile-detail-avatar" style="display: flex; align-items: center; justify-content: center; font-size: 4rem; font-weight: 700; color: var(--accent); background-color: var(--bg-primary);">
                <?= strtoupper(substr($p['name'], 0, 1)) ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-detail-title">
            <h1><?= e($p['name']) ?></h1>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?= renderStatusBadge($p['status'] === 'active' ? 'Aktif' : 'Nonaktif') ?>
            </div>
        </div>
    </div>
</div>

<!-- Details Grid -->
<div class="profile-detail-grid">
    
    <!-- Personal Details -->
    <div class="detail-section">
        <h3 class="detail-section-title">Detail Pribadi</h3>
        <div class="detail-list">
            <div class="detail-row">
                <span class="detail-label">Jenis Kelamin</span>
                <span class="detail-val"><?= $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tempat Lahir</span>
                <span class="detail-val"><?= e($p['birth_place'] ?: '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tanggal Lahir</span>
                <span class="detail-val"><?= formatIndoDate($p['birth_date']) ?> (<?= $age ?> tahun)</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tinggi Badan</span>
                <span class="detail-val"><?= $p['height'] ? $p['height'] . ' cm' : '-' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Berat Badan</span>
                <span class="detail-val"><?= $p['weight'] ? $p['weight'] . ' kg' : '-' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Asal Sekolah</span>
                <span class="detail-val"><?= e($p['school_name'] ?: '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">No HP / WhatsApp</span>
                <span class="detail-val" style="color: var(--accent);"><?= e($p['phone'] ?: '-') ?></span>
            </div>
            <div class="detail-row" style="flex-direction: column; align-items: flex-start; gap: 6px;">
                <span class="detail-label">Alamat Lengkap</span>
                <span class="detail-val" style="text-align: left; font-weight: 500; font-size: 0.95rem; line-height: 1.4; color: var(--text-primary);"><?= e($p['address'] ?: '-') ?></span>
            </div>
        </div>
    </div>

    <!-- Basketball & Schedule Info -->
    <div style="display: flex; flex-direction: column; gap: 30px;">
        
        <div class="detail-section">
            <h3 class="detail-section-title">Jadwal & Pengalaman</h3>
            <div class="detail-list">
                <div class="detail-row">
                    <span class="detail-label">Hari Latihan</span>
                    <span class="detail-val" style="color: var(--accent);"><?= $p['training_days'] ? e($p['training_days']) : '-' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Pengalaman Basket</span>
                    <span class="detail-val"><?= e($p['basketball_experience']) ?></span>
                </div>
                <?php if ($p['basketball_experience'] === 'Ya'): ?>
                    <div class="detail-row">
                        <span class="detail-label">Klub Sebelumnya</span>
                        <span class="detail-val"><?= e($p['previous_club'] ?: '-') ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-section">
            <h3 class="detail-section-title">Orang Tua / Wali</h3>
            <div class="detail-list">
                <div class="detail-row">
                    <span class="detail-label">Nama Orang Tua</span>
                    <span class="detail-val"><?= e($p['parent_name'] ?: '-') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Pekerjaan</span>
                    <span class="detail-val"><?= e($p['parent_job'] ?: '-') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">No HP Wali</span>
                    <span class="detail-val" style="color: var(--accent);"><?= e($p['parent_phone'] ?: '-') ?></span>
                </div>
            </div>
        </div>
        
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
