<?php
/**
 * Coach List Management Page
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php'; // requires auth

// Check authorization (Admin only)
requireAdmin();

$db = getDBConnection();

// Fetch coaches with training days schedule and user credentials
$sql = "SELECT c.*, u.username, u.email, GROUP_CONCAT(td.day_name ORDER BY td.id SEPARATOR ', ') as training_days
        FROM coaches c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN coach_training_days ctd ON c.id = ctd.coach_id
        LEFT JOIN training_days td ON ctd.training_day_id = td.id
        GROUP BY c.id
        ORDER BY c.created_at DESC";
        
$coaches = $db->query($sql)->fetchAll();
?>

<!-- Add button toolbar -->
<div class="card-actions" style="margin-bottom: 24px;">
    <a href="coach-add.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Tambah Pelatih
    </a>
</div>

<!-- Coach List Table -->
<div class="data-card">
    <?php if (count($coaches) > 0): ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Foto</th>
                        <th>Nama Lengkap</th>
                        <th>Username / Email</th>
                        <th>No HP</th>
                        <th>Jadwal Melatih</th>
                        <th>Status</th>
                        <th style="text-align: center; width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coaches as $c): ?>
                        <tr>
                            <td>
                                <?php if ($c['photo']): ?>
                                    <img src="uploads/coaches/<?= e($c['photo']) ?>" alt="<?= e($c['name']) ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background-color: var(--bg-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--accent); border: 1px solid rgba(255,255,255,0.05);">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e($c['name']) ?></strong></td>
                            <td>
                                <div style="font-size: 0.9rem; font-weight: 600; color: var(--accent);"><?= e($c['username']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($c['email']) ?></div>
                            </td>
                            <td><?= e($c['phone']) ?></td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <?= $c['training_days'] ? e($c['training_days']) : '-' ?>
                                </span>
                            </td>
                            <td><?= renderStatusBadge($c['status'] === 'active' ? 'Aktif' : 'Nonaktif') ?></td>
                            <td>
                                <div class="action-buttons" style="justify-content: center;">
                                    <a href="coach-edit.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm btn-icon" style="background-color: var(--info); box-shadow: none;" title="Edit Data">
                                        <!-- Edit Icon -->
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                    
                                    <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="confirmAction('Hapus Pelatih', 'Apakah Anda yakin ingin menghapus pelatih <?= e(addslashes($c['name'])) ?>? Akun login pelatih ini juga akan dihapus secara otomatis.', 'coach-delete.php?id=<?= $c['id'] ?>&csrf_token=<?= generateCSRFToken() ?>')" title="Hapus">
                                        <!-- Trash Icon -->
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            <line x1="10" y1="11" x2="10" y2="17"/>
                                            <line x1="14" y1="11" x2="14" y2="17"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-secondary); padding: 20px 0;">Tidak ada data pelatih ditemukan.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
