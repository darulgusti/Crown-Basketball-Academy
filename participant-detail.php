<?php
/**
 * Participant Full Detail View
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php'; // requires auth

$db = getDBConnection();
ensureAssessmentTables($db);
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

// Fetch participant assessments
$stmtAssess = $db->prepare("SELECT * FROM participant_assessments WHERE participant_id = ? ORDER BY assessment_date DESC, id DESC");
$stmtAssess->execute([$id]);
$participantAssessments = $stmtAssess->fetchAll();
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <a href="participants" class="btn btn-secondary btn-sm">&larr; Kembali ke Daftar</a>
    
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="assessment-add.php?participant_id=<?= $p['id'] ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                <path d="M9 14l2 2 4-4"/>
            </svg>
            <span>+ Nilai Atlet Ini</span>
        </a>

        <a href="participant-edit.php?id=<?= $p['id'] ?>" class="btn btn-primary" style="background-color: var(--info); box-shadow: none;">
            Edit Data
        </a>
        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-danger" onclick="confirmAction('Hapus Peserta', 'Apakah Anda yakin ingin menghapus peserta bernama <?= e(addslashes($p['name'])) ?>? Tindakan ini tidak dapat dibatalkan.', 'participant-delete.php?id=<?= $p['id'] ?>&csrf_token=<?= generateCSRFToken() ?>')">
                Hapus
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Header Card Profile Summary -->
<div class="data-card">
    <div class="profile-detail-header" style="margin-bottom: 0;">
        <?php if ($p['photo']): ?>
            <img src="<?= e(getPhotoSrc($p['photo'], 'uploads/participants/')) ?>" alt="<?= e($p['name']) ?>" class="profile-detail-avatar">
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

<!-- Riwayat Penilaian Atlet (GPAI) -->
<div class="data-card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0 0 4px;">
                Riwayat Penilaian Kemampuan (GPAI)
            </h3>
            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                Catatan evaluasi skill execution (Chest Pass, Dribbling, Shooting)
            </div>
        </div>
        <a href="assessment-add.php?participant_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>+ Buat Penilaian Baru</span>
        </a>
    </div>

    <?php if (!empty($participantAssessments)): ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Tanggal Observasi</th>
                        <th>Coach Penilai</th>
                        <th style="text-align: center;">Skor (E)</th>
                        <th style="text-align: center;">Nilai</th>
                        <th>Predikat</th>
                        <th>Catatan Evaluasi</th>
                        <th style="text-align: right; width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participantAssessments as $pa): ?>
                        <tr>
                            <td><strong><?= formatIndoDate($pa['assessment_date']) ?></strong></td>
                            <td><?= e($pa['coach_name']) ?></td>
                            <td style="text-align: center;">
                                <strong style="color: var(--accent);"><?= $pa['score_e'] ?></strong>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);"> / <?= ($pa['score_e'] + $pa['score_ie']) ?: 6 ?></span>
                            </td>
                            <td style="text-align: center;">
                                <strong><?= number_format($pa['final_score'], 0) ?>%</strong>
                            </td>
                            <td><?= renderPredicateBadge($pa['predicate']) ?></td>
                            <td style="max-width: 240px; font-size: 0.85rem; color: var(--text-secondary); white-space: normal;">
                                <?= $pa['notes'] ? e($pa['notes']) : '-' ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                    <a href="assessment-detail.php?id=<?= $pa['id'] ?>" class="btn btn-secondary btn-sm" title="Lihat Lembar Observasi" style="padding: 5px 8px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </a>
                                    <a href="assessment-print.php?id=<?= $pa['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Cetak Lembar Observasi" style="padding: 5px 8px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                            <polyline points="6 9 6 2 18 2 18 9"/>
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                            <rect x="6" y="14" width="12" height="8"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 28px 16px; background-color: var(--bg-primary); border-radius: 8px; border: 1px solid var(--bg-tertiary);">
            <p style="color: var(--text-secondary); margin: 0 0 12px; font-size: 0.9rem;">
                Atlet ini belum memiliki riwayat lembar observasi GPAI.
            </p>
            <a href="assessment-add.php?participant_id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">
                + Input Penilaian Pertama
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
