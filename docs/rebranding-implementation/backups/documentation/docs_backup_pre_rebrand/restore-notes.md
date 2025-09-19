Restore summary

- Database: `swiftchats`
- Source SQL: `swiftchats.sql` (path: `/Applications/MAMP/htdocs/Swiftchats/swiftchats.sql`)

Commands yang saya jalankan di environment ini:

1) Buat database jika belum ada

mysql -u root -P 3306 -e "CREATE DATABASE IF NOT EXISTS swiftchats CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

2) Import dump

mysql -u root -P 3306 swiftchats < /Applications/MAMP/htdocs/Swiftchats/swiftchats.sql

Hasil verifikasi singkat:
- Tabel muncul (contoh: `users`, `organizations`, `chats`, `contacts`, `campaigns`, dll.)
- Contoh query verifikasi: SELECT COUNT(*) FROM swiftchats.users; => [jumlah user]
- Verifikasi tabel utama: SELECT COUNT(*) FROM swiftchats.organizations; => [jumlah org]

Catatan dan alternatif:
- Jika MySQL Anda memerlukan password, gunakan flag `-p` dan masukkan password bila diminta:
  mysql -u <user> -p -P 3306 -e "CREATE DATABASE IF NOT EXISTS swiftchats ..."
  mysql -u <user> -p -P 3306 swiftchats < swiftchats.sql
- Jika Anda menjalankan MySQL via MAMP, port default MySQL MAMP biasanya 8889 (bukan 3306) — sesuaikan `-P` atau koneksi socket.
- Untuk MAMP dengan socket connection: mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock swiftchats < swiftchats.sql
- Jika Anda perlu saya jalankan import dengan credentials lain, beri tahu user/port/password dan saya jalankan perintah sesuai.

## 📤 Cara Dump/Backup Database

### Perintah Dump Dasar (Standard MySQL - Port 3306):
```bash
# Full backup dengan data lengkap
mysqldump -u root -P 3306 swiftchats > swiftchats_backup_$(date +%Y%m%d_%H%M%S).sql
mysqldump -u root -P 3306 swiftchats > swiftchats_bak.sql

# Backup dengan password
mysqldump -u root -p -P 3306 swiftchats > swiftchats_backup.sql
mysqldump -u root -p'your_password' -P 3306 swiftchats > swiftchats_backup.sql

# Backup untuk MAMP dengan socket
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock swiftchats > swiftchats_backup.sql
```

### Opsi Dump Lengkap:
```bash
# Full backup dengan semua komponen
mysqldump -u root -P 3306 \
  --single-transaction \
  --routines \
  --triggers \
  --add-drop-table \
  swiftchats > swiftchats_full_backup.sql

# Hanya struktur tabel (tanpa data)
mysqldump -u root -P 3306 --no-data swiftchats > swiftchats_structure_only.sql

# Backup tabel tertentu
mysqldump -u root -P 3306 swiftchats users > users_backup.sql
mysqldump -u root -P 3306 swiftchats users organizations chats > core_tables.sql

# Backup dengan MAMP socket
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  --single-transaction \
  --routines \
  --triggers \
  swiftchats > swiftchats_full_backup.sql
```

### Backup dengan Kompresi (untuk file besar):
```bash
# Backup terkompresi
mysqldump -u root -P 3306 swiftchats | gzip > swiftchats_backup.sql.gz

# Backup terkompresi dengan MAMP socket
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock swiftchats | gzip > swiftchats_backup.sql.gz

# Restore dari file terkompresi
gunzip < swiftchats_backup.sql.gz | mysql -u root -P 3306 swiftchats

# Restore dengan socket
gunzip < swiftchats_backup.sql.gz | mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock swiftchats
```

### Alternatif untuk MAMP (Port 8889):
```bash
# Jika menggunakan MAMP dengan port default 8889
mysqldump -u root -P 8889 swiftchats > swiftchats_backup_mamp.sql

# Dengan password untuk MAMP
mysqldump -u root -p -P 8889 swiftchats > swiftchats_backup_mamp.sql

# Menggunakan socket MAMP (lebih reliable)
mysqldump -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock swiftchats > swiftchats_backup_socket.sql

# Import dengan socket MAMP
mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock swiftchats < swiftchats_backup.sql
```

### Verifikasi Backup:
```bash
# Cek ukuran file backup
ls -lh swiftchats_backup_*.sql

# Cek jumlah tabel dalam backup
grep "CREATE TABLE" nama_file.sql | wc -l

# Preview struktur backup
head -20 nama_file.sql

# Verifikasi database setelah restore
mysql -u root -P 3306 -e "USE swiftchats; SHOW TABLES;"
mysql -u root -P 3306 -e "SELECT COUNT(*) FROM swiftchats.users;"
mysql -u root -P 3306 -e "SELECT COUNT(*) FROM swiftchats.organizations;"
mysql -u root -P 3306 -e "SELECT COUNT(*) FROM swiftchats.chats;"

# Verifikasi dengan socket MAMP
mysql -u root --socket=/Applications/MAMP/tmp/mysql/mysql.sock -e "USE swiftchats; SHOW TABLES;"
```

### Tips Backup:
- Selalu backup sebelum melakukan perubahan besar pada database
- Gunakan timestamp pada nama file untuk tracking versi
- Simpan backup di lokasi yang aman dan terpisah
- Test restore backup secara berkala untuk memastikan integritas
- Untuk database besar, gunakan opsi `--single-transaction` untuk menghindari locking
- **Swiftchats Specific**: Backup juga file uploads di `/public/uploads/` dan storage files
- **Security**: Jangan commit file .env yang berisi database credentials ke git repository

### Environment Setup untuk Swiftchats:
```bash
# Copy environment file
cp .env.laravel12.backup .env

# Generate application key
php artisan key:generate

# Setup database connection di .env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=swiftchats
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
- `swiftchats_backup_20250918_103000.sql` - Full backup lengkap database Swiftchats
- `swiftchats_structure_only.sql` - Struktur tabel saja tanpa data
- `swiftchats_full_backup.sql` - Full backup dengan routines & triggers
- `swiftchats.sql` - File SQL original untuk restore database

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
Project: Swiftchats - Laravel Chat Application
Database: swiftchats
Environment: MAMP local development
