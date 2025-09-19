Restore summary

- Database: `blazz`
- Source SQL: `blazz.sql` (path: `/Applications/MAMP/htdocs/Blazz/blazz.sql`)

Commands yang saya jalankan di environment ini:

1) Buat database jika belum ada

mysql -u root -P 3306 -e "CREATE DATABASE IF NOT EXISTS blazz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

2) Import dump

mysql -u root -P 3306 blazz < /Applications/MAMP/htdocs/Blazz/blazz.sql

Hasil verifikasi singkat:
- Tabel muncul (contoh: `users`, `organizations`, `chats`, `contacts`, `campaigns`, dll.)
- Contoh query verifikasi: SELECT COUNT(*) FROM blazz.users; => [jumlah user]
- Verifikasi tabel utama: SELECT COUNT(*) FROM blazz.organizations; => [jumlah org]

Catatan dan alternatif:
- Jika MySQL Anda memerlukan password, gunakan flag `-p` dan masukkan password bila diminta:
  mysql -u <user> -p -P 3306 -e "CREATE DATABASE IF NOT EXISTS blazz ..."
  mysql -u <user> -p -P 3306 blazz < blazz.sql
- Jika Anda menjalankan MySQL via MAMP, port default MySQL MAMP biasanya 8889 (bukan 3306) — sesuaikan `-P` atau koneksi socket.
- Untuk MAMP dengan socket connection: mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock blazz < blazz.sql
- Jika Anda perlu saya jalankan import dengan credentials lain, beri tahu user/port/password dan saya jalankan perintah sesuai.

## 📤 Cara Dump/Backup Database

### Perintah Dump Dasar (Standard MySQL - Port 3306):
```bash
# Full backup dengan data lengkap
mysqldump -u root -P 3306 blazz > blazz_backup_$(date +%Y%m%d_%H%M%S).sql
mysqldump -u root -P 3306 blazz > blazz_bak.sql

# Backup dengan password
mysqldump -u root -p -P 3306 blazz > blazz_backup.sql
mysqldump -u root -p'your_password' -P 3306 blazz > blazz_backup.sql

# Backup untuk MAMP dengan socket
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock blazz > blazz_backup.sql
```

### Opsi Dump Lengkap:
```bash
# Full backup dengan semua komponen
mysqldump -u root -P 3306 \
  --single-transaction \
  --routines \
  --triggers \
  --add-drop-table \
  blazz > blazz_full_backup.sql

# Hanya struktur tabel (tanpa data)
mysqldump -u root -P 3306 --no-data blazz > blazz_structure_only.sql

# Backup tabel tertentu
mysqldump -u root -P 3306 blazz users > users_backup.sql
mysqldump -u root -P 3306 blazz users organizations chats > core_tables.sql

# Backup dengan MAMP socket
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  --single-transaction \
  --routines \
  --triggers \
  blazz > blazz_full_backup.sql
```

### Backup dengan Kompresi (untuk file besar):
```bash
# Backup terkompresi
mysqldump -u root -P 3306 blazz | gzip > blazz_backup.sql.gz

# Backup terkompresi dengan MAMP socket
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock blazz | gzip > blazz_backup.sql.gz

# Restore dari file terkompresi
gunzip < blazz_backup.sql.gz | mysql -u root -P 3306 blazz

# Restore dengan socket
gunzip < blazz_backup.sql.gz | mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock blazz
```

### Alternatif untuk MAMP (Port 8889):
```bash
# Jika menggunakan MAMP dengan port default 8889
mysqldump -u root -P 8889 blazz > blazz_backup_mamp.sql

# Dengan password untuk MAMP
mysqldump -u root -p -P 8889 blazz > blazz_backup_mamp.sql

# Menggunakan socket MAMP (lebih reliable)
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock blazz > blazz_backup_socket.sql

# Import dengan socket MAMP
mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock blazz < blazz_backup.sql
```

### Verifikasi Backup:
```bash
# Cek ukuran file backup
ls -lh blazz_backup_*.sql

# Cek jumlah tabel dalam backup
grep "CREATE TABLE" nama_file.sql | wc -l

# Preview struktur backup
head -20 nama_file.sql

# Verifikasi database setelah restore
mysql -u root -P 3306 -e "USE blazz; SHOW TABLES;"
mysql -u root -P 3306 -e "SELECT COUNT(*) FROM blazz.users;"
mysql -u root -P 3306 -e "SELECT COUNT(*) FROM blazz.organizations;"
mysql -u root -P 3306 -e "SELECT COUNT(*) FROM blazz.chats;"

# Verifikasi dengan socket MAMP
mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock -e "USE blazz; SHOW TABLES;"
```

### Tips Backup:
- Selalu backup sebelum melakukan perubahan besar pada database
- Gunakan timestamp pada nama file untuk tracking versi
- Simpan backup di lokasi yang aman dan terpisah
- Test restore backup secara berkala untuk memastikan integritas
- Untuk database besar, gunakan opsi `--single-transaction` untuk menghindari locking
- **Blazz Specific**: Backup juga file uploads di `/public/uploads/` dan storage files
- **Security**: Jangan commit file .env yang berisi database credentials ke git repository

### Environment Setup untuk Blazz:
```bash
# Copy environment file
cp .env.laravel12.backup .env

# Generate application key
php artisan key:generate

# Setup database connection di .env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=blazz
DB_USERNAME=root
DB_PASSWORD=
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Setup permissions
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs
```

### File Backup Terbaru:
- `blazz_backup_20250918_103000.sql` - Full backup lengkap database Blazz
- `blazz_structure_only.sql` - Struktur tabel saja tanpa data
- `blazz_full_backup.sql` - Full backup dengan routines & triggers
- `blazz.sql` - File SQL original untuk restore database

### Laravel Artisan Commands untuk Post-Restore:
```bash
# Jalankan setelah restore database
php artisan migrate:status              # Cek status migrasi
php artisan db:seed                     # Jalankan seeding jika diperlukan
php artisan optimize:clear              # Clear cache
php artisan config:cache                # Cache config
php artisan route:cache                 # Cache routes
php artisan view:cache                  # Cache views

# Verifikasi aplikasi
php artisan queue:work --once           # Test queue system
php artisan schedule:run                # Test scheduled jobs
```              

Tanggal restore: 2025-09-18
Tanggal update dokumentasi backup: 2025-09-18
Project: Blazz - Laravel Chat Application
Database: blazz
Environment: MAMP local development
