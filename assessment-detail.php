<?php
/**
 * Assessment Detail (Lembar Observasi GPAI)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

requireAuth();

$db = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdmin = ($_SESSION['role'] === 'admin');
$currentUserId = $_SESSION['user_id'];

// Fetch assessment record with participant and coach data
$sql = "SELECT pa.*, 
               p.name as participant_name,
               p.gender as participant_gender,
               p.birth_date,
               p.school_name,
               p.photo as participant_photo,
               u.username as creator_username
        FROM participant_assessments pa
        JOIN participants p ON pa.participant_id = p.id
        JOIN users u ON pa.user_id = u.id
        WHERE pa.id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$a = $stmt->fetch();

if (!$a) {
    setFlashMessage('danger', 'Data lembar penilaian tidak ditemukan.');
    header("Location: assessments.php");
    exit;
}

// Age calculation
$birthDate = new DateTime($a['birth_date']);
$today = new DateTime();
$age = $today->diff($birthDate)->y;

// Total criteria
$totalIndicators = (int)$a['score_e'] + (int)$a['score_ie'];
if ($totalIndicators === 0) $totalIndicators = 6;

// Resolve snapshot groups & criteria
$snapshotGroups = [];
if (!empty($a['criteria_data'])) {
    $decoded = json_decode($a['criteria_data'], true);
    if (is_array($decoded)) {
        $snapshotGroups = $decoded;
    }
}

// Fallback jika belum ada criteria_data (data legacy)
if (empty($snapshotGroups)) {
    $snapshotGroups = [
        [
            'group_name' => 'Chest Pass (Umpan Dada)',
            'criteria' => [
                ['label' => 'Awalan bola di depan dada, kaki dibuka lebar, badan condong', 'score' => $a['chest_pass_prep'] ?? 'E'],
                ['label' => 'Akurasi lemparan ke arah kawan', 'score' => $a['chest_pass_acc'] ?? 'E'],
            ]
        ],
        [
            'group_name' => 'Dribbling',
            'criteria' => [
                ['label' => 'Bola dipantulkan setinggi pinggang dengan ujung jari', 'score' => $a['dribble_control'] ?? 'E'],
                ['label' => 'Pandangan ke depan, tidak terpaku pada bola', 'score' => $a['dribble_vision'] ?? 'E'],
            ]
        ],
        [
            'group_name' => 'Shooting',
            'criteria' => [
                ['label' => 'Siku lurus di bawah bola saat menembak', 'score' => $a['shooting_elbow'] ?? 'E'],
                ['label' => 'Lepasan bola diikuti gerakan pergelangan tangan', 'score' => $a['shooting_wrist'] ?? 'E'],
            ]
        ],
    ];
}

$pageTitle = "Detail Penilaian Atlet - " . $a['participant_name'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Action Bar -->
<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; gap: 8px; align-items: center;">
        <a href="assessments" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <!-- Cetak PDF / Lembar Observasi -->
        <a href="assessment-print.php?id=<?= $a['id'] ?>" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            <span>Cetak Lembar Observasi</span>
        </a>

        <!-- Edit Penilaian (Admin atau Coach yang menginput) -->
        <?php if ($isAdmin || $a['user_id'] == $currentUserId): ?>
            <a href="assessment-edit.php?id=<?= $a['id'] ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <span>Edit Penilaian</span>
            </a>
        <?php endif; ?>

        <!-- Hapus (Admin only) -->
        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-danger" onclick="confirmAction('Hapus Penilaian', 'Apakah Anda yakin ingin menghapus lembar penilaian ini untuk atlet <?= e(addslashes($a['participant_name'])) ?>? Tindakan ini tidak dapat dibatalkan.', 'assessment-delete.php?id=<?= $a['id'] ?>&csrf_token=<?= generateCSRFToken() ?>')">
                Hapus
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="data-card" style="margin-bottom: 24px;">
    <!-- Header Rubrik -->
    <div style="border-bottom: 1px solid var(--bg-tertiary); padding-bottom: 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <span class="badge badge-info" style="margin-bottom: 8px; display: inline-block;">Lembar Observasi GPAI</span>
            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0 0 4px;">
                Hasil Evaluasi Keterampilan Bermain
            </h2>
            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                Game Performance Assessment Instrument &middot; Crown Basketball Academy
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 0.8rem; color: var(--text-secondary);">Tanggal Observasi:</div>
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-primary);"><?= formatIndoDate($a['assessment_date']) ?></div>
        </div>
    </div>

    <!-- Athlete & Coach Profile Info Grid -->
    <div style="background-color: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
        <div style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
            <?php if (!empty($a['participant_photo'])): ?>
                <img src="<?= e(getPhotoSrc($a['participant_photo'], 'uploads/participants/')) ?>" alt="<?= e($a['participant_name']) ?>" style="width: 72px; height: 72px; border-radius: 10px; object-fit: cover; border: 2px solid var(--accent);">
            <?php else: ?>
                <div style="width: 72px; height: 72px; border-radius: 10px; background-color: var(--bg-secondary); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 800; color: var(--accent); border: 1px solid rgba(255,255,255,0.05);">
                    <?= strtoupper(substr($a['participant_name'], 0, 1)) ?>
                </div>
            <?php endif; ?>

            <div style="flex: 1; min-width: 240px;">
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">
                    <a href="participant-detail.php?id=<?= $a['participant_id'] ?>" style="color: inherit; text-decoration: none;">
                        <?= e($a['participant_name']) ?>
                    </a>
                </div>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.85rem; color: var(--text-secondary);">
                    <span>Gender: <strong style="color: var(--text-primary);"><?= $a['participant_gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></strong></span>
                    <span>Usia: <strong style="color: var(--text-primary);"><?= $age ?></strong></span>
                    <span>Sekolah: <strong style="color: var(--text-primary);"><?= e($a['school_name'] ?: '-') ?></strong></span>
                </div>
            </div>

            <div style="border-left: 1px solid var(--bg-tertiary); padding-left: 20px;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 2px;">Coach Penilai:</div>
                <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary);">
                    <?= e($a['coach_name']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pedoman Skoring Box -->
    <div style="background: rgba(249, 115, 22, 0.08); border: 1px solid rgba(249, 115, 22, 0.25); border-radius: 10px; padding: 14px 18px; margin-bottom: 28px; font-size: 0.92rem; color: var(--text-primary); line-height: 1.5;">
        <strong>Pedoman skoring:</strong> E = Efektif (sesuai indikator) &middot; IE = In-Efektif (kurang/tidak sesuai). Skor = jumlah indikator E dari total <?= $totalIndicators ?> indikator.
    </div>

    <!-- Section: Psikomotor (Skill Execution) -->
    <div style="margin-bottom: 28px;">
        <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 18px;">
            1. Psikomotor (Skill Execution)
        </h3>

        <?php
        $groupLetters = range('A', 'Z');
        $critGlobalNum = 1;
        foreach ($snapshotGroups as $gIdx => $group):
            $letter = $groupLetters[$gIdx % 26];
            $groupCriteria = $group['criteria'] ?? [];
        ?>
        <div style="background-color: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 12px; padding: 20px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--bg-tertiary); padding-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; border-radius: 8px; background: rgba(249, 115, 22, 0.15); color: var(--accent); font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; justify-content: center;">
                        <?= $letter ?>
                    </div>
                    <div style="font-size: 1rem; font-weight: 700; color: var(--text-primary);">
                        <?= e($group['group_name']) ?>
                    </div>
                </div>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">
                    <?= count($groupCriteria) ?> indikator
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                <?php foreach ($groupCriteria as $crit):
                    $critScore = $crit['score'] ?? 'E';
                ?>
                <div style="background-color: var(--bg-secondary); border-radius: 8px; padding: 14px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.84rem; color: var(--text-secondary); margin-bottom: 10px; line-height: 1.45;">
                        <strong style="color: var(--accent);"><?= $critGlobalNum++ ?>.</strong> <?= e($crit['label']) ?>
                    </div>
                    <div>
                        <?php if ($critScore === 'E'): ?>
                            <span class="badge badge-success" style="font-size: 0.85rem; padding: 6px 10px;">✓ E (Efektif - Memenuhi Kriteria)</span>
                        <?php else: ?>
                            <span class="badge badge-danger" style="font-size: 0.85rem; padding: 6px 10px;">✗ IE (In-Efektif - Tidak Memenuhi Kriteria)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Scoring Result Card -->
    <div style="background: linear-gradient(135deg, rgba(249, 115, 22, 0.1) 0%, var(--bg-primary) 100%); border: 2px solid rgba(249, 115, 22, 0.3); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: center; text-align: center;">
            <div style="border-right: 1px solid rgba(255,255,255,0.08); padding-right: 12px;">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 6px;">Indikator Efektif (E)</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--success);"><?= $a['score_e'] ?> <span style="font-size: 1.1rem; color: var(--text-secondary);">/ <?= $totalIndicators ?></span></div>
            </div>

            <div style="border-right: 1px solid rgba(255,255,255,0.08); padding-right: 12px;">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 6px;">Indikator In-Efektif (IE)</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--danger);"><?= $a['score_ie'] ?> <span style="font-size: 1.1rem; color: var(--text-secondary);">/ <?= $totalIndicators ?></span></div>
            </div>

            <div style="border-right: 1px solid rgba(255,255,255,0.08); padding-right: 12px;">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 6px;">Persentase Nilai Akhir</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--accent);"><?= number_format($a['final_score'], 1) ?>%</div>
            </div>

            <div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 6px;">Predikat Kualitatif</div>
                <div>
                    <?= renderPredicateBadge($a['predicate']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Coach Notes -->
    <div style="background-color: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 12px; padding: 20px;">
        <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
            Catatan / Evaluasi Pelatih:
        </div>
        <div style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.6; white-space: pre-line;">
            <?= !empty($a['notes']) ? e($a['notes']) : '<em>Tidak ada catatan khusus dari pelatih untuk sesi penilaian ini.</em>' ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
