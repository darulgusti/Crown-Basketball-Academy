<?php
/**
 * Printable Assessment Sheet (Lembar Observasi GPAI)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$db = getDBConnection();
ensureAssessmentTables($db);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT pa.*, 
                             p.name as participant_name, 
                             p.photo as participant_photo, 
                             p.gender as participant_gender, 
                             p.birth_date, 
                             p.school_name, 
                             p.phone as participant_phone
                      FROM participant_assessments pa
                      JOIN participants p ON pa.participant_id = p.id
                      WHERE pa.id = ?");
$stmt->execute([$id]);
$a = $stmt->fetch();

if (!$a) {
    die("Data penilaian tidak ditemukan.");
}

$birthYear = '-';
if (!empty($a['birth_date'])) {
    $birthYear = date('Y', strtotime($a['birth_date']));
}

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
                ['label' => 'Lepasan bola diikuti gerakan pergelangan tangan (follow through)', 'score' => $a['shooting_wrist'] ?? 'E'],
            ]
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PENILAIAN_GPAI_<?= e(str_replace(' ', '_', $a['participant_name'])) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 24px 30px;
            font-size: 11pt;
            line-height: 1.4;
        }
        .print-header {
            display: flex;
            align-items: center;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-logo {
            width: 70px;
            height: 70px;
            margin-right: 20px;
        }
        .header-logo svg {
            width: 100%;
            height: 100%;
        }
        .header-text h1 {
            font-size: 16pt;
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-text h2 {
            font-size: 12pt;
            margin: 0 0 4px;
            color: #f97316;
            text-transform: uppercase;
        }
        .header-text p {
            margin: 0;
            font-size: 9.5pt;
            color: #444;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .info-table td {
            padding: 4px 6px;
            font-size: 10.5pt;
            vertical-align: top;
        }
        .info-label {
            width: 170px;
            font-weight: bold;
        }
        .notice-box {
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 9.5pt;
            margin-bottom: 18px;
        }
        .rubric-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .rubric-table th, .rubric-table td {
            border: 1px solid #333;
            padding: 8px 10px;
            font-size: 10pt;
        }
        .rubric-table th {
            background-color: #f1f5f9;
            text-align: left;
            font-weight: bold;
        }
        .score-box {
            display: flex;
            justify-content: space-between;
            border: 2px solid #000;
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 20px;
            text-align: center;
        }
        .score-item {
            flex: 1;
        }
        .score-item:not(:last-child) {
            border-right: 1px solid #ccc;
        }
        .score-val {
            font-size: 16pt;
            font-weight: bold;
            margin-top: 4px;
        }
        .notes-box {
            border: 1px solid #333;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 30px;
            min-height: 50px;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            width: 220px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin-top: 60px;
            margin-bottom: 4px;
        }
        @media print {
            body { padding: 15px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 16px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background-color: #f97316; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
            Cetak Dokumen
        </button>
    </div>

    <!-- Kop Header -->
    <div class="print-header">
        <div class="header-logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M6.2 6.2c2.4 2.4 2.4 6.4 0 8.8" />
                <path d="M17.8 6.2c-2.4 2.4-2.4 6.4 0 8.8" />
                <path d="M2 12h20" />
                <path d="M12 2v20" />
            </svg>
        </div>
        <div class="header-text">
            <h1>Crown Basketball Academy</h1>
            <h2>Lembar Observasi Penilaian Kemampuan Bermain Bola Basket</h2>
            <p>Game Performance Assessment Instrument (GPAI) &middot; Official Player Evaluation</p>
        </div>
    </div>

    <!-- Informasi Biodata -->
    <table class="info-table">
        <tr>
            <td class="info-label">Nama Atlet / Peserta</td>
            <td>: <strong><?= e($a['participant_name']) ?></strong></td>
            <td class="info-label">Rentang Tangan</td>
            <td>: <strong><?= formatMeasurement($a['arm_span'] ?? '') ?></strong></td>
        </tr>
        <tr>
            <td class="info-label">Jenis Kelamin</td>
            <td>: <?= $a['participant_gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
            <td class="info-label">Rentang Jari</td>
            <td>: <strong><?= formatMeasurement($a['finger_span'] ?? '') ?></strong></td>
        </tr>
        <tr>
            <td class="info-label">Tahun Kelahiran</td>
            <td>: <?= e($birthYear) ?></td>
            <td class="info-label">Vertical Jump</td>
            <td>: <strong><?= formatMeasurement($a['vertical_jump'] ?? '') ?></strong></td>
        </tr>
    </table>

    <div class="notice-box">
        <strong>Pedoman Skoring:</strong> E = Efektif (sesuai indikator) &middot; IE = In-Efektif (kurang/tidak sesuai). Skor = jumlah indikator E dari total <?= $totalIndicators ?> indikator.
    </div>

    <!-- Rubrik Penilaian -->
    <table class="rubric-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">No</th>
                <th>Aspek / Indikator Keterampilan (Skill Execution)</th>
                <th style="width: 90px; text-align: center;">Hasil</th>
                <th style="width: 140px; text-align: center;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $groupLetters = range('A', 'Z');
            $critNum = 1;
            foreach ($snapshotGroups as $gIdx => $group):
                $letter = $groupLetters[$gIdx % 26];
                $groupCriteria = $group['criteria'] ?? [];
            ?>
                <tr style="background-color: #f8fafc; font-weight: bold;">
                    <td colspan="4"><?= $letter ?>. <?= e($group['group_name']) ?></td>
                </tr>
                <?php foreach ($groupCriteria as $crit):
                    $critScore = $crit['score'] ?? 'E';
                ?>
                <tr>
                    <td style="text-align: center;"><?= $critNum++ ?></td>
                    <td><?= e($crit['label']) ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= e($critScore) ?></td>
                    <td style="text-align: center;"><?= $critScore === 'E' ? 'Efektif (Memenuhi)' : 'In-Efektif' ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Ringkasan Nilai -->
    <div class="score-box">
        <div class="score-item">
            <div>Jumlah Indikator E</div>
            <div class="score-val" style="color: #16a34a;"><?= $a['score_e'] ?> / <?= $totalIndicators ?></div>
        </div>
        <div class="score-item">
            <div>Jumlah Indikator IE</div>
            <div class="score-val" style="color: #dc2626;"><?= $a['score_ie'] ?> / <?= $totalIndicators ?></div>
        </div>
        <div class="score-item">
            <div>Nilai Persentase</div>
            <div class="score-val" style="color: #ea580c;"><?= number_format($a['final_score'], 1) ?>%</div>
        </div>
        <div class="score-item">
            <div>Predikat</div>
            <div class="score-val"><?= e($a['predicate']) ?></div>
        </div>
    </div>

    <!-- Catatan Pelatih -->
    <div><strong>Catatan / Rekomendasi Evaluasi Pelatih:</strong></div>
    <div class="notes-box">
        <?= !empty($a['notes']) ? nl2br(e($a['notes'])) : 'Telah mengikuti sesi observasi penilaian kemampuan bermain bola basket GPAI dengan baik.' ?>
    </div>

    <!-- Tanda Tangan -->
    <div class="signature-section">
        <div class="signature-box">
            <div>Mengetahui,</div>
            <div>Kepala Pelatih / Manajemen</div>
            <div class="signature-line"></div>
            <div><strong>Head Coach Crown</strong></div>
        </div>
        <div class="signature-box">
            <div>Dibuat oleh,</div>
            <div>Coach Penilai</div>
            <div class="signature-line"></div>
            <div><strong><?= e($a['coach_name']) ?></strong></div>
        </div>
    </div>

</body>
</html>

