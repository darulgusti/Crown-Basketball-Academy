<?php
/**
 * Public Portal / Landing Page
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDBConnection();

// Fetch filter parameters
$search = trim($_GET['search'] ?? '');
$gender = $_GET['gender'] ?? '';
$day = $_GET['day'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest'; // newest, oldest (birth date)

// Build query
$queryStr = "SELECT p.*, GROUP_CONCAT(td.day_name ORDER BY td.id SEPARATOR ', ') as training_days
             FROM participants p
             LEFT JOIN participant_training_days ptd ON p.id = ptd.participant_id
             LEFT JOIN training_days td ON ptd.training_day_id = td.id
             WHERE 1=1";
$params = [];

if (!empty($search)) {
    $queryStr .= " AND (p.name LIKE ? OR p.registration_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($gender)) {
    $queryStr .= " AND p.gender = ?";
    $params[] = $gender;
}

if (!empty($day)) {
    $queryStr .= " AND p.id IN (SELECT participant_id FROM participant_training_days WHERE training_day_id = ?)";
    $params[] = $day;
}

if (!empty($status)) {
    $queryStr .= " AND p.status = ?";
    $params[] = $status;
}

$queryStr .= " GROUP BY p.id";

// Sort condition
if ($sort === 'youngest') {
    $queryStr .= " ORDER BY p.birth_date DESC";
} elseif ($sort === 'oldest') {
    $queryStr .= " ORDER BY p.birth_date ASC";
} else {
    $queryStr .= " ORDER BY p.created_at DESC";
}

// Pagination logic
$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch total records
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM ($queryStr) AS sub");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Add Limit & Offset to query
$queryStr .= " LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($queryStr);
$stmt->execute($params);
$participants = $stmt->fetchAll();

// Fetch training days for filter dropdown
$trainingDays = $db->query("SELECT * FROM training_days ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crown Basketball Academy</title>
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
            font-size: 1.25rem;
            color: var(--text-primary);
        }
        .navbar-brand svg {
            width: 32px;
            height: 32px;
            fill: var(--accent);
        }
        .public-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        /* Participant Card Grid */
        .participant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .participant-card {
            background-color: var(--bg-secondary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--bg-tertiary);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        .participant-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg), 0 0 20px rgba(249, 115, 22, 0.1);
            border-color: rgba(249, 115, 22, 0.2);
        }
        .card-banner {
            height: 80px;
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-primary) 100%);
            position: relative;
        }
        .card-avatar-wrapper {
            position: absolute;
            bottom: -40px;
            left: 20px;
            width: 80px;
            height: 80px;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 3px solid var(--bg-secondary);
            background-color: var(--bg-primary);
        }
        .card-avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--accent);
            background-color: var(--bg-primary);
        }
        .card-status {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .card-body {
            padding: 50px 20px 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .card-reg-num {
            font-size: 0.8rem;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .card-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-primary);
        }
        .card-info-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .card-info-item {
            display: flex;
            justify-content: space-between;
        }
        .card-info-item span:last-child {
            font-weight: 600;
            color: var(--text-primary);
        }
        .card-footer {
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: flex-end;
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <nav class="public-navbar">
        <div class="navbar-brand">
            <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2h2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H9v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 1.54-.37 2.99-1.1 4.29z"/>
            </svg>
            <span>CROWN BASKETBALL ACADEMY</span>
        </div>
        
        <div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="btn btn-primary btn-sm">Dashboard &rarr;</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm">Login / Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="public-container">
        
        <!-- Hero Section -->
        <header class="landing-hero">
            <h1>CROWN BASKETBALL</h1>
            <p>Membentuk talenta basket masa depan dengan disiplin, teknik, dan mental juara.</p>
        </header>

        <!-- Filters Box -->
        <div class="data-card">
            <form action="index.php" method="GET" class="search-filter-form">
                <div class="card-actions" style="margin-bottom: 0;">
                    <div class="search-filter-box">
                        <input type="text" name="search" class="form-control" placeholder="Cari Nama ..." value="<?= e($search) ?>" style="max-width: 250px;">
                        
                        <select name="gender" class="form-control" style="max-width: 150px;">
                            <option value="">Semua Gender</option>
                            <option value="L" <?= $gender === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= $gender === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>

                        <select name="day" class="form-control" style="max-width: 150px;">
                            <option value="">Hari Latihan</option>
                            <?php foreach ($trainingDays as $td): ?>
                                <option value="<?= $td['id'] ?>" <?= (string)$day === (string)$td['id'] ? 'selected' : '' ?>><?= e($td['day_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="form-control" style="max-width: 150px;">
                            <option value="">Semua Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                        
                        <select name="sort" class="form-control" style="max-width: 180px;">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Pendaftaran Terbaru</option>
                            <option value="youngest" <?= $sort === 'youngest' ? 'selected' : '' ?>>Termuda</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Tertua</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Participants Cards Grid -->
        <?php if (count($participants) > 0): ?>
            <div class="participant-grid">
                <?php foreach ($participants as $p): ?>
                    <div class="participant-card">
                        <div class="card-banner">
                            <span class="card-status">
                                <?= renderStatusBadge($p['status'] === 'active' ? 'Aktif' : 'Nonaktif'); ?>
                            </span>
                            <div class="card-avatar-wrapper">
                                <?php if ($p['photo']): ?>
                                    <img src="uploads/participants/<?= e($p['photo']) ?>" alt="<?= e($p['name']) ?>">
                                <?php else: ?>
                                    <div class="card-avatar-placeholder">
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="card-name"><?= e($p['name']) ?></div>
                            
                            <ul class="card-info-list">
                                <li class="card-info-item">
                                    <span>Gender:</span>
                                    <span><?= $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></span>
                                </li>
                                <li class="card-info-item">
                                    <span>Sekolah:</span>
                                    <span><?= e($p['school_name']) ?></span>
                                </li>
                                <li class="card-info-item">
                                    <span>Hari Latihan:</span>
                                    <span><?= $p['training_days'] ? e($p['training_days']) : 'Belum memilih' ?></span>
                                </li>
                            </ul>
                            
                            <div class="card-footer">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="showPublicDetail(<?= e(json_encode([
                                    'name' => $p['name'],
                                    'reg_num' => $p['registration_number'],
                                    'gender' => $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan',
                                    'birth_place_date' => $p['birth_place'] . ', ' . formatIndoDate($p['birth_date']),
                                    'height' => $p['height'] . ' cm',
                                    'weight' => $p['weight'] . ' kg',
                                    'school' => $p['school_name'],
                                    'days' => $p['training_days'] ?: 'Belum memilih',
                                    'status' => $p['status'] === 'active' ? 'Aktif' : 'Nonaktif',
                                    'reg_date' => formatIndoDate($p['registration_date']),
                                    'photo' => $p['photo'] ? 'uploads/participants/' . $p['photo'] : null,
                                    'initials' => strtoupper(substr($p['name'], 0, 1))
                                ])) ?>)">Detail Publik</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Menampilkan Halaman <?= $page ?> dari <?= $totalPages ?> (Total: <?= $totalRecords ?> Peserta)
                    </div>
                    <nav class="pagination-nav">
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">&larr;</a>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>">&rarr;</a>
                    </nav>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="data-card" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                Belum ada data peserta terdaftar yang sesuai filter.
            </div>
        <?php endif; ?>
    </div>

    <!-- Public Detail Modal -->
    <div id="public-detail-modal" class="modal-backdrop">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-title" id="m-title" style="border-bottom: 1px solid var(--bg-tertiary); padding-bottom: 10px;">Detail Publik Peserta</div>
            <div class="modal-body" style="margin-bottom: 16px;">
                
                <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
                    <div id="m-photo-box" style="width: 100px; height: 100px; border-radius: var(--border-radius); border: 2px solid var(--accent); overflow: hidden; background-color: var(--bg-primary); display: flex; align-items: center; justify-content: center;">
                        <!-- dynamic photo or initials -->
                    </div>
                    <div>
                        <h3 id="m-name" style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"></h3>
                    </div>
                </div>

                <div class="detail-list">
                    <div class="detail-row">
                        <span class="detail-label">Jenis Kelamin</span>
                        <span class="detail-val" id="m-gender"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tempat, Tanggal Lahir</span>
                        <span class="detail-val" id="m-birth"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tinggi / Berat Badan</span>
                        <span class="detail-val" id="m-height-weight"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Asal Sekolah</span>
                        <span class="detail-val" id="m-school"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Jadwal Hari Latihan</span>
                        <span class="detail-val" id="m-days" style="color: var(--accent);"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tanggal Pendaftaran</span>
                        <span class="detail-val" id="m-reg-date"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status Keanggotaan</span>
                        <span class="detail-val" id="m-status"></span>
                    </div>
                </div>

                <!-- Alert block indicating sensitive data hidden -->
                <div style="background-color: rgba(6, 182, 212, 0.1); border: 1px solid var(--info); color: var(--text-primary); border-radius: 8px; padding: 12px; font-size: 0.8rem; margin-top: 20px; line-height: 1.4;">
                    <strong>Informasi Tambahan:</strong> Alamat lengkap, nomor HP, nama & pekerjaan orang tua/wali, serta riwayat pengalaman basket disembunyikan demi menjaga privasi data pribadi peserta.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePublicDetail()">Tutup</button>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function showPublicDetail(data) {
            const modal = document.getElementById('public-detail-modal');
            const photoBox = document.getElementById('m-photo-box');
            
            document.getElementById('m-name').textContent = data.name;
            document.getElementById('m-gender').textContent = data.gender;
            document.getElementById('m-birth').textContent = data.birth_place_date;
            document.getElementById('m-height-weight').textContent = data.height + ' / ' + data.weight;
            document.getElementById('m-school').textContent = data.school;
            document.getElementById('m-days').textContent = data.days;
            document.getElementById('m-reg-date').textContent = data.reg_date;
            document.getElementById('m-status').textContent = data.status;
            
            if (data.photo) {
                photoBox.innerHTML = `<img src="${data.photo}" alt="${data.name}" style="width:100%; height:100%; object-fit:cover;">`;
            } else {
                photoBox.innerHTML = `<div style="font-size:2rem; font-weight:700; color:var(--accent);">${data.initials}</div>`;
            }
            
            modal.classList.add('show');
        }

        function closePublicDetail() {
            document.getElementById('public-detail-modal').classList.remove('show');
        }

        // Close modal when clicking backdrop
        document.getElementById('public-detail-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePublicDetail();
            }
        });
    </script>
</body>
</html>
