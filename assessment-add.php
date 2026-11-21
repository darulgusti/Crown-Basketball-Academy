<?php
/**
 * Add Participant Assessment (Lembar Observasi GPAI)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

requireAuth();

$db = getDBConnection();
ensureAssessmentTables($db);
$errors = [];

// Resolve Coach Name from active session
$currentUserId = $_SESSION['user_id'];
$coachId = $_SESSION['coach_id'] ?? null;
$coachName = $_SESSION['coach_name'] ?? null;

if (!$coachName) {
    $stmtC = $db->prepare("SELECT id, name FROM coaches WHERE user_id = ? LIMIT 1");
    $stmtC->execute([$currentUserId]);
    $coachProfile = $stmtC->fetch();
    if ($coachProfile) {
        $coachId = $coachProfile['id'];
        $coachName = $coachProfile['name'];
        $_SESSION['coach_id'] = $coachId;
        $_SESSION['coach_name'] = $coachName;
    } else {
        $coachName = !empty($_SESSION['username']) ? 'Coach ' . ucfirst($_SESSION['username']) : 'Coach Crown';
    }
}

// Fetch active participants
$stmtP = $db->query("SELECT id, name, gender FROM participants WHERE status = 'active' ORDER BY name ASC");
$participants = $stmtP->fetchAll();

// Fetch assessment groups & criteria from DB
$groupsList = $db->query("SELECT * FROM assessment_groups ORDER BY sort_order ASC, id ASC")->fetchAll();
$totalCriteriaCount = 0;
foreach ($groupsList as &$grp) {
    $stmtC = $db->prepare("SELECT * FROM assessment_criteria WHERE group_id = ? ORDER BY sort_order ASC, id ASC");
    $stmtC->execute([$grp['id']]);
    $grp['criteria'] = $stmtC->fetchAll();
    $totalCriteriaCount += count($grp['criteria']);
}
unset($grp);

// Fallback jika belum ada kelompok di assessment_groups
if (empty($groupsList)) {
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
        $totalCriteriaCount += count($cList);
    }
}

// Pre-selected participant if passed in query string
$selectedParticipantId = isset($_GET['participant_id']) ? (int)$_GET['participant_id'] : 0;
$assessmentDate = date('Y-m-d');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    
    $participantId = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;
    $dateInput = trim($_POST['assessment_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');
    $postCriteria = $_POST['crit'] ?? [];
    
    // Validation
    if ($participantId <= 0) {
        $errors[] = "Silakan pilih peserta didik / atlet yang dinilai.";
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
        $errors[] = "Format tanggal observasi tidak valid.";
    }

    if ($totalCriteriaCount === 0) {
        $errors[] = "Belum ada kriteria penilaian yang dikonfigurasi. Silakan atur kriteria penilaian terlebih dahulu.";
    }
    
    if (empty($errors)) {
        $scoreE = 0;
        $scoreIE = 0;
        $snapshotGroups = [];

        // Legacy columns mapping
        $legacyMap = [
            'chest_pass_prep' => 'E',
            'chest_pass_acc'  => 'E',
            'dribble_control' => 'E',
            'dribble_vision'  => 'E',
            'shooting_elbow'  => 'E',
            'shooting_wrist'  => 'E',
        ];

        foreach ($groupsList as $g) {
            $gSnapshot = [
                'group_id' => $g['id'],
                'group_name' => $g['name'],
                'criteria' => [],
            ];

            foreach ($g['criteria'] as $c) {
                $cId = $c['id'];
                $val = ($postCriteria[$cId] ?? 'E') === 'IE' ? 'IE' : 'E';
                if ($val === 'E') {
                    $scoreE++;
                } else {
                    $scoreIE++;
                }

                $gSnapshot['criteria'][] = [
                    'id' => $cId,
                    'slot_key' => $c['slot_key'] ?? '',
                    'label' => $c['criterion_label'],
                    'score' => $val,
                ];

                if (!empty($c['slot_key']) && array_key_exists($c['slot_key'], $legacyMap)) {
                    $legacyMap[$c['slot_key']] = $val;
                }
            }
            $snapshotGroups[] = $gSnapshot;
        }

        $totalCriteria = $scoreE + $scoreIE;
        $finalScore = $totalCriteria > 0 ? round(($scoreE / $totalCriteria) * 100, 2) : 0;
        
        // Predicate definition based on percentage
        if ($finalScore >= 80) {
            $predicate = 'Sangat Baik';
        } elseif ($finalScore >= 65) {
            $predicate = 'Baik';
        } elseif ($finalScore >= 50) {
            $predicate = 'Cukup';
        } else {
            $predicate = 'Perlu Latihan';
        }
        
        $criteriaDataJson = json_encode($snapshotGroups, JSON_UNESCAPED_UNICODE);

        try {
            $sql = "INSERT INTO participant_assessments 
                    (participant_id, user_id, coach_id, coach_name, assessment_date, criteria_data,
                     chest_pass_prep, chest_pass_acc, dribble_control, dribble_vision, shooting_elbow, shooting_wrist,
                     score_e, score_ie, final_score, predicate, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $participantId,
                $currentUserId,
                $coachId,
                $coachName,
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
                (empty($notes) ? null : $notes)
            ]);
            
            $newId = $db->lastInsertId();
            setFlashMessage('success', 'Lembar observasi penilaian berhasil disimpan!');
            header("Location: assessment-detail.php?id=" . $newId);
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Gagal menyimpan lembar observasi: " . $e->getMessage();
        }
    }
}

$pageTitle = "Input Lembar Observasi GPAI";
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
</div>

<!-- Page Header -->
<div class="card-actions" style="margin-bottom: 24px;">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Input Lembar Observasi GPAI
        </h2>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">
            Instrumen Asesmen Game Performance Assessment Instrument (GPAI) Bola Basket
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

<form action="assessment-add.php<?= $selectedParticipantId ? '?participant_id=' . $selectedParticipantId : '' ?>" method="POST">
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
                        <option value="">-- Pilih Atlet --</option>
                        <?php foreach ($participants as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($selectedParticipantId == $p['id'] || (isset($_POST['participant_id']) && $_POST['participant_id'] == $p['id'])) ? 'selected' : '' ?>>
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
                    <input type="date" name="assessment_date" id="assessment_date" class="form-control" value="<?= e($_POST['assessment_date'] ?? $assessmentDate) ?>" required style="width: 100%;">
                </div>

                <!-- Pelatih / Penilai (Auto from session) -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: block;">
                        Pelatih / Observer
                    </label>
                    <input type="text" class="form-control" value="<?= e($coachName) ?>" readonly style="background-color: var(--bg-secondary); cursor: not-allowed; width: 100%;">
                </div>
            </div>
        </div>

        <!-- Pedoman Skoring Notice -->
        <div style="background: rgba(249, 115, 22, 0.08); border: 1px solid rgba(249, 115, 22, 0.25); border-radius: 10px; padding: 14px 18px; margin-bottom: 28px; font-size: 0.92rem; color: var(--text-primary); line-height: 1.5;">
            <strong>Pedoman skoring:</strong> E = Efektif (sesuai indikator) &middot; IE = In-Efektif (kurang/tidak sesuai). Skor = jumlah indikator E dari total <?= $totalCriteriaCount ?> indikator.
        </div>

        <!-- Section: Psikomotor (Skill Execution) -->
        <div style="margin-bottom: 28px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 18px;">
                Rubrik Observasi Skill Execution
            </h3>

            <?php
            $groupLetters = range('A', 'Z');
            $globalIdx = 1;
            foreach ($groupsList as $gIdx => $group):
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
                            <?= e($group['name']) ?>
                        </div>
                    </div>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">
                        <?= count($groupCriteria) ?> indikator
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                    <?php foreach ($groupCriteria as $crit):
                        $cId = $crit['id'];
                        $critLabel = $crit['criterion_label'];
                        $selectedVal = 'E';
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $selectedVal = ($postCriteria[$cId] ?? 'E') === 'IE' ? 'IE' : 'E';
                        }
                    ?>
                    <div style="background-color: var(--bg-secondary); border-radius: 10px; padding: 14px; border: 1px solid var(--bg-tertiary);">
                        <label for="crit_<?= $cId ?>" style="font-size: 0.88rem; color: var(--text-primary); margin-bottom: 10px; display: block; line-height: 1.45; font-weight: 600;">
                            <span style="color: var(--accent); margin-right: 4px;"><?= $globalIdx++ ?>.</span> <?= e($critLabel) ?>
                        </label>
                        <select name="crit[<?= $cId ?>]" id="crit_<?= $cId ?>" class="form-control gpai-select" style="width: 100%;">
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
                        Skor: <span id="summaryScoreE" style="color: var(--accent);"><?= $totalCriteriaCount ?></span> / <span id="summaryTotalCrit"><?= $totalCriteriaCount ?></span> (<span id="summaryPercent">100%</span>)
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div style="text-align: right;">
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">Predikat Hasil:</div>
                        <div id="summaryPredicate" style="font-size: 1.1rem; font-weight: 700; color: var(--success);">
                            Sangat Baik
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
            <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Tuliskan catatan khusus terkait performa teknik atau hal yang perlu ditingkatkan oleh atlet..."><?= e($_POST['notes'] ?? '') ?></textarea>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
            <a href="assessments" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700;">
                Simpan Lembar Penilaian
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
