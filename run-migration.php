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

    echo "<p style='color: blue; font-weight: bold;'>Migrasi database selesai! Silakan hapus file ini dari server setelah Anda selesai memigrasi.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Gagal menjalankan migrasi: " . htmlspecialchars($e->getMessage()) . "</p>";
}
