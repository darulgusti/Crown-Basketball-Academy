<?php
/**
 * One-time Database Migration Script
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
    
    echo "<p style='color: blue; font-weight: bold;'>Migrasi database selesai! Silakan hapus file <code>run-migration.php</code> atau refresh halaman untuk memastikan.</p>";
    
    // Attempt self-delete
    if (@unlink(__FILE__)) {
        echo "<p style='color: gray; font-style: italic;'>File <code>run-migration.php</code> otomatis dihapus demi keamanan.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Gagal menjalankan migrasi: " . htmlspecialchars($e->getMessage()) . "</p>";
}
