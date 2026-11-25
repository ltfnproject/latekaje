# LATEKAJE v4 — Inventaris & Peminjaman Alat Lab TJKT

Sistem informasi inventaris dan sirkulasi peminjaman alat laboratorium (**Lab TJKT SMK Walisongo Pecangaan**).
Dibangun dengan **Laravel 13 + Filament 5 + Livewire 4**, MySQL 8.0/8.4, QR Code scanner lokal (`html5-qrcode`), notifikasi WhatsApp (GOWA) + Webhook, dan siap deploy di VPS publik via **Docker (Tanpa Build)**.

> **Status:** aktif dikembangkan di branch `latekaje-v4` (evolusi terstruktur dari `main` v3).
> **Progres Roadmap:** ✅ Fase 0 (Fondasi) · ✅ Fase 1 & 1.5 (Keamanan, Tata Kelola Role, Penyatuan Akun & Anggota, Deploy #1) · ⏳ Fase 2 (Pengadaan, Barang Terpasang, Kabel Massal, Bahan Habis Pakai) · ⏳ Fase 3 (Kit 1× Scan, Peminjaman 2 Sisi Tanpa PIN, Tiket Kerusakan) · ⏳ Fase 4 (Cetak QR 5 Preset PT-P750W & Rekap Global KKK).

---

## Daftar Isi

1. [Ringkasan & Alur Kerja](#1-ringkasan--alur-kerja)
2. [Fitur per Modul](#2-fitur-per-modul)
3. [Tata Kelola 5 Role & Keamanan](#3-tata-kelola-5-role--keamanan)
4. [Persyaratan Sistem](#4-persyaratan-sistem)
5. [Deploy Produksi ke VPS via Docker (Cara B — Tanpa Build)](#5-deploy-produksi-ke-vps-via-docker-cara-b--tanpa-build)
6. [Instalasi Lokal (Development)](#6-instalasi-lokal-development)
7. [Konfigurasi Environment (.env)](#7-konfigurasi-environment-env)
8. [Struktur Database](#8-struktur-database)
9. [Operasional Harian](#9-operasional-harian)
10. [Pemecahan Masalah (Troubleshooting)](#10-pemecahan-masalah-troubleshooting)
11. [Testing](#11-testing)
12. [Alur Kerja Git & Update VPS](#12-alur-kerja-git--update-vps)

---

## 1. Ringkasan & Alur Kerja

Aplikasi menyatukan inventaris fisik per-unit (`assets` → `asset_items`), identitas peminjam terpadu (`users` ⇄ `members` dengan kategori **Siswa / Guru / Karyawan**), dan transaksi peminjaman berbasis scan QR kamera HP/PC:

```text
PINJAM ALAT
  1. Scan stiker QR alat (kamera HP/PC via bundle lokal) atau ketik kode manual
     → validasi otomatis (terdaftar? tersedia? kondisi baik?)
  2. Identitas peminjam:
     • Login Peminjam (Siswa/Guru/Karyawan): identitas & grup terkunci otomatis sesuai akun.
     • Login Petugas (Toolman/KKK/Admin Lab): pilih dari daftar Anggota atau tambah cepat.
  3. Kunci transaksi dengan PIN 6 digit (akan berevolusi ke alur 2 sisi tanpa PIN + wajib foto di Fase 3).

KEMBALI ALAT
  Scan ulang QR + verifikasi PIN + nama pengembali (Sendiri / Diwakilkan)
  + penerima petugas (khusus staf, tersembunyi dari peminjam)
  + foto bukti (otomatis disanitasi JPEG bersih & disimpan di storage privat).
```

**Aturan integritas yang ditegakkan di level backend & database:**

| Aturan | Penegakan |
|---|---|
| 1 unit = maks 1 pinjaman aktif | Validasi form + `LoanService` (`lockForUpdate`) + constraint unik DB (`active_item_id`) |
| Unit rusak tidak bisa dipinjam | Validasi form + `LoanService` |
| Hapus unit ber-riwayat | Riwayat tetap utuh (`asset_item_id` di-set `NULL`, tampil `(unit dihapus)`) |
| Hapus unit yang sedang dipinjam | Ditolak `AssetItemPolicy` |
| Akun peminjam anti-yatim | `MemberAccountSync` menyinkronkan `User` ⇄ `Member` otomatis 2 arah |
| Akun nonaktif | Ditolak login (`CodeUserProvider`), sesi DB dihapus, middleware `EnsureUserIsActive` langsung logout |
| Password awal anggota (`DDMMYYYY`) | `EnsurePasswordChanged` mengarahkan ke Profil sampai password diganti |

---

## 2. Fitur per Modul

### Dashboard *(Khusus Petugas & Pimpinan — tersembunyi dari Peminjam)*
Statistik inventaris (Total Unit, Siap Pakai, Dipinjam, Karantina), grafik donat Kondisi & Lokasi, grafik aktivitas 6 bulan, tabel Pinjaman Aktif Terkini, Unit Perlu Perhatian, dan tabel **Belum Kembali** (durasi otomatis format **Bulan Hari Jam Menit** yang menyembunyikan satuan lebih besar bila belum tercapai + tombol Kirim Rekap).

### Aset & Data Unit/QR
- **Aset**: Katalog jenis alat (kode aset unik, nama, jenis, spesifikasi, kegunaan) dengan aksi cepat **Tambah Unit**.
- **Data Unit/QR**: Unit fisik ber-QR (`LTKJ-YYYY-#####` atau SN manual), dukungan tambah massal, import/export Excel & CSV (pemisah `;` + BOM UTF-8 ramah Excel Indonesia), pindah ruangan massal, dan **Cetak QR** (`/print-qr`, dilindungi Gate `print:AssetItem`).

### Peminjaman & Pengembalian
- Peminjam (`users`) otomatis diarahkan ke halaman Peminjaman dan **hanya melihat pinjaman miliknya sendiri** (*fail-closed* bila akun tidak punya identitas).
- Foto bukti pengembalian disimpan di disk privat (`storage/app/private/returns/YYYY/MM/`), di-reencode bersih lewat `SecureImageService` (buang EXIF/polyglot, maks 1600px), dan hanya bisa dibuka pemilik pinjaman / petugas via `/files/loans/{loan}/return-photo`.

### Data Master: Lokasi, Grup, & Anggota (Siswa / Guru / Karyawan)
- **Grup**: Kelas (`X TJKT 1`, `XI TJKT 1`, dll.), `GURU`, `KARYAWAN`, dan eksternal.
- **Anggota**: Membedakan kategori **Siswa**, **Guru**, dan **Karyawan** (`member_type`), dropdown `Grup` terhubung ke master `groups` (anti-typo), **Tanggal Lahir** (`birth_date`), toggle status aktif sinkron ke akun login, tombol **Buat Akun** per baris, **Buat Akun Massal**, **Reset Password Awal (`DDMMYYYY`)**, serta **Import CSV** (tidak menimpa password pribadi yang sudah diganti peminjam).

### Kelola User *(Khusus Super Admin)*
- Form dinamis: memilih role **Peminjam** otomatis memunculkan seksi **Profil Peminjam** (tautkan ke Anggota lama atau buat otomatis, pilih Kategori `Siswa`/`Guru`/`Karyawan`, dropdown `Grup`, dan `Tanggal Lahir` dengan auto-password `DDMMYYYY`).
- Tabel menampilkan badge **Role**, **Kategori**, & **Grup**, dilengkapi filter Role + filter Kategori, toggle aktif (langsung menendang sesi), dan **ACL khusus per user** (`allow`/`deny`).

### Log Aktivitas *(Khusus Super Admin & KKK)*
Audit log *append-only* (`activity_logs`) yang **immutable** (tidak bisa diubah/dihapus bahkan oleh Super Admin). Mencatat aksi sensitif (pinjam, kembali, tutup paksa, cetak QR, buat/ubah/nonaktifkan akun, ubah setting, mode perawatan, token API) dengan **auto-redact** otomatis untuk field `password`, `secret`, `token`, `pass`, dan `pin`.

### Setting *(Khusus Super Admin & KKK)*
- **Bahasa Aplikasi**: Indonesia / English (termasuk pesan validasi penuh di `lang/id/validation.php` & `lang/en/validation.php`).
- **Mode Perawatan**: Menggunakan `--secret` acak + cookie bypass + tampilan link bypass agar admin **tidak terkunci keluar**.
- **API Token (Sanctum)**: Khusus **Super Admin**.
- **Notifikasi GOWA WhatsApp & Webhook**: Kredensial dienkripsi *at-rest* di database (`enc::` + `Crypt::encryptString`), dilindungi **anti-SSRF** (`SafeUrl` memblokir `169.254.0.0/16`, `file://`, loopback/IP privat kecuali masuk `SAFE_URL_ALLOWED_HOSTS`), dan tanpa redirect terbuka.

---

## 3. Tata Kelola 5 Role & Keamanan

| Hak Akses / Modul | Super Admin (Tim IT) (`superadmin`) | KKK / Kepala Lab (`admin`) | Toolman / Teknisi (`staff`) | Admin Lab / Prakerin (`assistant`) | Peminjam: Siswa/Guru/Karyawan (`users`) |
|---|---|---|---|---|---|
| Dashboard, Widget, & Data per Lokasi | ✅ | ✅ | ✅ | ✅ | ❌ (`403`) |
| Kelola User & ACL & API Token | ✅ | ❌ | ❌ | ❌ | ❌ |
| Setting Notifikasi & Mode Perawatan | ✅ | ✅ *(non-token)* | ❌ | ❌ | ❌ |
| Log Aktivitas (Read-Only) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Cetak QR (`/print-qr`) | ✅ | ✅ | ✅ | ✅ | ❌ (`403`) |
| Master Aset, Lokasi, Grup | ✅ | ✅ | Lihat saja | Lihat saja | ❌ |
| Unit Fisik & Data Anggota | ✅ | ✅ | Tambah/Ubah *(tanpa hapus)* | Tambah/Ubah *(tanpa hapus)* | ❌ |
| Pinjam & Kembalikan Alat | Kembalikan / Tutup Paksa | ✅ | ✅ | ✅ *(reguler)* | 🔒 Milik sendiri saja |
| ACC Kritis & Finansial (Siap Fase 2–3) | ✅ ACC & Finansial | ✅ ACC & Finansial | ✅ ACC *(non-finansial)* | ❌ | ❌ |

**Pengamanan Tambahan Fase 1:**
- `html5-qrcode@2.3.8` di-bundle lokal di `public/js/vendor/html5-qrcode.min.js` (0 panggilan CDN luar).
- Global `SecurityHeaders`: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(self), microphone=(), geolocation=()`, dan HSTS saat HTTPS.
- `TRUSTED_PROXIES` dibatasi via `.env` (bukan wildcard `*`).

---

## 4. Persyaratan Sistem

- **PHP**: `^8.3` (rekomendasi **PHP 8.4** di VPS) dengan ekstensi `bcmath`, `ctype`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `simplexml`, `tokenizer`, `xml`, `zip`.
- **Database**: MySQL ≥ 8.0 / **MySQL 8.4 LTS** (atau MariaDB ≥ 10.4).
- **Composer 2** (sudah terpasang bawaan di image Docker `webdevops/php-nginx:8.4-alpine`).
- **HTTPS valid di produksi** — kamera scanner QR (`getUserMedia`) wajib berjalan di *secure context* (`https://`).

---

## 5. Deploy Produksi ke VPS via Docker (Cara B — Tanpa Build)

Template siap pakai tersedia di folder [`deploy/docker-eskasongo/`](deploy/docker-eskasongo/):
- [`deploy/docker-eskasongo/docker-compose.yml`](deploy/docker-eskasongo/docker-compose.yml) — memakai image `webdevops/php-nginx:8.4-alpine` (`FPM_PM_MAX_CHILDREN=5`, tanpa exposed `ports:`, terhubung ke external network `app-network`).
- [`deploy/docker-eskasongo/env.vps.example`](deploy/docker-eskasongo/env.vps.example) — template `.env` produksi bebas rahasia.
- [`deploy/docker-eskasongo/README-DEPLOY.md`](deploy/docker-eskasongo/README-DEPLOY.md) — panduan operasional lengkap khusus VPS `eskasongo`.

### Ringkasan Langkah Deploy Pertama

#### 1. Buat Database & User Terisolasi di `core-db-mysql`
```bash
docker exec -it core-db-mysql mysql -u root -p
```
```sql
CREATE DATABASE IF NOT EXISTS smk_latekaje CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'smk_latekaje'@'%' IDENTIFIED BY 'GANTI_PASSWORD_DB_YANG_KUAT';
GRANT ALL PRIVILEGES ON smk_latekaje.* TO 'smk_latekaje'@'%';
FLUSH PRIVILEGES;
EXIT;
```

#### 2. Siapkan Folder Stack & Clone Branch `latekaje-v4`
```bash
mkdir -p /home/eskasongo/docker/smk-latekaje-web
cd /home/eskasongo/docker/smk-latekaje-web

git clone -b latekaje-v4 https://github.com/ltfnproject/latekaje.git src
cp src/deploy/docker-eskasongo/docker-compose.yml ./docker-compose.yml
cp src/deploy/docker-eskasongo/env.vps.example ./src/.env
chmod 600 ./src/.env
nano ./src/.env   # Isi DB_PASSWORD, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD, APP_URL
```

#### 3. Siapkan Folder Writable & Jalankan Container
```bash
mkdir -p src/bootstrap/cache \
  src/storage/app/private/returns \
  src/storage/app/public \
  src/storage/framework/{cache/data,sessions,testing,views} \
  src/storage/logs

docker compose up -d
```

#### 4. Atur Kepemilikan User `application` (Tanpa `chmod 777`), Install Vendor & Inisialisasi
> **Penting:** Pastikan `.env` (tetap mode `600`) dan `public/` dimiliki oleh `application:application` agar PHP-FPM & `php artisan` tidak mengalami `Permission denied`.

```bash
docker exec smk-latekaje-web chown application:application /var/www/html/.env
docker exec smk-latekaje-web chmod 600 /var/www/html/.env
docker exec smk-latekaje-web chown -R application:application /var/www/html/public /var/www/html/storage /var/www/html/bootstrap/cache
docker exec smk-latekaje-web chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

docker exec smk-latekaje-web composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
docker exec smk-latekaje-web chown -R application:application /var/www/html/vendor /var/www/html/public /var/www/html/storage /var/www/html/bootstrap/cache

docker exec -u application smk-latekaje-web php artisan optimize:clear
docker exec -u application smk-latekaje-web php artisan key:generate --force
docker exec -u application smk-latekaje-web php artisan migrate --force --seed
docker exec -u application smk-latekaje-web php artisan storage:link
docker exec -u application smk-latekaje-web php artisan optimize
```

#### 5. Pasang Proxy Host & SSL di Nginx Proxy Manager (`core-proxy-npm`)
- **Domain**: `inv.tjkt.smkw9jepara.sch.id`
- **Scheme**: `http` · **Forward Hostname**: `smk-latekaje-web` · **Forward Port**: `80`
- **Block Common Exploits**: ON · **Websockets Support**: ON
- **SSL**: Request Let's Encrypt → aktifkan **Force SSL** & **HTTP/2 Support**.

#### 6. Jadwal Backup & Scheduler (Disarankan)
- Backup berkala: `mysqldump` database `smk_latekaje` + arsip folder `src/storage/app/private/`.
- Scheduler rekap WA harian (cron host/container tiap menit):
  ```cron
  * * * * * docker exec -u application smk-latekaje-web php artisan schedule:run >> /dev/null 2>&1
  ```

---

## 6. Instalasi Lokal (Development)

```bash
git clone -b latekaje-v4 https://github.com/ltfnproject/latekaje.git
cd latekaje
cp .env.example .env
composer install
php artisan key:generate
# Buat database `latekaje` dan `latekaje_testing` di MySQL lokal (Laragon/XAMPP/Docker)
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

> **Catatan Windows + OneDrive:** Jika proyek dibuka di dalam folder sinkronisasi OneDrive dan muncul error `bootstrap/cache directory must be present and writable`, hilangkan atribut `ReadOnly` folder dengan:
> ```powershell
> attrib -R "bootstrap\cache" /D
> attrib -R "storage" /S /D
> ```

---

## 7. Konfigurasi Environment (`.env`)

Acuan resmi ada di [`.env.example`](.env.example) (lokal) dan [`deploy/docker-eskasongo/env.vps.example`](deploy/docker-eskasongo/env.vps.example) (produksi):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://inv.tjkt.smkw9jepara.sch.id
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=core-db-mysql
DB_DATABASE=smk_latekaje
DB_USERNAME=smk_latekaje
DB_PASSWORD=GANTI_PASSWORD_DB_YANG_KUAT

SUPERADMIN_NAME="Tim IT Lab TJKT"
SUPERADMIN_EMAIL="admin@tjkt.smkw9jepara.sch.id"
SUPERADMIN_PASSWORD="GANTI_PASSWORD_SUPERADMIN_KUAT"
SEED_DEMO=false

# Pengaturan Keamanan LATEKAJE v4
TRUSTED_PROXIES=172.16.0.0/12
ALLOW_GENERIC_KIOSK=false
# Host internal tepercaya untuk GOWA/Webhook (pisahkan koma, misal: core-gowa,100.64.0.10)
SAFE_URL_ALLOWED_HOSTS=
```

> **Jangan pernah commit file `.env` asli ke Git.**

---

## 8. Struktur Database

Entitas utama: `users` ⇄ `members` → `loans` ⇄ `asset_items` → `assets` (+ `locations`, `groups`, `activity_logs`, `settings`, `recap_logs`).

- **`users`**: Akun login (`email`, `code` unik, `member_id` FK, `role`, `is_active`, `must_change_password`, `permissions` JSON).
- **`members`**: Master peminjam (`code` unik, `name`, `group`, `member_type` `student|teacher|employee`, `birth_date`, `aktif`). Disinkronkan otomatis dengan `users` lewat `MemberAccountSync`.
- **`assets` & `asset_items`**: Katalog alat & unit fisik (`nomor_seri_atau_qr` unik, `status`, `kondisi`, `location_id`).
- **`loans`**: Transaksi peminjaman (`asset_item_id` nullable + `nullOnDelete`, `member_id`, `code`, `return_pin`, `returned_by`, `return_relation`, `return_method`, `received_by`, `return_photo_path` privat, guard unik `active_item_id`).
- **`activity_logs`**: Log audit *append-only* (`user_id`, `action`, `subject_type`, `subject_id`, `description`, `properties` JSON ter-redaksi, `ip_address`, `user_agent`, `created_at`).
- **`settings`**: Key-value konfigurasi global dengan enkripsi *at-rest* (`enc::`) untuk kunci rahasia.

---

## 9. Operasional Harian

| Tugas | Cara |
|---|---|
| Tambah banyak unit sejenis | **Aset** → aksi **Tambah Unit** (atau **Data Unit/QR → Tambah Unit**) → isi jumlah (+ SN manual bila ada) |
| Daftar siswa/guru/karyawan massal | **Anggota → Template CSV** → isi kolom `code;name;group;member_type;birth_date` → **Import** |
| Buat / reset akun login anggota | **Anggota → Buat Akun** (atau **Buat Akun Massal**) → password awal `DDMMYYYY`, wajib ganti di Profil |
| Buat akun peminjam langsung dari Kelola User | **Kelola User → Tambah Pengguna** → Role `Peminjam` → isi seksi *Profil Peminjam* (otomatis buat/tautkan Anggota) |
| Audit aksi sensitif | **Sistem → Log Aktivitas** *(khusus Super Admin & KKK)* |
| Blokir akun bermasalah | **Kelola User → Nonaktifkan** atau matikan toggle **Aktif** di **Anggota** (sesi langsung terputus) |
| Mode Perawatan aman | **Setting → Nyalakan Mode Perawatan** → salin **Link bypass admin** yang muncul agar tidak terkunci |

---

## 10. Pemecahan Masalah (Troubleshooting)

| Gejala | Penyebab → Solusi |
|---|---|
| `file_get_contents(/var/www/html/.env): Permission denied` atau `database.sqlite does not exist` di Docker | File `.env` masih dimiliki `root:root` mode `600`, sehingga user `application` tidak bisa membaca `.env`. Jalankan `chown application:application /var/www/html/.env && chmod 600 /var/www/html/.env`, lalu `php artisan optimize:clear` dan ulangi `key:generate` / `migrate` / `optimize`. |
| `symlink(): Permission denied` saat `storage:link` | Folder `public/` masih dimiliki `root`. Jalankan `chown -R application:application /var/www/html/public`. |
| Kamera QR tidak muncul di HP | Halaman wajib diakses lewat **HTTPS** (`https://...`), izinkan akses kamera di Chrome HP, pastikan tidak dibuka lewat webview terbatas. |
| GOWA/Webhook ditolak `[blocked]` (SSRF) | Target memakai IP privat/internal atau Tailscale (`100.64.x.x`). Tambahkan hostname/IP tepercaya ke `SAFE_URL_ALLOWED_HOSTS` di `.env`, lalu jalankan `php artisan optimize`. *(IP metadata cloud `169.254.0.0/16` selalu diblokir permanen.)* |
| Import CSV menumpuk di kolom A | Excel Indonesia memakai pemisah `;`. Gunakan tombol **Template CSV** bawaan aplikasi. |
| Redirect ke `http://` di balik Nginx Proxy Manager | Pastikan `APP_URL=https://...`, `TRUSTED_PROXIES=172.16.0.0/12`, dan **Force SSL** aktif di NPM. |

---

## 11. Testing

Suite pengujian dijalankan pada database terisolasi `latekaje_testing` (terkonfigurasi di `phpunit.xml`):

```bash
php artisan test
```

Cakupan pengujian saat ini (**22 passed, 206 assertions**):
- `SecurityHardeningTest`: proteksi `/print-qr`, halaman Lokasi, 7 widget, anti-IDOR `LoanPolicy` & foto bukti privat + sanitasi polyglot, tata kelola role (KKK/Toolman/Admin Prakerin/Peminjam), enkripsi `Setting`, anti-SSRF `SafeUrl`, blokir akun nonaktif & `must_change_password`, login `DDMMYYYY`, bundle lokal `html5-qrcode`, `SecurityHeaders`, dan redaksi `ActivityLogger`.
- `AccountMemberUnificationTest`: sinkronisasi 2 arah `User` ⇄ `Member`, kategori `Siswa`/`Guru`/`Karyawan`, preservasi password pribadi saat re-provision, repair akun yatim, & validasi Bahasa Indonesia.
- `LoanDurationFormatTest`: format durasi `Bulan Hari Jam Menit` dengan penyembunyian satuan progresif.
- Test fungsional & smoke: `AdminSmokeTest`, `AccountSecurityTest`, `CodeLoginTest`, `KioskTest`, `LoginRedirectTest`, `MemberLoanVisibilityTest`, `WsListenerTest`.

---

## 12. Alur Kerja Git & Update VPS

- **Pengembangan**: semua fitur v4 dikerjakan di branch `latekaje-v4` dan diverifikasi penuh dengan `php artisan test` + `php artisan optimize` sebelum push.
- **Update cepat di VPS `eskasongo`** setelah `git push origin latekaje-v4`:

```bash
cd /home/eskasongo/docker/smk-latekaje-web/src && git pull origin latekaje-v4
cd /home/eskasongo/docker/smk-latekaje-web
docker exec smk-latekaje-web composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
docker exec smk-latekaje-web chown -R application:application /var/www/html/vendor /var/www/html/public /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -u application smk-latekaje-web php artisan migrate --force
docker exec -u application smk-latekaje-web php artisan optimize
```
