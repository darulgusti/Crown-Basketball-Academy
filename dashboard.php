<?php
/**
 * Shared Dashboard Controller & Views
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php'; // already performs auth checks

$db = getDBConnection();
getTrainingDays($db);
ensureAssessmentTables($db);
$role = $_SESSION['role'];
$todayDate = date('Y-m-d');
$todayDayIndex = date('N'); // 1 (Monday) to 7 (Sunday)

// Map index to Indonesian day name
$dayNamesIndo = [
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
    5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
];
$todayDayName = $dayNamesIndo[$todayDayIndex];

if ($role === 'admin') {
    // ----------------------------------------------------
    // ADMIN DASHBOARD DATA LOAD
    // ----------------------------------------------------
    
    // 1. Total Active Participants
    $activeParticipants = $db->query("SELECT COUNT(*) FROM participants WHERE status = 'active'")->fetchColumn();
    
    // 2. Total Active Coaches
    $activeCoaches = $db->query("SELECT COUNT(*) FROM coaches WHERE status = 'active'")->fetchColumn();
    
    // 3. Participants by Gender
    $genderStats = $db->query("SELECT gender, COUNT(*) as count FROM participants GROUP BY gender")->fetchAll();
    $maleCount = 0;
    $femaleCount = 0;
    foreach ($genderStats as $gs) {
        if ($gs['gender'] === 'L') $maleCount = $gs['count'];
        if ($gs['gender'] === 'P') $femaleCount = $gs['count'];
    }
    

    // 5. Participants by Training Day (chronological order, active participants)
    $dayStats = $db->query("SELECT td.day_name, COUNT(p.id) as count 
                            FROM training_days td 
                            LEFT JOIN participant_training_days ptd ON td.id = ptd.training_day_id 
                            LEFT JOIN participants p ON ptd.participant_id = p.id AND p.status = 'active'
                            GROUP BY td.id, td.day_name
                            ORDER BY FIELD(td.day_name, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu Sesi 1', 'Sabtu Sesi 2', 'Minggu Sesi 1', 'Minggu Sesi 2'), td.id ASC")->fetchAll();
                            
    // 6. Today Participant Attendance Summary
    $pAttendanceStats = $db->query("SELECT status, COUNT(*) as count 
                                     FROM participant_attendances 
                                     WHERE date = '$todayDate' 
                                     GROUP BY status")->fetchAll();
    $pAttendancesToday = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Tanpa Keterangan' => 0];
    foreach ($pAttendanceStats as $pas) {
        $pAttendancesToday[$pas['status']] = $pas['count'];
    }

    // 7. GPAI Assessment Summary
    $assessSummary = $db->query("SELECT COUNT(*) as total_assessments,
                                        COUNT(DISTINCT participant_id) as assessed_athletes,
                                        COALESCE(AVG(final_score), 0) as avg_score
                                 FROM participant_assessments")->fetch();
    

    
} elseif ($role === 'coach') {
    // ----------------------------------------------------
    // COACH DASHBOARD DATA LOAD
    // ----------------------------------------------------
    $coachId = $_SESSION['coach_id'] ?? 0;
    
    // 1. Total Participants (all participants in the system or coach-specific? The requirement states: "Total peserta" - let's count active participants)
    $totalParticipantsCount = $db->query("SELECT COUNT(*) FROM participants WHERE status = 'active'")->fetchColumn();
    
    // 2. Coach schedule today (Is the coach scheduled to train today?)
    $stmtSched = $db->prepare("SELECT COUNT(*) 
                               FROM coach_training_days ctd 
                               JOIN training_days td ON ctd.training_day_id = td.id 
                               WHERE ctd.coach_id = ? AND td.day_name LIKE CONCAT(?, '%')");
    $stmtSched->execute([$coachId, $todayDayName]);
    $isScheduledToday = $stmtSched->fetchColumn() > 0;
    
    // List of coach schedules (which days of week they are training)
    $stmtAllSched = $db->prepare("SELECT td.day_name 
                                  FROM coach_training_days ctd 
                                  JOIN training_days td ON ctd.training_day_id = td.id 
                                  WHERE ctd.coach_id = ? 
                                  ORDER BY FIELD(td.day_name, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu Sesi 1', 'Sabtu Sesi 2', 'Minggu Sesi 1', 'Minggu Sesi 2'), td.id ASC");
    $stmtAllSched->execute([$coachId]);
    $coachSchedules = $stmtAllSched->fetchAll(PDO::FETCH_COLUMN);
    
    // 3. Participants scheduled today and their attendance status (if taken)
    $stmtPToday = $db->prepare("SELECT p.id, p.name, pa.status as attendance_status, pa.session
                                 FROM participants p
                                 JOIN participant_training_days ptd ON p.id = ptd.participant_id
                                 JOIN training_days td ON ptd.training_day_id = td.id
                                 LEFT JOIN participant_attendances pa ON p.id = pa.participant_id AND pa.date = ?
                                 WHERE td.day_name LIKE CONCAT(?, '%') AND p.status = 'active'
                                 ORDER BY p.name ASC");
    $stmtPToday->execute([$todayDate, $todayDayName]);
    $participantsToday = $stmtPToday->fetchAll();
    
    // Summarize participant attendance today
    $pAttendancesToday = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Tanpa Keterangan' => 0, 'Belum Diabsen' => 0];
    foreach ($participantsToday as $pt) {
        if ($pt['attendance_status']) {
            $pAttendancesToday[$pt['attendance_status']]++;
        } else {
            $pAttendancesToday['Belum Diabsen']++;
        }
    }
    

}
?>

<?php if ($role === 'admin'): ?>
    <!-- ====================================================
         ADMIN VIEW
         ==================================================== -->
    
    <!-- Metrics Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-info">
                <h3>Peserta Aktif</h3>
                <div class="metric-value"><?= $activeParticipants ?></div>
            </div>
            <div class="metric-icon">
                <!-- Users Whistle -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Pelatih Aktif</h3>
                <div class="metric-value"><?= $activeCoaches ?></div>
            </div>
            <div class="metric-icon" style="color: var(--info); background-color: rgba(6, 182, 212, 0.1);">
                <!-- User-Tie Whistle -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>L / P Peserta</h3>
                <div class="metric-value"><?= $maleCount ?> / <?= $femaleCount ?></div>
            </div>
            <div class="metric-icon" style="color: var(--success); background-color: rgba(16, 185, 129, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a5 5 0 1 0 5 5 5 5 0 0 0-5-5zM12 14c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5z"/>
                </svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Hari Latihan</h3>
                <div class="metric-value"><?= count(array_filter($dayStats, fn($d) => $d['count'] > 0)) ?></div>
            </div>
            <div class="metric-icon" style="color: var(--warning); background-color: rgba(245, 158, 11, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Charts & Tables Grid -->
    <div class="charts-grid" style="align-items: stretch;">
        
        <!-- Left Column: Attendance Today + GPAI Summary -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Attendance Today Panel -->
            <div class="chart-card" style="margin-bottom: 0;">
                <div class="chart-header">
                    <div>
                        <span class="chart-title">Absensi Hari Ini</span>
                        <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px;"><?= formatIndoDate($todayDate) ?></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="badge badge-active"><?= $todayDayName ?></span>
                        <a href="attendance-participants" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 0.75rem;">Buka &rarr;</a>
                    </div>
                </div>
                
                <div class="stats-list">
                    <?php 
                    $pTotalToday = array_sum($pAttendancesToday);
                    foreach ($pAttendancesToday as $status => $count): 
                        $percent = $pTotalToday > 0 ? ($count / $pTotalToday) * 100 : 0;
                    ?>
                        <div class="stats-item">
                            <div class="stats-label" style="flex-shrink: 0;">
                                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $status)) ?>" style="min-width: 148px; white-space: nowrap; justify-content: center; padding: 5px 10px;"><?= $status ?></span>
                            </div>
                            <div class="stats-bar-wrapper">
                                <div class="stats-bar" style="width: <?= $percent ?>%; <?= $status === 'Tanpa Keterangan' ? 'background: var(--danger)' : '' ?>"></div>
                            </div>
                            <span class="stats-count"><?= $count ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- GPAI Assessment Summary Panel -->
            <div class="chart-card" style="flex: 1; display: flex; flex-direction: column; justify-content: space-between; margin-bottom: 0;">
                <div>
                    <div class="chart-header">
                        <span class="chart-title">Ringkasan Penilaian (GPAI)</span>
                        <a href="assessments" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 0.75rem;">Lihat Semua &rarr;</a>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                        <div style="background: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 10px; padding: 14px 10px; text-align: center;">
                            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em;">Total Lembar</div>
                            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin-top: 4px;"><?= number_format((int)$assessSummary['total_assessments']) ?></div>
                        </div>
                        <div style="background: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 10px; padding: 14px 10px; text-align: center;">
                            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em;">Atlet Dinilai</div>
                            <div style="font-size: 1.35rem; font-weight: 800; color: var(--accent); margin-top: 4px;"><?= number_format((int)$assessSummary['assessed_athletes']) ?></div>
                        </div>
                        <div style="background: var(--bg-primary); border: 1px solid var(--bg-tertiary); border-radius: 10px; padding: 14px 10px; text-align: center;">
                            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em;">Rata-rata Skor</div>
                            <div style="font-size: 1.35rem; font-weight: 800; color: var(--success); margin-top: 4px;"><?= number_format((float)$assessSummary['avg_score'], 0) ?>%</div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="assessment-add" class="btn btn-primary btn-sm" style="flex: 1; text-align: center; justify-content: center;">+ Input Penilaian Baru</a>
                    <a href="assessment-settings" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center; justify-content: center;">Atur Kriteria</a>
                </div>
            </div>
        </div>

        <!-- Right Column: Participants Schedules stats -->
        <div class="chart-card" style="margin-bottom: 0;">
            <div class="chart-header">
                <span class="chart-title">Peserta Berdasarkan Hari Latihan</span>
                <a href="participants" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 0.75rem;">Data Peserta &rarr;</a>
            </div>
            <div class="stats-list">
                <?php 
                $maxDayCount = max(array_merge([1], array_column($dayStats, 'count')));
                foreach ($dayStats as $ds): 
                    $percent = $maxDayCount > 0 ? ($ds['count'] / $maxDayCount) * 100 : 0;
                ?>
                    <div class="stats-item">
                        <div class="stats-label" style="min-width: 118px; width: 118px; white-space: nowrap; font-weight: 600; font-size: 0.88rem; color: var(--text-primary);"><?= e($ds['day_name']) ?></div>
                        <div class="stats-bar-wrapper">
                            <div class="stats-bar" style="width: <?= $percent ?>%;"></div>
                        </div>
                        <span class="stats-count"><?= $ds['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>



<?php elseif ($role === 'coach'): ?>
    <!-- ====================================================
         COACH VIEW
         ==================================================== -->
    
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-info">
                <h3>Total Peserta</h3>
                <div class="metric-value"><?= $totalParticipantsCount ?></div>
            </div>
            <div class="metric-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Jadwal Melatih Anda</h3>
                <div class="metric-value metric-value-text">
                    <?= count($coachSchedules) > 0 ? e(implode(', ', $coachSchedules)) : 'Belum Ada Jadwal' ?>
                </div>
            </div>
            <div class="metric-icon" style="color: var(--info); background-color: rgba(6, 182, 212, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Jadwal Hari Ini</h3>
                <div class="metric-value metric-value-status">
                    <?= $isScheduledToday ? '<span style="color:var(--success)">Ada Jadwal (' . $todayDayName . ')</span>' : '<span style="color:var(--text-secondary)">Tidak Ada Jadwal</span>' ?>
                </div>
            </div>
            <div class="metric-icon" style="color: var(--warning); background-color: rgba(245, 158, 11, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="charts-grid">
        <!-- Today Participant Attendance for Coach Scheduled Day -->
        <div class="chart-card" style="grid-column: 1 / -1;">
            <div class="chart-header">
                <span class="chart-title">Absensi Peserta Hari Ini (<?= $todayDayName . ', ' . formatIndoDate($todayDate) ?>)</span>
            </div>
            
            <?php if ($isScheduledToday): ?>
                <div class="stats-list" style="margin-bottom: 24px;">
                    <?php foreach ($pAttendancesToday as $status => $count): 
                        $totalScheduled = count($participantsToday);
                        $percent = $totalScheduled > 0 ? ($count / $totalScheduled) * 100 : 0;
                    ?>
                        <div class="stats-item">
                            <div class="stats-label">
                                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $status)) ?>" style="width: 140px; justify-content: center;"><?= $status ?></span>
                            </div>
                            <div class="stats-bar-wrapper">
                                <div class="stats-bar" style="width: <?= $percent ?>%; <?= $status === 'Belum Diabsen' ? 'background: var(--bg-tertiary)' : '' ?>"></div>
                            </div>
                            <span class="stats-count"><?= $count ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: right;">
                    <a href="attendance-participants.php?date=<?= $todayDate ?>" class="btn btn-primary btn-sm">Kelola Absensi Sekarang</a>
                </div>
            <?php else: ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 30px;">Anda tidak memiliki jadwal melatih hari ini (<?= $todayDayName ?>).</p>
            <?php endif; ?>
        </div>


    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
