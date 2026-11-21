<?php
/**
 * Database Migration Tool
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';

echo "<h2>Database Migration Tool</h2>";

try {
    $db = getDBConnection();
    
    // Check and drop registration_number
    $q = $db->query("SHOW COLUMNS FROM participants LIKE 'registration_number'");
    if ($q->rowCount() > 0) {
        $db->exec("ALTER TABLE participants DROP COLUMN registration_number");
        echo "<p style='color: green;'>✓ Berhasil menghapus kolom <strong>registration_number</strong> dari tabel <em>participants</em>.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Kolom <strong>registration_number</strong> sudah tidak ada.</p>";
    }
    
    // Check and drop registration_date
    $q = $db->query("SHOW COLUMNS FROM participants LIKE 'registration_date'");
    if ($q->rowCount() > 0) {
        $db->exec("ALTER TABLE participants DROP COLUMN registration_date");
        echo "<p style='color: green;'>✓ Berhasil menghapus kolom <strong>registration_date</strong> dari tabel <em>participants</em>.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Kolom <strong>registration_date</strong> sudah tidak ada.</p>";
    }
    
    // Add avatar column to users table
    $qUser = $db->query("SHOW COLUMNS FROM users LIKE 'avatar'");
    if ($qUser->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN avatar LONGTEXT NULL DEFAULT NULL AFTER email");
        echo "<p style='color: green;'>✓ Berhasil menambahkan kolom <strong>avatar</strong> (LONGTEXT) ke tabel <em>users</em>.</p>";
    } else {
        // If it exists but might be VARCHAR, modify it to LONGTEXT
        $db->exec("ALTER TABLE users MODIFY COLUMN avatar LONGTEXT NULL DEFAULT NULL");
        echo "<p style='color: orange;'>⚠ Kolom <strong>avatar</strong> sudah ada, tipe data dipastikan LONGTEXT.</p>";
    }
    
    // Migrate photo column in participants to LONGTEXT (for base64 storage)
    $db->exec("ALTER TABLE participants MODIFY COLUMN photo LONGTEXT DEFAULT NULL");
    echo "<p style='color: green;'>✓ Kolom <strong>photo</strong> di tabel <em>participants</em> berhasil diubah ke LONGTEXT.</p>";

    // Migrate photo column in coaches to LONGTEXT (for base64 storage)
    $db->exec("ALTER TABLE coaches MODIFY COLUMN photo LONGTEXT DEFAULT NULL");
    echo "<p style='color: green;'>✓ Kolom <strong>photo</strong> di tabel <em>coaches</em> berhasil diubah ke LONGTEXT.</p>";

    // Create participant_assessments table
    $db->exec("CREATE TABLE IF NOT EXISTS participant_assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_id INT NOT NULL,
        user_id INT NOT NULL,
        coach_id INT DEFAULT NULL,
        coach_name VARCHAR(100) NOT NULL,
        assessment_date DATE NOT NULL,
        chest_pass_prep ENUM('E', 'IE') NOT NULL DEFAULT 'E',
        chest_pass_acc ENUM('E', 'IE') NOT NULL DEFAULT 'E',
        dribble_control ENUM('E', 'IE') NOT NULL DEFAULT 'E',
        dribble_vision ENUM('E', 'IE') NOT NULL DEFAULT 'E',
        shooting_elbow ENUM('E', 'IE') NOT NULL DEFAULT 'E',
        shooting_wrist ENUM('E', 'IE') NOT NULL DEFAULT 'E',
        score_e INT NOT NULL DEFAULT 6,
        score_ie INT NOT NULL DEFAULT 0,
        final_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        predicate VARCHAR(50) NOT NULL DEFAULT 'Sangat Baik',
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (coach_id) REFERENCES coaches(id) ON DELETE SET NULL,
        INDEX idx_assessment_participant (participant_id),
        INDEX idx_assessment_date (assessment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p style='color: green;'>✓ Tabel <strong>participant_assessments</strong> berhasil dibuat / dipastikan ada.</p>";

    // Add criteria_data column to participant_assessments if not exists
    $qCritData = $db->query("SHOW COLUMNS FROM participant_assessments LIKE 'criteria_data'");
    if ($qCritData->rowCount() == 0) {
        $db->exec("ALTER TABLE participant_assessments ADD COLUMN criteria_data LONGTEXT NULL DEFAULT NULL AFTER assessment_date");
        echo "<p style='color: green;'>✓ Berhasil menambahkan kolom <strong>criteria_data</strong> ke tabel <em>participant_assessments</em>.</p>";
    }

    // Create assessment_groups table
    $db->exec("CREATE TABLE IF NOT EXISTS assessment_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✓ Tabel <strong>assessment_groups</strong> berhasil dibuat / dipastikan ada.</p>";

    // Create assessment_criteria table
    $db->exec("CREATE TABLE IF NOT EXISTS assessment_criteria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NULL,
        slot_key VARCHAR(50) NULL,
        category_name VARCHAR(100) NOT NULL,
        criterion_label TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✓ Tabel <strong>assessment_criteria</strong> berhasil dibuat / dipastikan ada.</p>";

    // Ensure group_id column exists
    $qGid = $db->query("SHOW COLUMNS FROM assessment_criteria LIKE 'group_id'");
    if ($qGid->rowCount() == 0) {
        $db->exec("ALTER TABLE assessment_criteria ADD COLUMN group_id INT NULL AFTER id");
        echo "<p style='color: green;'>✓ Berhasil menambahkan kolom <strong>group_id</strong> ke tabel <em>assessment_criteria</em>.</p>";
    }

    // Ensure slot_key is nullable
    try {
        $db->exec("ALTER TABLE assessment_criteria MODIFY COLUMN slot_key VARCHAR(50) NULL");
    } catch (Exception $e) {}

    // Seed default groups & criteria jika kosong
    $grpCount = (int)$db->query("SELECT COUNT(*) FROM assessment_groups")->fetchColumn();
    if ($grpCount === 0) {
        $defaults = [
            'Chest Pass (Umpan Dada)' => [
                ['chest_pass_prep', 'Awalan bola di depan dada, kaki dibuka lebar, badan condong'],
                ['chest_pass_acc',  'Akurasi lemparan ke arah kawan'],
            ],
            'Dribbling' => [
                ['dribble_control', 'Bola dipantulkan setinggi pinggang dengan ujung jari'],
                ['dribble_vision',  'Pandangan ke depan, tidak terpaku pada bola'],
            ],
            'Shooting' => [
                ['shooting_elbow',  'Siku lurus di bawah bola saat menembak'],
                ['shooting_wrist',  'Lepasan bola diikuti gerakan pergelangan tangan'],
            ],
        ];
        $stmtG = $db->prepare("INSERT INTO assessment_groups (name, sort_order) VALUES (?, ?)");
        $stmtC = $db->prepare("INSERT INTO assessment_criteria (group_id, slot_key, category_name, criterion_label, sort_order) VALUES (?, ?, ?, ?, ?)");
        $gOrd = 1;
        $cOrd = 1;
        foreach ($defaults as $gName => $crits) {
            $stmtG->execute([$gName, $gOrd++]);
            $newGid = $db->lastInsertId();
            foreach ($crits as $c) {
                $stmtC->execute([$newGid, $c[0], $gName, $c[1], $cOrd++]);
            }
        }
        echo "<p style='color: green;'>✓ Data default kelompok & kriteria GPAI berhasil di-seed.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Tabel <strong>assessment_groups</strong> sudah memiliki data ($grpCount baris), seed dilewati.</p>";
    }

    echo "<p style='color: blue; font-weight: bold;'>Migrasi database selesai! Silakan hapus file ini dari server setelah Anda selesai memigrasi.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Gagal menjalankan migrasi: " . htmlspecialchars($e->getMessage()) . "</p>";
}
