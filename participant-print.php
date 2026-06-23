<?php
/**
 * Printable Participant Biodata Template
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Requires authentication
requireAuth();

$db = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
    die("Data peserta tidak ditemukan.");
}

// Calculate age
$birthDate = new DateTime($p['birth_date']);
$today = new DateTime();
$age = $today->diff($birthDate)->y;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BIODATA_<?= e(str_replace(' ', '_', $p['name'])) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 30px;
            font-size: 12pt;
            line-height: 1.6;
        }
        .print-header {
            display: flex;
            align-items: center;
            border-bottom: 3px double #000;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .logo-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #000;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5rem;
            margin-right: 20px;
        }
        .header-text h1 {
            font-size: 20pt;
            margin: 0 0 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header-text p {
            margin: 0;
            font-size: 10pt;
            color: #555;
        }
        .document-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 25px;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .profile-container {
            display: flex;
            gap: 30px;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        .photo-box {
            width: 140px;
            height: 180px;
            border: 2px solid #000;
            padding: 2px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #eee;
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 6px 4px;
            vertical-align: top;
        }
        .info-table td.label {
            width: 180px;
            font-weight: bold;
        }
        .info-table td.semi {
            width: 15px;
            text-align: center;
        }
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background-color: #f0f0f0;
            padding: 6px 10px;
            margin: 25px 0 12px;
            border-left: 5px solid #000;
            text-transform: uppercase;
        }
        .footer-signature {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-space {
            height: 75px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Floating Print Button (Only visible on screen, not on print) -->
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 999;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #000; color: #fff; border: none; cursor: pointer; font-weight: bold; border-radius: 4px;">Cetak Halaman Ini</button>
        <button onclick="window.close()" style="padding: 10px 20px; background-color: #ccc; color: #000; border: none; cursor: pointer; font-weight: bold; border-radius: 4px; margin-left: 5px;">Tutup</button>
    </div>

    <!-- Letter Head -->
    <div class="print-header">
        <div class="logo-placeholder">CBA</div>
        <div class="header-text">
            <h1>Crown Basketball Academy</h1>
            <p>Alamat: Gg. Basket No. 10, Jakarta Selatan | Telp: +62 812-3456-789 | Email: info@crownbasketball.com</p>
        </div>
    </div>

    <div class="document-title">Lembar Biodata Lengkap Peserta</div>

    <div class="profile-container">
        <!-- Photo -->
        <div class="photo-box">
            <?php if ($p['photo'] && file_exists(__DIR__ . '/uploads/participants/' . $p['photo'])): ?>
                <img src="uploads/participants/<?= e($p['photo']) ?>" alt="<?= e($p['name']) ?>">
            <?php else: ?>
                <span style="font-size: 9pt; color: #666; text-align: center; padding: 10px;">Foto 3x4<br>Belum Diunggah</span>
            <?php endif; ?>
        </div>

        <!-- Primary Registration Information -->
        <table class="info-table">
            <tr>
                <td class="label">Nama Lengkap</td>
                <td class="semi">:</td>
                <td><strong><?= e($p['name']) ?></strong></td>
            </tr>
            <tr>
                <td class="label">Status Anggota</td>
                <td class="semi">:</td>
                <td><?= $p['status'] === 'active' ? 'AKTIF' : 'NONAKTIF' ?></td>
            </tr>
            <tr>
                <td class="label">Tanggal Terdaftar</td>
                <td class="semi">:</td>
                <td><?= formatIndoDate($p['registration_date']) ?></td>
            </tr>
        </table>
    </div>

    <div class="section-title">I. Data Pribadi Peserta</div>
    <table class="info-table">
        <tr>
            <td class="label">Tempat / Tanggal Lahir</td>
            <td class="semi">:</td>
            <td><?= e($p['birth_place']) ?>, <?= formatIndoDate($p['birth_date']) ?> (<?= $age ?> Tahun)</td>
        </tr>
        <tr>
            <td class="label">Jenis Kelamin</td>
            <td class="semi">:</td>
            <td><?= $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
        </tr>
        <tr>
            <td class="label">Tinggi / Berat Badan</td>
            <td class="semi">:</td>
            <td>Tinggi: <?= $p['height'] ?> cm | Berat: <?= $p['weight'] ?> kg</td>
        </tr>
        <tr>
            <td class="label">Asal Sekolah</td>
            <td class="semi">:</td>
            <td><?= e($p['school_name']) ?></td>
        </tr>
        <tr>
            <td class="label">No. HP / WhatsApp</td>
            <td class="semi">:</td>
            <td><?= e($p['phone']) ?></td>
        </tr>
        <tr>
            <td class="label">Alamat Lengkap</td>
            <td class="semi">:</td>
            <td><?= e($p['address']) ?></td>
        </tr>
    </table>

    <div class="section-title">II. Pengalaman Basket & Jadwal Latihan</div>
    <table class="info-table">
        <tr>
            <td class="label">Hari Latihan</td>
            <td class="semi">:</td>
            <td><?= $p['training_days'] ? e($p['training_days']) : '-' ?></td>
        </tr>
        <tr>
            <td class="label">Memiliki Pengalaman</td>
            <td class="semi">:</td>
            <td><?= e($p['basketball_experience']) ?></td>
        </tr>
        <?php if ($p['basketball_experience'] === 'Ya'): ?>
            <tr>
                <td class="label">Nama Klub Sebelumnya</td>
                <td class="semi">:</td>
                <td><?= e($p['previous_club'] ?: '-') ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="section-title">III. Data Orang Tua / Wali</div>
    <table class="info-table">
        <tr>
            <td class="label">Nama Orang Tua / Wali</td>
            <td class="semi">:</td>
            <td><?= e($p['parent_name']) ?></td>
        </tr>
        <tr>
            <td class="label">Pekerjaan Orang Tua</td>
            <td class="semi">:</td>
            <td><?= e($p['parent_job']) ?></td>
        </tr>
        <tr>
            <td class="label">No. HP Orang Tua / Wali</td>
            <td class="semi">:</td>
            <td><?= e($p['parent_phone']) ?></td>
        </tr>
    </table>

    <!-- Signature Boxes -->
    <div class="footer-signature">
        <div class="signature-box">
            <p>Mengetahui,</p>
            <p>Orang Tua / Wali Peserta</p>
            <div class="signature-space"></div>
            <p>( _______________________ )</p>
        </div>
        <div class="signature-box">
            <p>Jakarta, <?= formatIndoDate(date('Y-m-d')) ?></p>
            <p>Petugas Pendaftaran,</p>
            <div class="signature-space"></div>
            <p><strong><?= e($_SESSION['username']) ?></strong></p>
        </div>
    </div>

    <script>
        // Trigger print window automatically
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
