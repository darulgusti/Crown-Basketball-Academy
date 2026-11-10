<?php
/**
 * Public Participant Attendance Portal (Absen Luar / Tanpa Login)
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDBConnection();
$errors = [];

// Get selected date (default to today)
$date = $_GET['date'] ?? date('Y-m-d');
// Basic format validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$session = 'Regular';

// Determine day name in Indonesian based on date
$dayIndex = (int)date('N', strtotime($date));
$dayNamesIndo = [
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
    5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
];
$dayName = $dayNamesIndo[$dayIndex] ?? 'Senin';

// Fetch active participants scheduled for this training day
$sqlParticipants = "SELECT p.id, p.name, p.photo, p.gender, p.school_name 
                    FROM participants p
                    JOIN participant_training_days ptd ON p.id = ptd.participant_id
                    JOIN training_days td ON ptd.training_day_id = td.id
                    WHERE td.day_name = ? AND p.status = 'active'
                    ORDER BY p.name ASC";
$stmtP = $db->prepare($sqlParticipants);
$stmtP->execute([$dayName]);
$participants = $stmtP->fetchAll();

// Fetch existing attendance records for this date
$sqlExisting = "SELECT participant_id, status, notes, recorded_by 
                 FROM participant_attendances 
                 WHERE date = ? AND session = ?";
$stmtE = $db->prepare($sqlExisting);
$stmtE->execute([$date, $session]);
$existingAttendances = $stmtE->fetchAll(PDO::FETCH_UNIQUE); // Keyed by participant_id

// Handle post submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    checkCSRF();
    
    $attendancesInput = $_POST['attendance'] ?? [];
    $notesInput = $_POST['notes'] ?? [];
    $recordedBy = $_SESSION['user_id'] ?? null;
    
    if (empty($participants)) {
        $errors[] = "Tidak ada peserta yang memiliki jadwal latihan pada hari " . $dayName . ".";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $sqlSave = "INSERT INTO participant_attendances (participant_id, date, session, status, notes, recorded_by) 
                        VALUES (?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                            status = VALUES(status), 
                            notes = VALUES(notes), 
                            recorded_by = COALESCE(VALUES(recorded_by), recorded_by)";
            $stmtSave = $db->prepare($sqlSave);
            
            $allowedStatuses = ['Hadir', 'Izin', 'Sakit', 'Tanpa Keterangan'];
            
            foreach ($participants as $p) {
                $pId = $p['id'];
                $status = $attendancesInput[$pId] ?? 'Hadir';
                if (!in_array($status, $allowedStatuses, true)) {
                    $status = 'Hadir';
                }
                $notes = trim($notesInput[$pId] ?? '');
                
                $stmtSave->execute([
                    $pId, 
                    $date, 
                    $session, 
                    $status, 
                    (empty($notes) ? null : $notes), 
                    $recordedBy
                ]);
            }
            
            $db->commit();
            
            setFlashMessage('success', 'Absensi peserta untuk tanggal ' . formatIndoDate($date) . ' berhasil disimpan!');
            header("Location: absen.php?date=" . urlencode($date));
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Gagal menyimpan absensi: " . $e->getMessage();
        }
    }
}

// Calculate summary counts
$countHadir = 0;
$countIzin = 0;
$countSakit = 0;
$countAlpha = 0;
$countBelumAbsen = 0;

foreach ($participants as $p) {
    $pId = $p['id'];
    if (isset($existingAttendances[$pId])) {
        $st = $existingAttendances[$pId]['status'];
        if ($st === 'Hadir') $countHadir++;
        elseif ($st === 'Izin') $countIzin++;
        elseif ($st === 'Sakit') $countSakit++;
        elseif ($st === 'Tanpa Keterangan') $countAlpha++;
    } else {
        $countBelumAbsen++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Peserta - Crown Basketball Academy</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: block;
            background-color: var(--bg-primary);
        }
        .public-navbar {
            height: 70px;
            background-color: var(--bg-secondary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-primary);
        }
        .navbar-brand svg {
            width: 30px;
            height: 30px;
            fill: var(--accent);
        }
        .public-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px 80px;
        }
        .page-header-card {
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.12) 0%, var(--bg-secondary) 100%);
            border-radius: var(--border-radius);
            padding: 28px;
            border: 1px solid rgba(249, 115, 22, 0.2);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-pill {
            background-color: var(--bg-secondary);
            border: 1px solid var(--bg-tertiary);
            border-radius: 12px;
            padding: 12px 16px;
            text-align: center;
        }
        .stat-pill .num {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-pill .label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        .table-avatar {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid var(--bg-tertiary);
        }
        .table-avatar-placeholder {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background-color: var(--bg-primary);
            border: 2px solid var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--accent);
            font-size: 1.1rem;
        }
        .sticky-save-bar {
            position: sticky;
            bottom: 20px;
            background-color: var(--bg-secondary);
            padding: 14px 20px;
            border-radius: 14px;
            border: 1px solid rgba(249, 115, 22, 0.3);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 90;
            margin-top: 24px;
        }
        .status-select {
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 12px;
        }
        .status-select.val-Hadir {
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.4);
            background-color: rgba(16, 185, 129, 0.05);
        }
        .status-select.val-Izin {
            color: #06b6d4;
            border-color: rgba(6, 182, 212, 0.4);
            background-color: rgba(6, 182, 212, 0.05);
        }
        .status-select.val-Sakit {
            color: #f59e0b;
            border-color: rgba(245, 158, 11, 0.4);
            background-color: rgba(245, 158, 11, 0.05);
        }
        .status-select.val-Alpha {
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.4);
            background-color: rgba(239, 68, 68, 0.05);
        }
        @media (max-width: 768px) {
            .public-navbar {
                padding: 0 16px;
            }
            .public-container {
                padding: 16px 12px 100px;
            }
            .page-header-card {
                padding: 18px;
            }
            .sticky-save-bar {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            .sticky-save-bar .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <nav class="public-navbar">
        <a href="index" class="navbar-brand" style="text-decoration: none;">
            <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2h2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H9v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 1.54-.37 2.99-1.1 4.29z"/>
            </svg>
            <span>CROWN BASKETBALL</span>
        </a>
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="index" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Beranda</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard" class="btn btn-primary btn-sm">Dashboard &rarr;</a>
            <?php else: ?>
                <a href="login" class="btn btn-primary btn-sm">Login / Masuk</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="public-container">
        
        <!-- Header Banner -->
        <div class="page-header-card">
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">
                    Absensi Latihan Peserta
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.95rem;">
                    Pencatatan kehadiran peserta akademi pada sesi latihan hari <strong><?= $dayName ?></strong>.
                </p>
            </div>
            
            <form action="absen" method="GET" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div style="display: flex; flex-direction: column;">
                    <label for="date-select" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600;">
                        PILIH TANGGAL LATIHAN
                    </label>
                    <input type="date" id="date-select" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()" style="font-weight: 600;">
                </div>
                <?php if ($date !== date('Y-m-d')): ?>
                    <a href="absen" class="btn btn-secondary btn-sm" style="margin-top: 18px;" title="Kembali ke Hari Ini">Hari Ini</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-alert" style="margin-bottom: 20px;">
                <ul style="padding-left: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Stats Counter -->
        <div class="stats-grid">
            <div class="stat-pill">
                <div class="num" style="color: var(--text-primary);"><?= count($participants) ?></div>
                <div class="label">Total Terjadwal</div>
            </div>
            <div class="stat-pill">
                <div class="num" style="color: var(--success);"><?= $countHadir ?></div>
                <div class="label">Hadir</div>
            </div>
            <div class="stat-pill">
                <div class="num" style="color: var(--info);"><?= $countIzin ?></div>
                <div class="label">Izin</div>
            </div>
            <div class="stat-pill">
                <div class="num" style="color: var(--warning);"><?= $countSakit ?></div>
                <div class="label">Sakit</div>
            </div>
            <div class="stat-pill">
                <div class="num" style="color: var(--danger);"><?= $countAlpha ?></div>
                <div class="label">Alpha</div>
            </div>
        </div>

        <!-- Attendance Card -->
        <div class="data-card" style="padding: 24px;">
            
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 20px;">
                <!-- Fast Search input -->
                <div style="position: relative; max-width: 320px; width: 100%;">
                    <input type="text" id="participantSearch" class="form-control" placeholder="Cari nama peserta..." onkeyup="filterParticipants()" style="padding-left: 36px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-secondary);">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>

                <!-- Fast Actions -->
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setAllAttendance('Hadir')" title="Setel semua status menjadi Hadir">
                        ✓ Tandai Semua Hadir
                    </button>
                </div>
            </div>

            <?php if (count($participants) > 0): ?>
                <form id="attendanceForm" action="absen.php?date=<?= urlencode($date) ?>" method="POST">
                    <?= csrfField(); ?>

                    <div class="table-responsive">
                        <table class="custom-table" id="participantsTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Peserta</th>
                                    <th style="width: 220px;">Status Kehadiran</th>
                                    <th>Catatan / Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($participants as $p): 
                                    $pId = $p['id'];
                                    $existing = $existingAttendances[$pId] ?? null;
                                    $currentStatus = $existing ? $existing['status'] : 'Hadir';
                                    $currentNotes = $existing ? $existing['notes'] : '';
                                ?>
                                    <tr class="participant-row" data-name="<?= strtolower(e($p['name'])) ?>">
                                        <td style="color: var(--text-secondary); font-size: 0.85rem;">
                                            <?= $no++ ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <?php if (!empty($p['photo'])): ?>
                                                    <img src="<?= e(getPhotoSrc($p['photo'], 'uploads/participants/')) ?>" alt="<?= e($p['name']) ?>" class="table-avatar">
                                                <?php else: ?>
                                                    <div class="table-avatar-placeholder">
                                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem;">
                                                        <?= e($p['name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                                        <?= e($p['school_name'] ?: ($p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan')) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <select name="attendance[<?= $pId ?>]" class="form-control status-select <?= 'val-' . ($currentStatus === 'Tanpa Keterangan' ? 'Alpha' : $currentStatus) ?>" onchange="updateSelectColor(this)">
                                                <option value="Hadir" <?= $currentStatus === 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                                <option value="Izin" <?= $currentStatus === 'Izin' ? 'selected' : '' ?>>Izin</option>
                                                <option value="Sakit" <?= $currentStatus === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                                <option value="Tanpa Keterangan" <?= $currentStatus === 'Tanpa Keterangan' ? 'selected' : '' ?>>Tanpa Keterangan</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="notes[<?= $pId ?>]" class="form-control" value="<?= e($currentNotes) ?>" placeholder="Catatan tambahan (opsional)...">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sticky Save Bar -->
                    <div class="sticky-save-bar">
                        <div style="font-size: 0.9rem; color: var(--text-secondary);">
                            Tanggal: <strong><?= formatIndoDate($date) ?></strong> (<?= count($participants) ?> Peserta)
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-weight: 700; font-size: 0.95rem;">
                                💾 Simpan Absensi
                            </button>
                        </div>
                    </div>

                </form>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-secondary); padding: 50px 20px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: var(--text-secondary); margin-bottom: 12px;">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <p style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Tidak Ada Peserta Terjadwal
                    </p>
                    <p style="font-size: 0.9rem;">
                        Tidak ada peserta aktif yang memiliki jadwal latihan pada hari <strong><?= $dayName ?></strong>.
                    </p>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <!-- Main Client Script & Flash Toasts -->
    <script src="assets/js/main.js"></script>
    <?php renderFlashMessages(); ?>

    <script>
        // Update color styling of select elements dynamically
        function updateSelectColor(select) {
            select.classList.remove('val-Hadir', 'val-Izin', 'val-Sakit', 'val-Alpha');
            const val = select.value === 'Tanpa Keterangan' ? 'Alpha' : select.value;
            select.classList.add('val-' + val);
        }

        // Fast client-side filter
        function filterParticipants() {
            const input = document.getElementById('participantSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.participant-row');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                if (name.includes(input)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Quick action: set all participants to specific status
        function setAllAttendance(status) {
            const selects = document.querySelectorAll('.status-select');
            selects.forEach(select => {
                select.value = status;
                updateSelectColor(select);
            });
            if (typeof showToast === 'function') {
                showToast('success', 'Semua peserta ditandai: ' + status);
            }
        }
    </script>
</body>
</html>

