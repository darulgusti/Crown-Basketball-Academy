<?php
/**
 * Edit Participant Assessment (Lembar Observasi GPAI)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

requireAuth();

$db = getDBConnection();
ensureAssessmentTables($db);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdmin = ($_SESSION['role'] === 'admin');
$currentUserId = $_SESSION['user_id'];
$errors = [];

// Fetch existing record
$stmt = $db->prepare("SELECT pa.*, p.name as participant_name 
                      FROM participant_assessments pa
                      JOIN participants p ON pa.participant_id = p.id
                      WHERE pa.id = ?");
$stmt->execute([$id]);
$a = $stmt->fetch();

if (!$a) {
    setFlashMessage('danger', 'Data penilaian tidak ditemukan.');
    header("Location: assessments.php");
    exit;
}

// Check permission
if (!$isAdmin && $a['user_id'] != $currentUserId) {
    setFlashMessage('danger', 'Anda tidak memiliki hak untuk mengedit penilaian ini.');
    header("Location: assessment-detail.php?id=" . $id);
    exit;
}

// Fetch active participants
$stmtP = $db->query("SELECT id, name, gender FROM participants ORDER BY name ASC");
$participants = $stmtP->fetchAll();

// Resolve Criteria Snapshot
$snapshotGroups = [];
if (!empty($a['criteria_data'])) {
    $decoded = json_decode($a['criteria_data'], true);
    if (is_array($decoded)) {
        $snapshotGroups = $decoded;
    }
}

// Jika belum ada snapshot (misal data legacy lama), gunakan data kriteria saat ini atau 6 slot lama
if (empty($snapshotGroups)) {
    // Ambil dari database jika ada
    $groupsList = $db->query("SELECT * FROM assessment_groups ORDER BY sort_order ASC, id ASC")->fetchAll();
    if (!empty($groupsList)) {
        foreach ($groupsList as $grp) {
            $stmtC = $db->prepare("SELECT * FROM assessment_criteria WHERE group_id = ? ORDER BY sort_order ASC, id ASC");
            $stmtC->execute([$grp['id']]);
            $crits = $stmtC->fetchAll();
            $gCriteria = [];
            foreach ($crits as $c) {
                // Ambil nilai dari legacy column jika cocok, default 'E'
                $val = 'E';
                if (!empty($c['slot_key']) && isset($a[$c['slot_key']])) {
                    $val = $a[$c['slot_key']];
                }
                $gCriteria[] = [
                    'id' => $c['id'],
                    'slot_key' => $c['slot_key'] ?? '',
                    'label' => $c['criterion_label'],
                    'score' => $val,
                ];
            }
            $snapshotGroups[] = [
                'group_id' => $grp['id'],
                'group_name' => $grp['name'],
                'criteria' => $gCriteria,
            ];
        }
    } else {
        // Fallback ke 6 kriteria legacy
        $snapshotGroups = [
            [
                'group_id' => 1,
                'group_name' => 'Chest Pass (Umpan Dada)',
                'criteria' => [
                    ['id' => 'chest_pass_prep', 'slot_key' => 'chest_pass_prep', 'label' => 'Awalan bola di depan dada, kaki dibuka lebar, badan condong', 'score' => $a['chest_pass_prep'] ?? 'E'],
                    ['id' => 'chest_pass_acc',  'slot_key' => 'chest_pass_acc',  'label' => 'Akurasi lemparan ke arah kawan', 'score' => $a['chest_pass_acc'] ?? 'E'],
                ]
            ],
            [
                'group_id' => 2,
                'group_name' => 'Dribbling',
                'criteria' => [
                    ['id' => 'dribble_control', 'slot_key' => 'dribble_control', 'label' => 'Bola dipantulkan setinggi pinggang dengan ujung jari', 'score' => $a['dribble_control'] ?? 'E'],
                    ['id' => 'dribble_vision',  'slot_key' => 'dribble_vision',  'label' => 'Pandangan ke depan, tidak terpaku pada bola', 'score' => $a['dribble_vision'] ?? 'E'],
                ]
            ],
            [
                'group_id' => 3,
                'group_name' => 'Shooting',
                'criteria' => [
                    ['id' => 'shooting_elbow',  'slot_key' => 'shooting_elbow',  'label' => 'Siku lurus di bawah bola saat menembak', 'score' => $a['shooting_elbow'] ?? 'E'],
                    ['id' => 'shooting_wrist',  'slot_key' => 'shooting_wrist',  'label' => 'Lepasan bola diikuti gerakan pergelangan tangan', 'score' => $a['shooting_wrist'] ?? 'E'],
                ]
            ],
        ];
    }
}

// Hitung total kriteria
$totalCriteriaCount = 0;
foreach ($snapshotGroups as $sg) {
    $totalCriteriaCount += count($sg['criteria'] ?? []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    
    $participantId = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : $a['participant_id'];
    $dateInput = trim($_POST['assessment_date'] ?? $a['assessment_date']);
    $notes = trim($_POST['notes'] ?? '');
    $postCriteria = $_POST['crit'] ?? [];
    
    if ($participantId <= 0) {
        $errors[] = "Silakan pilih peserta didik / atlet yang dinilai.";
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
        $errors[] = "Format tanggal observasi tidak valid.";
    }
    
    if (empty($errors)) {
        $scoreE = 0;
        $scoreIE = 0;
        $updatedSnapshot = [];

        $legacyMap = [
            'chest_pass_prep' => $a['chest_pass_prep'] ?? 'E',
            'chest_pass_acc'  => $a['chest_pass_acc'] ?? 'E',
            'dribble_control' => $a['dribble_control'] ?? 'E',
            'dribble_vision'  => $a['dribble_vision'] ?? 'E',
            'shooting_elbow'  => $a['shooting_elbow'] ?? 'E',
            'shooting_wrist'  => $a['shooting_wrist'] ?? 'E',
        ];

        foreach ($snapshotGroups as $g) {
            $gSnapshot = [
                'group_id' => $g['group_id'] ?? null,
                'group_name' => $g['group_name'] ?? 'Kategori Skill',
                'criteria' => [],
            ];

            foreach ($g['criteria'] as $c) {
                $cKey = $c['id'] ?? $c['slot_key'];
                $val = ($postCriteria[$cKey] ?? ($c['score'] ?? 'E')) === 'IE' ? 'IE' : 'E';
                if ($val === 'E') {
                    $scoreE++;
                } else {
                    $scoreIE++;
                }

                $gSnapshot['criteria'][] = [
                    'id' => $c['id'] ?? null,
                    'slot_key' => $c['slot_key'] ?? '',
                    'label' => $c['label'] ?? '',
                    'score' => $val,
                ];

                if (!empty($c['slot_key']) && array_key_exists($c['slot_key'], $legacyMap)) {
                    $legacyMap[$c['slot_key']] = $val;
                }
            }
            $updatedSnapshot[] = $gSnapshot;
        }

        $totalCriteria = $scoreE + $scoreIE;
        $finalScore = $totalCriteria > 0 ? round(($scoreE / $totalCriteria) * 100, 2) : 0;
        
        if ($finalScore >= 80) {
            $predicate = 'Sangat Baik';
        } elseif ($finalScore >= 65) {
            $predicate = 'Baik';
        } elseif ($finalScore >= 50) {
            $predicate = 'Cukup';
        } else {
            $predicate = 'Perlu Latihan';
        }
        
        $criteriaDataJson = json_encode($updatedSnapshot, JSON_UNESCAPED_UNICODE);

        try {
            $sql = "UPDATE participant_assessments SET
                        participant_id = ?,
                        assessment_date = ?,
                        criteria_data = ?,
                        chest_pass_prep = ?,
                        chest_pass_acc = ?,
                        dribble_control = ?,
                        dribble_vision = ?,
                        shooting_elbow = ?,
                        shooting_wrist = ?,
                        score_e = ?,
                        score_ie = ?,
                        final_score = ?,
                        predicate = ?,
                        notes = ?
                    WHERE id = ?";
            $stmtUp = $db->prepare($sql);
            $stmtUp->execute([
                $participantId,
                $dateInput,
                $criteriaDataJson,
                $legacyMap['chest_pass_prep'],
                $legacyMap['chest_pass_acc'],
                $legacyMap['dribble_control'],
                $legacyMap['dribble_vision'],
                $legacyMap['shooting_elbow'],
                $legacyMap['shooting_wrist'],
                $scoreE,
                $scoreIE,
                $finalScore,
                $predicate,
                (empty($notes) ? null : $notes),
                $id
            ]);
            
            setFlashMessage('success', 'Perubahan lembar penilaian berhasil disimpan!');
            header("Location: assessment-detail.php?id=" . $id);
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
    
    $a['participant_id'] = $participantId;
    $a['assessment_date'] = $dateInput;
    $a['notes'] = $notes;
}

$pageTitle = "Edit Lembar Observasi GPAI";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb / Back -->
<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <a href="assessment-detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Kembali ke Detail Penilaian</span>
        </a>
    </div>
</div>

<!-- Page Header -->
<div class="card-actions" style="margin-bottom: 24px;">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Edit Lembar Observasi GPAI
        </h2>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">
            Perbarui data hasil observasi performa teknik atlet: <?= e($a['participant_name']) ?>
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

<form action="assessment-edit.php?id=<?= $a['id'] ?>" method="POST">
    <?= csrfField() ?>

    <div class="data-card" style="margin-bottom: 24px;">
        <!-- Identitas Form Section -->
        <div style="border-bottom: 1px solid var(--bg-tertiary); padding-bottom: 24px; margin-bottom: 28px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 18px;">
                Identitas Penilaian
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px;">
                <!-- Pilih Peserta -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="participant_id" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: block;">
                        Nama Peserta / Atlet <span style="color: var(--danger);">*</span>
                    </label>
                    <select name="participant_id" id="participant_id" class="form-control" required style="width: 100%;">
                        <?php foreach ($participants as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $a['participant_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> (<?= $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tanggal Observasi -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="assessment_date" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: block;">
                        Tanggal Observasi <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="date" name="assessment_date" id="assessment_date" class="form-control" value="<?= e($a['assessment_date']) ?>" required style="width: 100%;">
                </div>

                <!-- Pelatih / Penilai -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: block;">
                        Pelatih / Observer
                    </label>
                    <input type="text" class="form-control" value="<?= e($a['coach_name']) ?>" readonly style="background-color: var(--bg-secondary); cursor: not-allowed; width: 100%;">
                </div>
            </div>
        </div>

        <!-- Pedoman Skoring Notice -->
        <div style="background: rgba(249, 115, 22, 0.08); border: 1px solid rgba(249, 115, 22, 0.25); border-radius: 10px; padding: 14px 18px; margin-bottom: 28px; font-size: 0.92rem; color: var(--text-primary); line-height: 1.5;">
            <strong>Pedoman skoring:</strong> E = Efektif (sesuai indikator) &middot; IE = In-Efektif (kurang/tidak sesuai). Skor = jumlah indikator E dari total <?= $totalCriteriaCount ?> indikator.
        </div>

        <!-- Section: Rubrik Observasi Skill Execution -->
        <div style="margin-bottom: 28px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 18px;">
                Rubrik Observasi Skill Execution
            </h3>

            <?php
            $groupLetters = range('A', 'Z');
            $globalIdx = 1;
            foreach ($snapshotGroups as $gIdx => $group):
                $letter = $groupLetters[$gIdx % 26];
                $groupCriteria = $group['criteria'] ?? [];
            ?>
            <!-- Card Kelompok <?= $letter ?> -->
            <div style="background-color: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 12px; padding: 20px; margin-bottom: 18px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--bg-tertiary); padding-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(249, 115, 22, 0.15); color: var(--accent); font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; justify-content: center;">
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

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                    <?php foreach ($groupCriteria as $crit):
                        $cKey = $crit['id'] ?? $crit['slot_key'];
                        $critLabel = $crit['label'];
                        $selectedVal = $crit['score'] ?? 'E';
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($postCriteria[$cKey])) {
                            $selectedVal = $postCriteria[$cKey] === 'IE' ? 'IE' : 'E';
                        }
                    ?>
                    <div style="background-color: var(--bg-secondary); border-radius: 10px; padding: 14px; border: 1px solid var(--bg-tertiary);">
                        <label for="crit_<?= e($cKey) ?>" style="font-size: 0.88rem; color: var(--text-primary); margin-bottom: 10px; display: block; line-height: 1.45; font-weight: 600;">
                            <span style="color: var(--accent); margin-right: 4px;"><?= $globalIdx++ ?>.</span> <?= e($critLabel) ?>
                        </label>
                        <select name="crit[<?= e($cKey) ?>]" id="crit_<?= e($cKey) ?>" class="form-control gpai-select" style="width: 100%;">
                            <option value="E" <?= $selectedVal === 'E' ? 'selected' : '' ?>>E (Efektif - Memenuhi Kriteria)</option>
                            <option value="IE" <?= $selectedVal === 'IE' ? 'selected' : '' ?>>IE (In-Efektif - Tidak Memenuhi Kriteria)</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Live Score Summary Box -->
        <div style="background: linear-gradient(135deg, rgba(249, 115, 22, 0.08) 0%, var(--bg-primary) 100%); border: 1px solid rgba(249, 115, 22, 0.2); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px;">Perhitungan Skor Observasi</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary);">
                        Skor: <span id="summaryScoreE" style="color: var(--accent);"><?= $a['score_e'] ?></span> / <span id="summaryTotalCrit"><?= $totalCriteriaCount ?></span> (<span id="summaryPercent"><?= round($a['final_score']) ?>%</span>)
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div style="text-align: right;">
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">Predikat Hasil:</div>
                        <div id="summaryPredicate" style="font-size: 1.1rem; font-weight: 700; color: var(--success);">
                            <?= e($a['predicate']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes / Evaluasi Pelatih -->
        <div class="form-group" style="margin-bottom: 28px;">
            <label for="notes" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: block;">
                Catatan / Masukan Evaluasi Pelatih (Opsional)
            </label>
            <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Tuliskan catatan khusus terkait performa teknik atau hal yang perlu ditingkatkan oleh atlet..."><?= e($a['notes']) ?></textarea>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
            <a href="assessment-detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700;">
                Simpan Perubahan
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selects = document.querySelectorAll('.gpai-select');
    const scoreESpan = document.getElementById('summaryScoreE');
    const totalSpan = document.getElementById('summaryTotalCrit');
    const percentSpan = document.getElementById('summaryPercent');
    const predicateDiv = document.getElementById('summaryPredicate');

    function updateLiveScore() {
        let countE = 0;
        const total = selects.length;
        selects.forEach(s => {
            if (s.value === 'E') {
                countE++;
                s.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                s.style.color = '#10b981';
                s.style.fontWeight = '600';
            } else {
                s.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                s.style.color = '#ef4444';
                s.style.fontWeight = '600';
            }
        });

        const percent = total > 0 ? Math.round((countE / total) * 100) : 0;
        scoreESpan.textContent = countE;
        if (totalSpan) totalSpan.textContent = total;
        percentSpan.textContent = percent + '%';

        let predicateText = 'Perlu Latihan';
        let predicateColor = '#ef4444';

        if (percent >= 80) {
            predicateText = 'Sangat Baik';
            predicateColor = '#10b981';
        } else if (percent >= 65) {
            predicateText = 'Baik';
            predicateColor = '#06b6d4';
        } else if (percent >= 50) {
            predicateText = 'Cukup';
            predicateColor = '#f59e0b';
        }

        predicateDiv.textContent = predicateText;
        predicateDiv.style.color = predicateColor;
    }

    selects.forEach(s => {
        s.addEventListener('change', updateLiveScore);
    });

    updateLiveScore();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
