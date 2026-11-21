<?php
/**
 * Assessment Criteria Settings
 * Crown Basketball Academy
 * 
 * Halaman pengaturan kriteria GPAI - hanya admin yang dapat mengakses.
 * Mendukung tambah, ubah, dan hapus kelompok kriteria serta indikator penilaian secara dinamis.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

requireAuth();

// Hanya admin yang boleh akses
if ($_SESSION['role'] !== 'admin') {
    setFlashMessage('danger', 'Halaman ini hanya dapat diakses oleh administrator.');
    header("Location: assessments.php");
    exit;
}

$db = getDBConnection();

// Pastikan tabel assessment_groups dan assessment_criteria ada
$db->exec("CREATE TABLE IF NOT EXISTS assessment_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

// Pastikan kolom group_id ada jika tabel assessment_criteria sudah ada sebelumnya
$hasGroupIdCol = $db->query("SHOW COLUMNS FROM assessment_criteria LIKE 'group_id'")->rowCount() > 0;
if (!$hasGroupIdCol) {
    $db->exec("ALTER TABLE assessment_criteria ADD COLUMN group_id INT NULL AFTER id");
}

// Pastikan slot_key nullable
try {
    $db->exec("ALTER TABLE assessment_criteria MODIFY COLUMN slot_key VARCHAR(50) NULL");
} catch (Exception $e) {}

// Pastikan participant_assessments memiliki criteria_data
$hasCriteriaDataCol = $db->query("SHOW COLUMNS FROM participant_assessments LIKE 'criteria_data'")->rowCount() > 0;
if (!$hasCriteriaDataCol) {
    $db->exec("ALTER TABLE participant_assessments ADD COLUMN criteria_data LONGTEXT NULL DEFAULT NULL AFTER assessment_date");
}

// Inisialisasi data default jika kedua tabel masih kosong
$groupCount = (int)$db->query("SELECT COUNT(*) FROM assessment_groups")->fetchColumn();
$criteriaCount = (int)$db->query("SELECT COUNT(*) FROM assessment_criteria")->fetchColumn();

if ($groupCount === 0) {
    if ($criteriaCount > 0) {
        // Sync dari criteria yang sudah ada
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
        // Seed default 3 kelompok & 6 kriteria
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

$errors = [];

// Handle POST: Simpan perubahan struktur kelompok dan kriteria
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $submittedGroups = $_POST['groups'] ?? [];

    if (empty($submittedGroups) || !is_array($submittedGroups)) {
        $errors[] = "Minimal harus ada 1 kelompok penilaian.";
    } else {
        // Validasi data
        $validatedData = [];
        $totalCriteriaCount = 0;

        foreach ($submittedGroups as $gIdx => $gData) {
            $gName = trim($gData['name'] ?? '');
            $gId = !empty($gData['id']) ? (int)$gData['id'] : null;

            if (empty($gName)) {
                $errors[] = "Nama kelompok ke-" . ($gIdx + 1) . " tidak boleh kosong.";
                continue;
            }
            if (strlen($gName) > 100) {
                $errors[] = "Nama kelompok '$gName' terlalu panjang (maks. 100 karakter).";
                continue;
            }

            $rawCriteria = $gData['criteria'] ?? [];
            if (empty($rawCriteria) || !is_array($rawCriteria)) {
                $errors[] = "Kelompok '$gName' harus memiliki minimal 1 indikator kriteria penilaian.";
                continue;
            }

            $groupCriteria = [];
            foreach ($rawCriteria as $cIdx => $cData) {
                $cLabel = trim($cData['label'] ?? '');
                $cId = !empty($cData['id']) ? (int)$cData['id'] : null;
                $slotKey = trim($cData['slot_key'] ?? '');

                if (empty($cLabel)) {
                    $errors[] = "Deskripsi indikator kriteria ke-" . ($cIdx + 1) . " pada kelompok '$gName' tidak boleh kosong.";
                    continue;
                }

                $groupCriteria[] = [
                    'id' => $cId,
                    'label' => $cLabel,
                    'slot_key' => $slotKey,
                ];
                $totalCriteriaCount++;
            }

            if (empty($groupCriteria)) {
                $errors[] = "Kelompok '$gName' tidak memiliki indikator kriteria yang valid.";
                continue;
            }

            $validatedData[] = [
                'id' => $gId,
                'name' => $gName,
                'criteria' => $groupCriteria,
            ];
        }

        if ($totalCriteriaCount === 0) {
            $errors[] = "Minimal harus ada setidaknya 1 kriteria penilaian aktif.";
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $existingGroupIds = $db->query("SELECT id FROM assessment_groups")->fetchAll(PDO::FETCH_COLUMN);
                $existingCritIds = $db->query("SELECT id FROM assessment_criteria")->fetchAll(PDO::FETCH_COLUMN);

                $keptGroupIds = [];
                $keptCriteriaIds = [];

                $gOrder = 1;
                $cGlobalOrder = 1;

                foreach ($validatedData as $g) {
                    $groupId = null;
                    if ($g['id'] && in_array($g['id'], $existingGroupIds)) {
                        // Update kelompok yang sudah ada
                        $stmtG = $db->prepare("UPDATE assessment_groups SET name = ?, sort_order = ? WHERE id = ?");
                        $stmtG->execute([$g['name'], $gOrder, $g['id']]);
                        $groupId = $g['id'];
                    } else {
                        // Buat kelompok baru
                        $stmtG = $db->prepare("INSERT INTO assessment_groups (name, sort_order) VALUES (?, ?)");
                        $stmtG->execute([$g['name'], $gOrder]);
                        $groupId = (int)$db->lastInsertId();
                    }
                    $keptGroupIds[] = $groupId;
                    $gOrder++;

                    // Simpan kriteria di dalam kelompok ini
                    foreach ($g['criteria'] as $c) {
                        $critId = null;
                        if ($c['id'] && in_array($c['id'], $existingCritIds)) {
                            // Update kriteria yang sudah ada
                            $stmtC = $db->prepare("UPDATE assessment_criteria SET group_id = ?, category_name = ?, criterion_label = ?, sort_order = ? WHERE id = ?");
                            $stmtC->execute([$groupId, $g['name'], $c['label'], $cGlobalOrder, $c['id']]);
                            $critId = $c['id'];
                        } else {
                            // Buat kriteria baru
                            $slotKey = !empty($c['slot_key']) ? $c['slot_key'] : ('crit_' . bin2hex(random_bytes(6)));
                            $stmtC = $db->prepare("INSERT INTO assessment_criteria (group_id, slot_key, category_name, criterion_label, sort_order) VALUES (?, ?, ?, ?, ?)");
                            $stmtC->execute([$groupId, $slotKey, $g['name'], $c['label'], $cGlobalOrder]);
                            $critId = (int)$db->lastInsertId();
                        }
                        $keptCriteriaIds[] = $critId;
                        $cGlobalOrder++;
                    }
                }

                // Hapus kriteria yang tidak ada di daftar simpan
                if (!empty($keptCriteriaIds)) {
                    $inC = implode(',', array_map('intval', $keptCriteriaIds));
                    $db->exec("DELETE FROM assessment_criteria WHERE id NOT IN ($inC)");
                } else {
                    $db->exec("DELETE FROM assessment_criteria");
                }

                // Hapus kelompok yang tidak ada di daftar simpan
                if (!empty($keptGroupIds)) {
                    $inG = implode(',', array_map('intval', $keptGroupIds));
                    $db->exec("DELETE FROM assessment_groups WHERE id NOT IN ($inG)");
                } else {
                    $db->exec("DELETE FROM assessment_groups");
                }

                $db->commit();
                setFlashMessage('success', 'Kelompok dan kriteria penilaian GPAI berhasil diperbarui!');
                header("Location: assessment-settings.php");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Terjadi kesalahan saat menyimpan data: " . $e->getMessage();
            }
        }
    }
}

// Fetch semua kelompok dan kriteria dari DB
$groupsList = $db->query("SELECT * FROM assessment_groups ORDER BY sort_order ASC, id ASC")->fetchAll();
$totalCriteria = 0;
foreach ($groupsList as &$grp) {
    $stmtC = $db->prepare("SELECT * FROM assessment_criteria WHERE group_id = ? ORDER BY sort_order ASC, id ASC");
    $stmtC->execute([$grp['id']]);
    $grp['criteria'] = $stmtC->fetchAll();
    $totalCriteria += count($grp['criteria']);
}
unset($grp);

// Fallback jika belum ada kelompok terhubung
if (empty($groupsList)) {
    // Coba ambil dari assessment_criteria saja
    $allCrits = $db->query("SELECT * FROM assessment_criteria ORDER BY sort_order ASC, id ASC")->fetchAll();
    $grouped = [];
    foreach ($allCrits as $ac) {
        $cat = $ac['category_name'] ?: 'Kategori Penilaian';
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $ac;
    }
    $gIdx = 1;
    foreach ($grouped as $catName => $cList) {
        $groupsList[] = [
            'id' => null,
            'name' => $catName,
            'sort_order' => $gIdx++,
            'criteria' => $cList,
        ];
        $totalCriteria += count($cList);
    }
}

$pageTitle = "Pengaturan Kriteria Penilaian";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb / Back -->
<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <a href="assessments" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Kembali ke Penilaian Atlet</span>
        </a>
    </div>

    <div>
        <button type="button" class="btn btn-primary btn-sm" id="btn-top-add-group" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>+ Tambah Kelompok Baru</span>
        </button>
    </div>
</div>

<!-- Page Header -->
<div class="card-actions" style="margin-bottom: 24px;">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Pengaturan Kriteria Penilaian (GPAI)
        </h2>
        <div style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 4px;">
            Kelola kelompok skill dan indikator kriteria penilaian &middot; 
            <strong id="badge-total-groups" style="color: var(--accent);"><?= count($groupsList) ?></strong> kelompok &middot; 
            <strong id="badge-total-criteria" style="color: var(--accent);"><?= $totalCriteria ?></strong> indikator aktif
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px;">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form id="form-criteria-settings" action="assessment-settings.php" method="POST">
    <?= csrfField() ?>

    <!-- Container Semua Kelompok -->
    <div id="groups-container">
        <?php
        $letterList = range('A', 'Z');
        foreach ($groupsList as $gIdx => $group):
            $letter = $letterList[$gIdx % 26];
            $groupCriteria = $group['criteria'] ?? [];
        ?>
        <div class="data-card group-card" data-group-index="<?= $gIdx ?>" style="margin-bottom: 24px; border: 1px solid var(--bg-tertiary); border-radius: 12px; background: var(--bg-primary); padding: 22px;">
            <!-- Group Header -->
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid var(--bg-tertiary);">
                <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 260px;">
                    <div class="group-letter-badge" style="width: 38px; height: 38px; border-radius: 10px; background: rgba(249, 115, 22, 0.15); color: var(--accent); font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?= $letter ?>
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">
                            Nama Kelompok <?= $gIdx + 1 ?> / Kategori Skill <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="text"
                               name="groups[<?= $gIdx ?>][name]"
                               class="form-control group-name-input"
                               value="<?= e($group['name']) ?>"
                               maxlength="100"
                               required
                               placeholder="Contoh: Chest Pass (Umpan Dada)..."
                               style="font-weight: 700; font-size: 1.05rem; width: 100%;">
                        <input type="hidden" name="groups[<?= $gIdx ?>][id]" value="<?= e($group['id'] ?? '') ?>" class="group-id-input">
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <span class="badge badge-info group-crit-count" style="font-size: 0.82rem; padding: 6px 12px; border-radius: 20px;">
                        <?= count($groupCriteria) ?> Kriteria
                    </span>
                    <button type="button" class="btn btn-secondary btn-sm btn-add-crit-header" title="Tambah Kriteria ke Kelompok Ini" style="padding: 6px 10px; font-size: 0.82rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; vertical-align: middle;">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        <span>+ Kriteria</span>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm btn-remove-group" title="Hapus Seluruh Kelompok Ini" style="padding: 6px 10px; font-size: 0.82rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; vertical-align: middle;">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        <span>Hapus Kelompok</span>
                    </button>
                </div>
            </div>

            <!-- List Kriteria di dalam Kelompok -->
            <div class="criteria-list">
                <?php foreach ($groupCriteria as $cIdx => $crit): ?>
                <div class="criterion-item" data-crit-index="<?= $cIdx ?>" style="background-color: var(--bg-secondary); border: 1px solid var(--bg-tertiary); border-radius: 10px; padding: 16px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div class="crit-number-label" style="font-size: 0.82rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em;">
                            Kriteria <?= $cIdx + 1 ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger btn-remove-crit" title="Hapus Kriteria Ini" style="padding: 4px 8px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 4px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 13px; height: 13px;">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                            <span>Hapus</span>
                        </button>
                    </div>

                    <div>
                        <label style="font-weight: 600; font-size: 0.86rem; margin-bottom: 6px; display: block; color: var(--text-primary);">
                            Deskripsi Indikator Penilaian <span style="color: var(--danger);">*</span>
                            <span style="font-size: 0.75rem; font-weight: 400; color: var(--text-secondary);">(ditampilkan di form observasi saat menilai atlet)</span>
                        </label>
                        <textarea name="groups[<?= $gIdx ?>][criteria][<?= $cIdx ?>][label]"
                                  class="form-control crit-label-input"
                                  rows="2"
                                  required
                                  placeholder="Deskripsi indikator yang dinilai..."
                                  style="width: 100%; resize: vertical;"><?= e($crit['criterion_label']) ?></textarea>
                        <input type="hidden" name="groups[<?= $gIdx ?>][criteria][<?= $cIdx ?>][id]" value="<?= e($crit['id'] ?? '') ?>" class="crit-id-input">
                        <input type="hidden" name="groups[<?= $gIdx ?>][criteria][<?= $cIdx ?>][slot_key]" value="<?= e($crit['slot_key'] ?? '') ?>" class="crit-slot-input">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Group Footer: Tambah Kriteria Button -->
            <div style="margin-top: 14px; display: flex; justify-content: flex-start;">
                <button type="button" class="btn btn-secondary btn-sm btn-add-crit-bottom" style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>+ Tambah Kriteria di Kelompok Ini</span>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Bottom Actions Card -->
    <div style="background-color: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 12px; padding: 18px 24px; margin-top: 24px; margin-bottom: 36px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <button type="button" class="btn btn-secondary" id="btn-bottom-add-group" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>+ Tambah Kelompok Baru</span>
            </button>
        </div>

        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <a href="assessments" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" style="padding: 10px 28px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                <span>Simpan Perubahan Kriteria</span>
            </button>
        </div>
    </div>
</form>

<!-- Modal Konfirmasi Hapus Lokal -->
<div id="delete-confirm-modal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 14px; max-width: 440px; width: 90%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); pointer-events: auto;">
        <div class="modal-title" id="del-modal-title" style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
            Konfirmasi Hapus
        </div>
        <div class="modal-body" id="del-modal-message" style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 24px;">
            Apakah Anda yakin ingin menghapus item ini?
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn btn-secondary" id="del-modal-cancel" style="cursor: pointer; pointer-events: auto;">Batal</button>
            <button type="button" class="btn btn-danger" id="del-modal-proceed" style="cursor: pointer; pointer-events: auto;">Hapus Sekarang</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const groupsContainer = document.getElementById('groups-container');
    const badgeTotalGroups = document.getElementById('badge-total-groups');
    const badgeTotalCriteria = document.getElementById('badge-total-criteria');
    const btnTopAddGroup = document.getElementById('btn-top-add-group');
    const btnBottomAddGroup = document.getElementById('btn-bottom-add-group');

    // Modal Confirmation elements
    const delModal = document.getElementById('delete-confirm-modal');
    const delModalTitle = document.getElementById('del-modal-title');
    const delModalMsg = document.getElementById('del-modal-message');
    const delModalCancel = document.getElementById('del-modal-cancel');
    const delModalProceed = document.getElementById('del-modal-proceed');
    let pendingDeleteAction = null;

    function openConfirmModal(title, message, onProceed) {
        if (!delModal) {
            if (window.confirm(message)) {
                onProceed();
            }
            return;
        }
        delModalTitle.textContent = title;
        delModalMsg.textContent = message;
        pendingDeleteAction = onProceed;
        delModal.style.display = 'flex';
        delModal.style.pointerEvents = 'auto';
        setTimeout(() => {
            delModal.classList.add('show');
        }, 10);
    }

    function closeConfirmModal() {
        if (delModal) {
            delModal.classList.remove('show');
            delModal.style.pointerEvents = 'none';
            setTimeout(() => {
                delModal.style.display = 'none';
                pendingDeleteAction = null;
            }, 150);
        }
    }

    if (delModalCancel) {
        delModalCancel.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeConfirmModal();
        });
    }

    if (delModalProceed) {
        delModalProceed.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (typeof pendingDeleteAction === 'function') {
                const action = pendingDeleteAction;
                pendingDeleteAction = null;
                action();
            }
            closeConfirmModal();
        });
    }

    if (delModal) {
        delModal.addEventListener('click', (e) => {
            if (e.target === delModal) {
                closeConfirmModal();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && delModal && delModal.classList.contains('show')) {
            closeConfirmModal();
        }
    });

    // Re-index all groups and their criteria inputs
    function refreshIndexes() {
        const groupCards = groupsContainer.querySelectorAll('.group-card');
        badgeTotalGroups.textContent = groupCards.length;

        let totalCritCount = 0;

        groupCards.forEach((card, gIdx) => {
            card.dataset.groupIndex = gIdx;

            // Letter badge
            const letter = letters[gIdx % 26];
            const letterBadge = card.querySelector('.group-letter-badge');
            if (letterBadge) letterBadge.textContent = letter;

            // Group Label
            const labelEl = card.querySelector('label');
            if (labelEl) {
                labelEl.innerHTML = `Nama Kelompok ${gIdx + 1} / Kategori Skill <span style="color: var(--danger);">*</span>`;
            }

            // Group name input
            const nameInput = card.querySelector('.group-name-input');
            if (nameInput) nameInput.name = `groups[${gIdx}][name]`;

            // Group ID input
            const idInput = card.querySelector('.group-id-input');
            if (idInput) idInput.name = `groups[${gIdx}][id]`;

            // Criteria in this group
            const critItems = card.querySelectorAll('.criterion-item');
            const critCountBadge = card.querySelector('.group-crit-count');
            if (critCountBadge) critCountBadge.textContent = `${critItems.length} Kriteria`;
            totalCritCount += critItems.length;

            critItems.forEach((crit, cIdx) => {
                crit.dataset.critIndex = cIdx;

                // Number label
                const numLabel = crit.querySelector('.crit-number-label');
                if (numLabel) numLabel.textContent = `Kriteria ${cIdx + 1}`;

                // Label textarea
                const labelTextarea = crit.querySelector('.crit-label-input');
                if (labelTextarea) labelTextarea.name = `groups[${gIdx}][criteria][${cIdx}][label]`;

                // Criterion ID input
                const critIdInput = crit.querySelector('.crit-id-input');
                if (critIdInput) critIdInput.name = `groups[${gIdx}][criteria][${cIdx}][id]`;

                // Slot key input
                const slotKeyInput = crit.querySelector('.crit-slot-input');
                if (slotKeyInput) slotKeyInput.name = `groups[${gIdx}][criteria][${cIdx}][slot_key]`;
            });
        });

        badgeTotalCriteria.textContent = totalCritCount;
    }

    // Template untuk kriteria baru
    function createCriterionElement(cIdx) {
        const div = document.createElement('div');
        div.className = 'criterion-item';
        div.dataset.critIndex = cIdx;
        div.style.cssText = 'background-color: var(--bg-secondary); border: 1px solid var(--bg-tertiary); border-radius: 10px; padding: 16px; margin-bottom: 12px; animation: fadeIn 0.25s ease;';
        div.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div class="crit-number-label" style="font-size: 0.82rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em;">
                    Kriteria ${cIdx + 1}
                </div>
                <button type="button" class="btn btn-sm btn-danger btn-remove-crit" title="Hapus Kriteria Ini" style="padding: 4px 8px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 13px; height: 13px; pointer-events: none;">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    <span style="pointer-events: none;">Hapus</span>
                </button>
            </div>
            <div>
                <label style="font-weight: 600; font-size: 0.86rem; margin-bottom: 6px; display: block; color: var(--text-primary);">
                    Deskripsi Indikator Penilaian <span style="color: var(--danger);">*</span>
                    <span style="font-size: 0.75rem; font-weight: 400; color: var(--text-secondary);">(ditampilkan di form observasi saat menilai atlet)</span>
                </label>
                <textarea name=""
                          class="form-control crit-label-input"
                          rows="2"
                          required
                          placeholder="Deskripsi indikator yang dinilai..."
                          style="width: 100%; resize: vertical;"></textarea>
                <input type="hidden" name="" value="" class="crit-id-input">
                <input type="hidden" name="" value="" class="crit-slot-input">
            </div>
        `;
        return div;
    }

    // Template untuk kelompok baru
    function createGroupElement(gIdx) {
        const letter = letters[gIdx % 26];
        const card = document.createElement('div');
        card.className = 'data-card group-card';
        card.dataset.groupIndex = gIdx;
        card.style.cssText = 'margin-bottom: 24px; border: 1px solid var(--bg-tertiary); border-radius: 12px; background: var(--bg-primary); padding: 22px; animation: fadeIn 0.3s ease;';
        card.innerHTML = `
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid var(--bg-tertiary);">
                <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 260px;">
                    <div class="group-letter-badge" style="width: 38px; height: 38px; border-radius: 10px; background: rgba(249, 115, 22, 0.15); color: var(--accent); font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        ${letter}
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">
                            Nama Kelompok ${gIdx + 1} / Kategori Skill <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="text"
                               name="groups[${gIdx}][name]"
                               class="form-control group-name-input"
                               value=""
                               maxlength="100"
                               required
                               placeholder="Contoh: Rebounding, Defense, Passing..."
                               style="font-weight: 700; font-size: 1.05rem; width: 100%;">
                        <input type="hidden" name="groups[${gIdx}][id]" value="" class="group-id-input">
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <span class="badge badge-info group-crit-count" style="font-size: 0.82rem; padding: 6px 12px; border-radius: 20px;">
                        1 Kriteria
                    </span>
                    <button type="button" class="btn btn-secondary btn-sm btn-add-crit-header" title="Tambah Kriteria ke Kelompok Ini" style="padding: 6px 10px; font-size: 0.82rem; cursor: pointer;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; vertical-align: middle; pointer-events: none;">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        <span style="pointer-events: none;">+ Kriteria</span>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm btn-remove-group" title="Hapus Seluruh Kelompok Ini" style="padding: 6px 10px; font-size: 0.82rem; cursor: pointer;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; vertical-align: middle; pointer-events: none;">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        <span style="pointer-events: none;">Hapus Kelompok</span>
                    </button>
                </div>
            </div>

            <div class="criteria-list"></div>

            <div style="margin-top: 14px; display: flex; justify-content: flex-start;">
                <button type="button" class="btn btn-secondary btn-sm btn-add-crit-bottom" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; pointer-events: none;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span style="pointer-events: none;">+ Tambah Kriteria di Kelompok Ini</span>
                </button>
            </div>
        `;

        // Tambah 1 kriteria default di kelompok baru
        const critList = card.querySelector('.criteria-list');
        critList.appendChild(createCriterionElement(0));
        return card;
    }

    function addNewGroup() {
        const currentGroups = groupsContainer.querySelectorAll('.group-card');
        const newGroup = createGroupElement(currentGroups.length);
        groupsContainer.appendChild(newGroup);
        refreshIndexes();

        // Scroll and focus
        newGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const nameInput = newGroup.querySelector('.group-name-input');
        if (nameInput) setTimeout(() => nameInput.focus(), 300);
    }

    if (btnTopAddGroup) btnTopAddGroup.addEventListener('click', addNewGroup);
    if (btnBottomAddGroup) btnBottomAddGroup.addEventListener('click', addNewGroup);

    // Global Click Delegation untuk tombol aksi
    document.addEventListener('click', (e) => {
        // 1. Tambah kriteria (header atau bottom button)
        const addCritBtn = e.target.closest('.btn-add-crit-header, .btn-add-crit-bottom');
        if (addCritBtn) {
            e.preventDefault();
            e.stopPropagation();
            const groupCard = addCritBtn.closest('.group-card');
            if (groupCard) {
                const critList = groupCard.querySelector('.criteria-list');
                const currentCrits = critList.querySelectorAll('.criterion-item');
                const newCrit = createCriterionElement(currentCrits.length);
                critList.appendChild(newCrit);
                refreshIndexes();

                const txt = newCrit.querySelector('.crit-label-input');
                if (txt) txt.focus();
            }
            return;
        }

        // 2. Hapus kelompok
        const removeGroupBtn = e.target.closest('.btn-remove-group');
        if (removeGroupBtn) {
            e.preventDefault();
            e.stopPropagation();
            const groupCard = removeGroupBtn.closest('.group-card');
            if (!groupCard) return;

            const totalGroups = groupsContainer.querySelectorAll('.group-card').length;
            if (totalGroups <= 1) {
                alert('Tidak dapat menghapus kelompok. Minimal harus ada 1 kelompok kriteria penilaian.');
                return;
            }

            const nameInput = groupCard.querySelector('.group-name-input');
            const groupName = nameInput && nameInput.value.trim() ? nameInput.value.trim() : 'Kelompok ini';

            openConfirmModal(
                'Hapus Kelompok',
                `Apakah Anda yakin ingin menghapus kelompok "${groupName}" beserta seluruh kriteria di dalamnya?`,
                () => {
                    groupCard.remove();
                    refreshIndexes();
                }
            );
            return;
        }

        // 3. Hapus kriteria
        const removeCritBtn = e.target.closest('.btn-remove-crit');
        if (removeCritBtn) {
            e.preventDefault();
            e.stopPropagation();
            const critItem = removeCritBtn.closest('.criterion-item');
            if (!critItem) return;

            const groupCard = critItem.closest('.group-card');
            if (!groupCard) return;

            const totalCritsInGroup = groupCard.querySelectorAll('.criterion-item').length;
            if (totalCritsInGroup <= 1) {
                alert('Setiap kelompok minimal harus memiliki 1 kriteria penilaian. Jika kelompok ini tidak digunakan lagi, silakan klik tombol "Hapus Kelompok".');
                return;
            }

            openConfirmModal(
                'Hapus Kriteria',
                'Apakah Anda yakin ingin menghapus indikator kriteria penilaian ini?',
                () => {
                    critItem.remove();
                    refreshIndexes();
                }
            );
            return;
        }
    });

    // Validasi form sebelum submit
    const form = document.getElementById('form-criteria-settings');
    if (form) {
        form.addEventListener('submit', (e) => {
            const groupCards = groupsContainer.querySelectorAll('.group-card');
            if (groupCards.length === 0) {
                e.preventDefault();
                alert('Minimal harus ada 1 kelompok penilaian.');
                return;
            }

            let hasError = false;
            groupCards.forEach((card, gIdx) => {
                const name = card.querySelector('.group-name-input').value.trim();
                if (!name) {
                    hasError = true;
                    alert(`Nama Kelompok ${gIdx + 1} tidak boleh kosong.`);
                    card.querySelector('.group-name-input').focus();
                    return;
                }

                const crits = card.querySelectorAll('.criterion-item');
                if (crits.length === 0) {
                    hasError = true;
                    alert(`Kelompok "${name}" harus memiliki minimal 1 kriteria.`);
                    return;
                }

                crits.forEach((crit, cIdx) => {
                    const label = crit.querySelector('.crit-label-input').value.trim();
                    if (!label) {
                        hasError = true;
                        alert(`Deskripsi Kriteria ${cIdx + 1} pada kelompok "${name}" tidak boleh kosong.`);
                        crit.querySelector('.crit-label-input').focus();
                        return;
                    }
                });
            });

            if (hasError) {
                e.preventDefault();
            }
        });
    }
});
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

#delete-confirm-modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    background-color: rgba(15, 23, 42, 0.85) !important;
    backdrop-filter: blur(4px) !important;
    z-index: 99999 !important;
    display: none;
    align-items: center !important;
    justify-content: center !important;
}

#delete-confirm-modal.show {
    display: flex !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}

#delete-confirm-modal .modal-content {
    pointer-events: auto !important;
    z-index: 100000 !important;
}

#delete-confirm-modal button {
    pointer-events: auto !important;
    cursor: pointer !important;
}

.btn-remove-group, .btn-remove-crit, .btn-add-crit-header, .btn-add-crit-bottom {
    cursor: pointer !important;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
