<?php
/**
 * Shared Dashboard Controller & Views
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php'; // already performs auth checks

$db = getDBConnection();
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
    
    // 4. Participants by Year of Birth
    $yearStats = $db->query("SELECT YEAR(birth_date) as year, COUNT(*) as count FROM participants GROUP BY YEAR(birth_date) ORDER BY year DESC LIMIT 5")->fetchAll();
    
    // 5. Participants by Training Day
    $dayStats = $db->query("SELECT td.day_name, COUNT(ptd.participant_id) as count 
                            FROM training_days td 
                            LEFT JOIN participant_training_days ptd ON td.id = ptd.training_day_id 
                            GROUP BY td.id, td.day_name")->fetchAll();
                            
    // 6. Today Participant Attendance Summary
    $pAttendanceStats = $db->query("SELECT status, COUNT(*) as count 
                                     FROM participant_attendances 
                                     WHERE date = '$todayDate' 
                                     GROUP BY status")->fetchAll();
    $pAttendancesToday = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Tanpa Keterangan' => 0];
    foreach ($pAttendanceStats as $pas) {
        $pAttendancesToday[$pas['status']] = $pas['count'];
    }
    

    
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
                               WHERE ctd.coach_id = ? AND td.day_name = ?");
    $stmtSched->execute([$coachId, $todayDayName]);
    $isScheduledToday = $stmtSched->fetchColumn() > 0;
    
    // List of coach schedules (which days of week they are training)
    $stmtAllSched = $db->prepare("SELECT td.day_name 
                                  FROM coach_training_days ctd 
                                  JOIN training_days td ON ctd.training_day_id = td.id 
                                  WHERE ctd.coach_id = ? 
                                  ORDER BY td.id");
    $stmtAllSched->execute([$coachId]);
    $coachSchedules = $stmtAllSched->fetchAll(PDO::FETCH_COLUMN);
    
    // 3. Participants scheduled today and their attendance status (if taken)
    $stmtPToday = $db->prepare("SELECT p.id, p.name, pa.status as attendance_status, pa.session
                                 FROM participants p
                                 JOIN participant_training_days ptd ON p.id = ptd.participant_id
                                 JOIN training_days td ON ptd.training_day_id = td.id
                                 LEFT JOIN participant_attendances pa ON p.id = pa.participant_id AND pa.date = ?
                                 WHERE td.day_name = ? AND p.status = 'active'
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
    <div class="charts-grid">
        
        <!-- Attendance Today Panel -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">Absensi Hari Ini (<?= formatIndoDate($todayDate) ?>)</span>
                <span class="badge badge-active"><?= $todayDayName ?></span>
            </div>
            
            <h4 style="font-size: 0.9rem; margin-bottom: 12px; color: var(--accent);">Peserta Latihan</h4>
            <div class="stats-list" style="margin-bottom: 24px;">
                <?php 
                $pTotalToday = array_sum($pAttendancesToday);
                foreach ($pAttendancesToday as $status => $count): 
                    $percent = $pTotalToday > 0 ? ($count / $pTotalToday) * 100 : 0;
                ?>
                    <div class="stats-item">
                        <div class="stats-label">
                            <span class="badge badge-<?= strtolower(str_replace(' ', '-', $status)) ?>" style="width: 140px; justify-content: center;"><?= $status ?></span>
                        </div>
                        <div class="stats-bar-wrapper">
                            <div class="stats-bar" style="width: <?= $percent ?>%; <?= $status === 'Tanpa Keterangan' ? 'background: var(--danger)' : '' ?>"></div>
                        </div>
                        <span class="stats-count"><?= $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>


        </div>

        <!-- Participants Schedules stats -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">Peserta Berdasarkan Hari Latihan</span>
            </div>
            <div class="stats-list">
                <?php 
                $maxDayCount = max(array_column($dayStats, 'count') ?: [1]);
                foreach ($dayStats as $ds): 
                    $percent = ($ds['count'] / $maxDayCount) * 100;
                ?>
                    <div class="stats-item">
                        <div class="stats-label" style="width: 80px; font-weight: 500;"><?= e($ds['day_name']) ?></div>
                        <div class="stats-bar-wrapper">
                            <div class="stats-bar" style="width: <?= $percent ?>%;"></div>
                        </div>
                        <span class="stats-count"><?= $ds['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>

    <div class="charts-grid">
        <!-- Year of Birth Stats -->
        <div class="chart-card" style="grid-column: 1 / -1;">
            <div class="chart-header">
                <span class="chart-title">Distribusi Peserta Berdasarkan Tahun Lahir</span>
            </div>
            <div class="stats-list">
                <?php 
                if (count($yearStats) > 0):
                    $maxYearCount = max(array_column($yearStats, 'count') ?: [1]);
                    foreach ($yearStats as $ys): 
                        $percent = ($ys['count'] / $maxYearCount) * 100;
                    ?>
                        <div class="stats-item">
                            <div class="stats-label" style="width: 80px; font-weight: 500;">Tahun <?= $ys['year'] ?></div>
                            <div class="stats-bar-wrapper">
                                <div class="stats-bar" style="width: <?= $percent ?>%; background: linear-gradient(90deg, var(--info) 0%, var(--accent) 100%);"></div>
                            </div>
                            <span class="stats-count"><?= $ys['count'] ?> orang</span>
                        </div>
                    <?php 
                    endforeach;
                else: ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 20px;">Belum ada data peserta.</p>
                <?php endif; ?>
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
