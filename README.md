# Crown Basketball Academy Web Application

Aplikasi web manajemen "Crown Basketball Academy" dibangun menggunakan PHP Native (tanpa framework), MySQL/MariaDB, HTML5, CSS3, dan JavaScript Vanilla.

## Fitur Utama

- **Role & Hak Akses**: Admin, Pelatih (Coach), dan Umum (Public).
- **Modul Peserta**: CRUD data peserta lengkap, pencarian, filter (gender, tahun lahir, hari latihan, status), pengurutan, pagination, cetak biodata berlogo, dan ekspor data CSV.
- **Modul Pelatih**: CRUD data pelatih (hanya Admin) dan pembuatan akun login pelatih secara otomatis.
- **Modul Absensi Peserta**: Pencatatan kehadiran harian sesuai jadwal hari latihan, rekap absensi harian & bulanan, pencegahan absensi ganda, serta ekspor CSV.
- **Modul Absensi Pelatih**: Absensi mandiri pelatih (check-in / check-out), monitoring & koreksi oleh Admin, serta ekspor CSV.
- **Dashboard**: Statistik visual berupa metrics dan progress bars untuk Admin dan Pelatih.
- **Keamanan**: Prepared statements PDO, token CSRF untuk semua request modifikasi, validasi file upload tipe MIME/ekstensi/ukuran, enkripsi password `bcrypt` (`password_hash`), regenerasi session ID, dan escape output HTML (`htmlspecialchars`).

---

## Petunjuk Instalasi di Laragon

1. **Persiapan Folder Proyek**:
   - Pastikan folder proyek `Crown Basketball Academy 2` (atau nama folder yang Anda inginkan) diletakkan di dalam folder `laragon/www/` (misalnya: `C:\laragon\www\Crown Basketball Academy 2`).
   
2. **Membuat Database**:
   - Buka aplikasi Laragon, lalu jalankan semua service (**Start All**).
   - Klik tombol **Database** (atau buka phpMyAdmin / HeidiSQL).
   - Buat database baru dengan nama `crown_basketball` dan collate `utf8mb4_unicode_ci`.

3. **Import Skema dan Data Awal (Seeder)**:
   - Hubungkan database client Anda ke database `crown_basketball`.
   - Jalankan script SQL berikut secara berurutan:
     1. `database/schema.sql` (untuk membuat tabel dan relasi).
     2. `database/seed.sql` (untuk memasukkan data hari latihan dan akun admin awal).

4. **Menjalankan Aplikasi**:
   - Buka browser Anda dan akses alamat: `http://localhost/Crown Basketball Academy 2/` atau `http://crown-basketball-academy-2.test/` jika auto-virtual host Laragon aktif.
   - Halaman utama akan menampilkan portal publik katalog peserta.
   - Klik tombol **Login / Register** di bagian atas kanan.

5. **Akun Pengujian Default**:
   - **Username**: `deandra`
   - **Email**: `cendekia@gmail.com`
   - **Password**: `deandra`
   - **Role**: Admin

---

## Petunjuk Pemindahan ke Shared Hosting

Untuk memindahkan aplikasi dari Laragon ke shared hosting, ikuti langkah-langkah berikut:

1. **Unggah Berkas Aplikasi**:
   - Kompres semua file di dalam folder proyek Anda menjadi file `.zip` (kecuali folder konfigurasi lokal yang tidak diperlukan).
   - Masuk ke cPanel / Panel hosting Anda, buka **File Manager**, dan arahkan ke direktori root domain/subdomain Anda (biasanya `public_html`).
   - Unggah file `.zip` tersebut dan ekstrak di sana.

2. **Ekspor & Impor Database**:
   - Di Laragon (lokal), buka HeidiSQL / phpMyAdmin, pilih database `crown_basketball`, lalu lakukan ekspor ke file `.sql` (struktur dan data).
   - Di cPanel hosting, masuk ke menu **MySQL Database Wizard**:
     - Buat database baru.
     - Buat pengguna database baru dan buat password yang kuat.
     - Berikan hak akses penuh (**All Privileges**) kepada pengguna tersebut untuk database yang baru dibuat.
   - Masuk ke **phpMyAdmin** di cPanel hosting, pilih database baru tersebut, pilih menu **Import**, dan pilih file `.sql` yang telah diekspor dari lokal.

3. **Konfigurasi Koneksi Database**:
   - Di File Manager hosting, edit file [database.php](file:///c:/laragon/www/Crown%20Basketball%20Academy%202/config/database.php) yang berada di dalam folder `config/`.
   - Ubah konstanta koneksi database sesuai dengan kredensial hosting baru Anda:
     ```php
     define('DB_HOST', 'localhost'); // Biasanya localhost di shared hosting
     define('DB_USER', 'nama_user_database_hosting');
     define('DB_PASS', 'password_user_database_hosting');
     define('DB_NAME', 'nama_database_hosting');
     ```

4. **Konfigurasi Hak Akses Uploads**:
   - Pastikan direktori `uploads/participants/` dan `uploads/coaches/` di hosting memiliki izin tulis (**Permission: 0755** atau **0777** jika hosting membutuhkannya) agar fitur unggah foto dapat berjalan dengan lancar.
