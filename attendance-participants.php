<?php
/**
 * Participant Attendance Management
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

// Admin & Coach can record attendance
requireCoachOrAdmin();

$db = getDBConnection();
$errors = [];
$successMessage = null;

// Get selected date. The database still requires a session value, so use one
// internal value without exposing session choices in the attendance UI.
$date = $_GET['date'] ?? date('Y-m-d');
$session = 'Regular';

// Determine day name in Indonesian based on date
$dayIndex = date('N', strtotime($date));
$dayNamesIndo = [
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
    5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
];
$dayName = $dayNamesIndo[$dayIndex];

// Fetch active participants scheduled for this training day
$sqlParticipants = "SELECT p.id, p.name 
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
$existingAttendances = $stmtE->fetchAll(PDO::FETCH_UNIQUE); // Fetch with participant_id as key

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    
    $attendancesInput = $_POST['attendance'] ?? []; // Array formatted: [participant_id => status]
    $notesInput = $_POST['notes'] ?? []; // Array formatted: [participant_id => notes]
    $recordedBy = $_SESSION['user_id'];
    
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
                            recorded_by = VALUES(recorded_by)";
            $stmtSave = $db->prepare($sqlSave);
            
            foreach ($participants as $p) {
                $pId = $p['id'];
                $status = $attendancesInput[$pId] ?? 'Tanpa Keterangan'; // Default fallback status
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
            
            // Reload page or reload data
            setFlashMessage('success', 'Absensi peserta untuk tanggal ' . formatIndoDate($date) . ' berhasil disimpan.');
            header("Location: attendance-participants.php?date=" . urlencode($date));
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Gagal menyimpan absensi: " . $e->getMessage();
        }
    }
}
// Load page header layout here, after redirects are completed
$pageTitle = "Absensi Peserta";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Tab Actions -->
<div class="card-actions" style="margin-bottom: 24px;">
    <div style="display: flex; gap: 8px;">
        <a href="attendance-participants.php?date=<?= urlencode($date) ?>" class="btn btn-primary">
            Pencatatan Absensi
        </a>
        <a href="attendance-participants-history" class="btn btn-secondary">
            Riwayat & Rekap Absensi
        </a>
    </div>
</div>

<!-- Selection Panel -->
<div class="data-card">
    <form action="attendance-participants" method="GET">
        <div class="card-actions" style="margin-bottom: 0;">
            <div class="search-filter-box">
                <div class="form-group" style="min-width: 200px;">
                    <label for="date">Pilih Tanggal Latihan</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
                </div>
            </div>
            
            <div style="display: flex; align-items: flex-end; padding-top: 20px;">
                <span class="badge badge-active" style="padding: 12px 20px; font-size: 0.9rem;">
                    Hari Latihan: <strong><?= $dayName ?></strong>
                </span>
            </div>
        </div>
    </form>
</div>

<!-- Attendance Form Sheet -->
<div class="data-card">
    <div class="chart-header" style="margin-bottom: 24px;">
        <span class="chart-title">Daftar Hadir Peserta Jadwal Hari <?= $dayName ?></span>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
            Tanggal: <strong><?= formatIndoDate($date) ?></strong>
        </span>
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

    <?php if (count($participants) > 0): ?>
        <form action="attendance-participants.php?date=<?= urlencode($date) ?>" method="POST">
            <?= csrfField(); ?>
            
            <div class="table-responsive">
                <table class="custom-table attendance-list-table">
                    <thead>
                        <tr>
                            <th>Nama Lengkap</th>
                            <th style="width: 250px;">Status Kehadiran</th>
                            <th>Catatan Opsional</th>
                            <th style="font-size: 0.8rem; color: var(--text-secondary); width: 120px;">Pencatat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p): 
                            $pId = $p['id'];
                            $existing = $existingAttendances[$pId] ?? null;
                            $currentStatus = $existing ? $existing['status'] : 'Hadir';
                            $currentNotes = $existing ? $existing['notes'] : '';
                            
                            // Fetch user name who recorded this attendance
                            $recordedByName = '-';
                            if ($existing && $existing['recorded_by']) {
                                $stmtUser = $db->prepare("SELECT username FROM users WHERE id = ?");
                                $stmtUser->execute([$existing['recorded_by']]);
                                $recordedByName = $stmtUser->fetchColumn() ?: '-';
                            }
                        ?>
                            <tr>
                                <td><strong><?= e($p['name']) ?></strong></td>
                                <td>
                                    <select name="attendance[<?= $pId ?>]" class="form-control">
                                        <option value="Hadir" <?= $currentStatus === 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                        <option value="Izin" <?= $currentStatus === 'Izin' ? 'selected' : '' ?>>Izin</option>
                                        <option value="Sakit" <?= $currentStatus === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                        <option value="Tanpa Keterangan" <?= $currentStatus === 'Tanpa Keterangan' ? 'selected' : '' ?>>Tanpa Keterangan</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="notes[<?= $pId ?>]" class="form-control" value="<?= e($currentNotes) ?>" placeholder="Catatan tambahan...">
                                </td>
                                <td>
                                    <span style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($recordedByName) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" class="btn btn-primary">Simpan Seluruh Absensi</button>
            </div>
        </form>
    <?php else: ?>
        <div style="text-align: center; color: var(--text-secondary); padding: 40px 0;">
            Tidak ada peserta aktif yang memiliki jadwal latihan pada hari <strong><?= $dayName ?></strong>.
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
