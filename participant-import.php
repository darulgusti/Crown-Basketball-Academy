<?php
/**
 * Import Participants from Excel (.xlsx) File
 * Crown Basketball Academy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

// Check authorization (Admin & Coach can import)
requireCoachOrAdmin();

$db = getDBConnection();

// 1. Template Generation Action (Supports xlsx only)
if (isset($_GET['template']) && $_GET['template'] === 'xlsx') {
    $headers = [
        'Nama Lengkap',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Jenis Kelamin',
        'Tinggi Badan (cm)',
        'Berat Badan (kg)',
        'Asal Sekolah',
        'No. HP',
        'Alamat Lengkap',
        'Pengalaman Basket',
        'Nama Klub Sebelumnya',
        'Hari Latihan',
        'Nama Orang Tua / Wali',
        'Pekerjaan Orang Tua',
        'No. HP Orang Tua / Wali',
        'Status Anggota'
    ];
    
    $row1 = [
        'Budi Santoso',
        'Jakarta',
        '2010-05-15',
        'Laki-laki',
        175,
        65,
        'SMPN 1 Jakarta',
        '081234567890',
        'Jl. Mawar No. 12, Jakarta Selatan',
        'Ya',
        'Indo Basket Club',
        'Senin, Rabu',
        'Joko Santoso',
        'Swasta',
        '081234567891',
        'Aktif'
    ];
    
    $row2 = [
        'Siti Aminah',
        'Bandung',
        '2012-08-20',
        'Perempuan',
        160,
        50,
        'SMPN 2 Bandung',
        '082345678901',
        'Jl. Melati No. 5, Bandung',
        'Tidak',
        '', // Empty club
        'Selasa, Kamis, Sabtu',
        'Ahmad',
        'Guru',
        '082345678902',
        'Aktif'
    ];

    if (ob_get_level()) {
        ob_end_clean();
    }

    require_once __DIR__ . '/includes/SimpleXLSXGen.php';
    $excelData = [$headers, $row1, $row2];
    \Shuchkin\SimpleXLSXGen::fromArray($excelData)->downloadAs('Template_Import_Peserta.xlsx');
    exit;
}

function parseImportDate($dateVal) {
    if (empty($dateVal)) {
        return false;
    }
    
    // If it is numeric (excel date serial number)
    if (is_numeric($dateVal)) {
        $unixTimestamp = ($dateVal - 25569) * 86400;
        return date('Y-m-d', $unixTimestamp);
    }
    
    // Normalize string: trim, replace '/' or '.' with '-'
    $cleaned = str_replace(['/', '.'], '-', trim($dateVal));
    
    // Strip time component if present (e.g., "2010-05-15 00:00:00" -> "2010-05-15")
    if (strpos($cleaned, ' ') !== false) {
        $parts = explode(' ', $cleaned);
        $cleaned = $parts[0];
    }
    
    // Try YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleaned)) {
        $parts = explode('-', $cleaned);
        if (checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            return $cleaned;
        }
    }
    
    // Try DD-MM-YYYY
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $cleaned)) {
        $parts = explode('-', $cleaned);
        if (checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2])) {
            return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
        }
    }
    
    // Fallback using strtotime
    $ts = strtotime($dateVal);
    if ($ts !== false && $ts > 0) {
        return date('Y-m-d', $ts);
    }
    
    return false;
}

$errors = [];
$successCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    checkCSRF();
    
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Harap unggah file Excel (.xlsx) yang valid.";
    } else {
        $fileTmpPath = $_FILES['excel_file']['tmp_name'];
        $fileName = $_FILES['excel_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileExtension !== 'xlsx') {
            $errors[] = "Format file tidak didukung. Harap unggah file dengan ekstensi .xlsx.";
        } else {
            // Fetch day mapping from DB
            $stmtDays = $db->query("SELECT * FROM training_days");
            $dbDays = $stmtDays->fetchAll();
            $dayNameToId = [];
            foreach ($dbDays as $d) {
                $dayNameToId[strtolower($d['day_name'])] = $d['id'];
            }
            
            $headers = [];
            $allRows = [];
            $parserSuccess = false;

            require_once __DIR__ . '/includes/SimpleXLSX.php';
            if ($xlsx = \Shuchkin\SimpleXLSX::parse($fileTmpPath)) {
                $sheetRows = $xlsx->rows();
                if (!empty($sheetRows)) {
                    $headers = array_shift($sheetRows); // First row is header
                    $allRows = $sheetRows;
                    $parserSuccess = true;
                } else {
                    $errors[] = "File Excel kosong.";
                }
            } else {
                $errors[] = "Gagal membaca file Excel: " . \Shuchkin\SimpleXLSX::parseError();
            }

            if ($parserSuccess) {
                if (count($headers) < 16) {
                    $errors[] = "Format kolom tidak cocok. Harus terdapat 16 kolom sesuai template.";
                } else {
                    $rowsToInsert = [];
                    $rowNum = 1; // Row 1 is header
                    
                    // Parse each data row
                    foreach ($allRows as $row) {
                        $rowNum++;
                        
                        // Skip empty rows
                        if (count($row) === 1 && empty($row[0])) {
                            continue;
                        }
                        
                        // Pad row if columns are missing
                        if (count($row) < 16) {
                            $row = array_pad($row, 16, '');
                        }
                        
                        // Trim all fields
                        $row = array_map('trim', $row);
                        
                        $name = $row[0];
                        $birth_place = $row[1];
                        $birth_date = $row[2];
                        $gender = $row[3];
                        $heightStr = $row[4];
                        $weightStr = $row[5];
                        $school_name = $row[6];
                        $phone = $row[7];
                        $address = $row[8];
                        $basketball_experience = $row[9];
                        $previous_club = $row[10];
                        $training_days_str = $row[11];
                        $parent_name = $row[12];
                        $parent_job = $row[13];
                        $parent_phone = $row[14];
                        $statusStr = $row[15];
                        
                        // 1. Name validation
                        if (empty($name)) {
                            $name = '-';
                        } elseif (strlen($name) > 100) {
                            $name = substr($name, 0, 100);
                        }
                        
                        // 2. Birth place validation
                        if (empty($birth_place)) {
                            $birth_place = '-';
                        } elseif (strlen($birth_place) > 100) {
                            $birth_place = substr($birth_place, 0, 100);
                        }
                        
                        // 3. Birth date validation
                        $parsedBirthDate = false;
                        if (empty($birth_date)) {
                            $parsedBirthDate = '2000-01-01';
                        } else {
                            $parsedBirthDate = parseImportDate($birth_date);
                            if (!$parsedBirthDate) {
                                $parsedBirthDate = '2000-01-01';
                            }
                        }
                        
                        // 4. Gender validation
                        $normalizedGender = strtoupper($gender);
                        if (in_array($normalizedGender, ['L', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA'])) {
                            $normalizedGender = 'L';
                        } elseif (in_array($normalizedGender, ['P', 'PEREMPUAN', 'WANITA'])) {
                            $normalizedGender = 'P';
                        } else {
                            $normalizedGender = 'L'; // Fallback default
                        }
                        
                        // 5. Height and Weight validation
                        $height = (int)$heightStr;
                        $weight = (int)$weightStr;
                        if (empty($heightStr) || $height <= 0) {
                            $height = 0;
                        }
                        if (empty($weightStr) || $weight <= 0) {
                            $weight = 0;
                        }
                        
                        // 6. School validation
                        if (empty($school_name)) {
                            $school_name = '-';
                        } elseif (strlen($school_name) > 100) {
                            $school_name = substr($school_name, 0, 100);
                        }
                        
                        // 7. Phone validation
                        if (empty($phone)) {
                            $phone = '-';
                        } elseif (strlen($phone) > 20) {
                            $phone = substr($phone, 0, 20);
                        }
                        
                        // 8. Address validation
                        if (empty($address)) {
                            $address = '-';
                        }
                        
                        // 9. Basketball experience validation
                        $expVal = strtolower($basketball_experience);
                        if (in_array($expVal, ['ya', 'yes', 'y'])) {
                            $basketball_experience = 'Ya';
                            if (empty($previous_club)) {
                                $previous_club = '-';
                            }
                        } else {
                            $basketball_experience = 'Tidak';
                            $previous_club = null;
                        }
                        
                        if (!empty($previous_club) && strlen($previous_club) > 100) {
                            $previous_club = substr($previous_club, 0, 100);
                        }
                        
                        // 10. Parse training days
                        $rowDayIds = [];
                        if (!empty($training_days_str)) {
                            // Split by comma, ampersand, slash or semicolon
                            $dayNames = preg_split('/[\s,;&\/]+/', $training_days_str);
                            $dayNames = array_filter(array_map('trim', $dayNames));
                            
                            foreach ($dayNames as $dName) {
                                $dNameLower = strtolower($dName);
                                if ($dNameLower === "jum'at") $dNameLower = 'jumat';
                                
                                if (isset($dayNameToId[$dNameLower])) {
                                    $rowDayIds[] = $dayNameToId[$dNameLower];
                                }
                            }
                            $rowDayIds = array_unique($rowDayIds);
                        }
                        
                        // 11. Parent validations
                        if (empty($parent_name)) {
                            $parent_name = '-';
                        } elseif (strlen($parent_name) > 100) {
                            $parent_name = substr($parent_name, 0, 100);
                        }
                        
                        // 12. Parent job validation
                        if (empty($parent_job)) {
                            $parent_job = '-';
                        } elseif (strlen($parent_job) > 100) {
                            $parent_job = substr($parent_job, 0, 100);
                        }
                        
                        // 13. Parent phone validation
                        if (empty($parent_phone)) {
                            $parent_phone = '-';
                        } elseif (strlen($parent_phone) > 20) {
                            $parent_phone = substr($parent_phone, 0, 20);
                        }
                        
                        // 15. Status validation
                        $statusVal = 'active';
                        if (!empty($statusStr)) {
                            $statusLower = strtolower($statusStr);
                            if (in_array($statusLower, ['aktif', 'active'])) {
                                $statusVal = 'active';
                            } elseif (in_array($statusLower, ['nonaktif', 'inactive', 'non-aktif'])) {
                                $statusVal = 'inactive';
                            }
                        }
                        
                        // Save structured row data for transaction execution
                        $rowsToInsert[] = [
                            'name' => $name,
                            'birth_place' => $birth_place,
                            'birth_date' => $parsedBirthDate,
                            'gender' => $normalizedGender,
                            'height' => $height,
                            'weight' => $weight,
                            'school_name' => $school_name,
                            'phone' => $phone,
                            'address' => $address,
                            'basketball_experience' => $basketball_experience,
                            'previous_club' => $previous_club,
                            'parent_name' => $parent_name,
                            'parent_job' => $parent_job,
                            'parent_phone' => $parent_phone,
                            'status' => $statusVal,
                            'training_days' => $rowDayIds
                        ];
                    }
                    
                    // Execute transaction insert if no errors found
                    if (empty($errors)) {
                        if (empty($rowsToInsert)) {
                            $errors[] = "Tidak ada baris data peserta yang ditemukan untuk diimpor.";
                        } else {
                            try {
                                $db->beginTransaction();
                                
                                $sqlInsert = "INSERT INTO participants (
                                    photo, name, birth_place, birth_date, gender, 
                                    height, weight, school_name, phone, address, basketball_experience, 
                                    previous_club, parent_name, parent_job, parent_phone, status
                                ) VALUES (
                                    NULL, ?, ?, ?, ?, 
                                    ?, ?, ?, ?, ?, ?, 
                                    ?, ?, ?, ?, ?
                                )";
                                
                                $stmtInsert = $db->prepare($sqlInsert);
                                $stmtDayInsert = $db->prepare("INSERT INTO participant_training_days (participant_id, training_day_id) VALUES (?, ?)");
                                
                                foreach ($rowsToInsert as $data) {
                                    $stmtInsert->execute([
                                        $data['name'],
                                        $data['birth_place'],
                                        $data['birth_date'],
                                        $data['gender'],
                                        $data['height'],
                                        $data['weight'],
                                        $data['school_name'],
                                        $data['phone'],
                                        $data['address'],
                                        $data['basketball_experience'],
                                        $data['previous_club'],
                                        $data['parent_name'],
                                        $data['parent_job'],
                                        $data['parent_phone'],
                                        $data['status']
                                    ]);
                                    
                                    $participantId = $db->lastInsertId();
                                    
                                    // Insert day mapping
                                    foreach ($data['training_days'] as $dayId) {
                                        $stmtDayInsert->execute([$participantId, $dayId]);
                                    }
                                    
                                    $successCount++;
                                }
                                
                                $db->commit();
                                setFlashMessage('success', "Berhasil mengimpor {$successCount} data peserta baru.");
                                header("Location: participants.php");
                                exit;
                                
                            } catch (Exception $ex) {
                                $db->rollBack();
                                $errors[] = "Kesalahan basis data terjadi saat menyimpan: " . $ex->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }
}

$pageTitle = "Impor Data Peserta";
require_once __DIR__ . '/includes/header.php';
?>

<div class="data-card">
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="participants" class="btn btn-secondary btn-sm">&larr; Kembali</a>
        <a href="participant-import.php?template=xlsx" class="btn btn-info btn-sm">
            <!-- Excel icon -->
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
            </svg>
            Unduh Template Excel (.xlsx)
        </a>
    </div>

    <h2 style="margin-bottom: 8px; color: var(--accent);">Impor Data Peserta dari Excel</h2>
    <p style="color: var(--text-secondary); margin-bottom: 24px; font-size: 0.9rem;">
        Gunakan menu ini untuk menambahkan peserta dalam jumlah banyak sekaligus menggunakan file Excel (.xlsx).
    </p>

    <!-- Detailed Instruction Box -->
    <div style="background-color: var(--accent-light); border-left: 4px solid var(--accent); padding: 16px; border-radius: 8px; margin-bottom: 24px; font-size: 0.9rem; line-height: 1.5;">
        <h4 style="color: var(--accent); margin-bottom: 8px;">Petunjuk Pengisian File Excel:</h4>
        <ul style="padding-left: 20px; color: var(--text-secondary);">
            <li><strong>Kolom Excel</strong> harus persis sesuai template (16 kolom). Jangan mengubah baris header pertama.</li>
            <li><strong>Tanggal Lahir</strong> harus berformat <code>YYYY-MM-DD</code> (contoh: <code>2010-05-15</code>) atau <code>DD-MM-YYYY</code>. Jika kosong/salah, diisi dengan <code>2000-01-01</code> secara default.</li>
            <li><strong>Jenis Kelamin</strong> diisi <code>L</code> (Laki-laki) atau <code>P</code> (Perempuan). Jika tidak sesuai, diisi <code>L</code> secara default.</li>
            <li><strong>Tinggi & Berat Badan</strong> diisi angka positif. Jika kosong atau tidak valid, diset ke <code>0</code>.</li>
            <li><strong>Pengalaman Basket</strong> diisi <code>Ya</code> atau <code>Tidak</code>. Jika memilih <code>Ya</code> tetapi nama klub sebelumnya kosong, diset ke <code>-</code>.</li>
            <li><strong>Hari Latihan</strong> diisi nama hari yang dipisahkan dengan koma (contoh: <code>Senin, Rabu</code>). Jika dikosongkan/salah, data tetap masuk dan dapat dipilih nanti saat mengedit data peserta.</li>
            <li><strong>Toleransi Data:</strong> Kolom wajib yang kosong atau salah format tetap akan diimpor menggunakan nilai default sementara (seperti <code>-</code> atau <code>0</code>) agar Anda dapat memperbaruinya di halaman edit peserta nanti.</li>
        </ul>
    </div>

    <!-- Error Summary Alert -->
    <?php if (!empty($errors)): ?>
        <div class="error-alert" style="margin-bottom: 24px; background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; padding: 16px;">
            <div style="display: flex; align-items: flex-start; gap: 8px;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" style="flex-shrink: 0;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <strong style="color: #991b1b; display: block; margin-bottom: 8px;">Gagal mengimpor data! Silakan perbaiki kesalahan berikut:</strong>
                    <div style="max-height: 250px; overflow-y: auto; padding-right: 8px;">
                        <ul style="padding-left: 20px; font-size: 0.85rem; color: #b91c1c; margin: 0; line-height: 1.6;">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Import Form with Drag & Drop styling -->
    <form action="participant-import" method="POST" enctype="multipart/form-data" id="import-form">
        <?= csrfField(); ?>

        <div class="drag-drop-zone" id="drop-zone" style="border: 2px dashed var(--bg-tertiary); border-radius: var(--border-radius); padding: 40px 20px; text-align: center; background-color: var(--bg-primary); cursor: pointer; transition: all 0.3s ease; margin-bottom: 24px;">
            <input type="file" name="excel_file" id="excel-file-input" accept=".xlsx" style="display: none;" required>
            
            <div id="drop-zone-prompt">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" style="margin: 0 auto 12px; display: block; opacity: 0.7;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <p style="font-weight: 500; font-size: 1.05rem; margin-bottom: 6px; color: var(--text-primary);">
                    Tarik & Lepas file Excel (.xlsx) di sini
                </p>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">
                    atau <span style="color: var(--accent); font-weight: 600; text-decoration: underline;">pilih file dari komputer</span>
                </p>
            </div>
            
            <div id="drop-zone-selected" style="display: none;">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--success)" stroke-width="1.5" style="margin: 0 auto 12px; display: block;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <circle cx="12" cy="14" r="3"/>
                    <path d="M12 11v6"/>
                    <path d="M9 14h6"/>
                </svg>
                <p style="font-weight: 600; font-size: 1.05rem; margin-bottom: 4px; color: var(--success);" id="selected-file-name">
                    Nama_File.xlsx
                </p>
                <p style="font-size: 0.85rem; color: var(--text-secondary);" id="selected-file-size">
                    0 KB
                </p>
                <button type="button" id="remove-file-btn" class="btn btn-secondary btn-sm" style="margin-top: 12px; padding: 4px 12px; font-size: 0.8rem;">
                    Ganti File
                </button>
            </div>
        </div>

        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="participants" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" id="submit-btn" disabled>Mulai Impor Data</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('excel-file-input');
    const promptDiv = document.getElementById('drop-zone-prompt');
    const selectedDiv = document.getElementById('drop-zone-selected');
    const fileNameSpan = document.getElementById('selected-file-name');
    const fileSizeSpan = document.getElementById('selected-file-size');
    const removeBtn = document.getElementById('remove-file-btn');
    const submitBtn = document.getElementById('submit-btn');

    // Trigger click on file input
    dropZone.addEventListener('click', (e) => {
        // Prevent click trigger when clicking the remove button
        if (e.target !== removeBtn && !removeBtn.contains(e.target)) {
            fileInput.click();
        }
    });

    // Handle file selection
    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
    });

    // Drag over styling
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--accent)';
        dropZone.style.backgroundColor = 'var(--accent-light)';
    });

    // Reset styling when drag leaves
    ['dragleave', 'dragend'].forEach(type => {
        dropZone.addEventListener(type, () => {
            dropZone.style.borderColor = 'var(--bg-tertiary)';
            dropZone.style.backgroundColor = 'var(--bg-primary)';
        });
    });

    // Handle dropped files
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--bg-tertiary)';
        dropZone.style.backgroundColor = 'var(--bg-primary)';
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFiles(e.dataTransfer.files);
        }
    });

    // Remove selected file
    removeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.value = '';
        promptDiv.style.display = 'block';
        selectedDiv.style.display = 'none';
        submitBtn.disabled = true;
    });

    function handleFiles(files) {
        if (files.length === 0) return;
        const file = files[0];
        
        // Check extension
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'xlsx') {
            showToast('danger', 'Hanya file Excel (.xlsx) yang diperbolehkan.');
            fileInput.value = '';
            return;
        }

        // Update UI
        fileNameSpan.textContent = file.name;
        
        // Format size
        let sizeStr = '';
        if (file.size < 1024) {
            sizeStr = file.size + ' B';
        } else if (file.size < 1024 * 1024) {
            sizeStr = (file.size / 1024).toFixed(1) + ' KB';
        } else {
            sizeStr = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
        }
        fileSizeSpan.textContent = sizeStr;

        promptDiv.style.display = 'none';
        selectedDiv.style.display = 'block';
        submitBtn.disabled = false;
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
