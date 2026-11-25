# Panduan Deploy #1 — LATEKAJE v4 di VPS `eskasongo` (Cara B: Tanpa Build)

Panduan ini menjalankan **LATEKAJE v4** memakai image siap pakai `webdevops/php-nginx:8.4-alpine` (sudah termasuk PHP 8.4, Nginx dengan `try_files` Laravel, Composer, `ext-intl`, `ext-gd`, `ext-zip`, `ext-pdo_mysql`), terhubung ke `core-db-mysql` dan `core-proxy-npm` di jaringan eksternal `app-network`.

---

## Prasyarat
1. Branch `latekaje-v4` sudah di-push ke GitHub dari laptop lokal (`git push -u origin latekaje-v4`).
2. DNS A Record `inv.tjkt.smkw9jepara.sch.id` sudah diarahkan ke IP VPS `eskasongo` (`DNS Only`).
3. Database `smk_latekaje` + user non-root `smk_latekaje` siap dibuat di container `core-db-mysql`.

---

## Langkah 1 — Buat Database & User Terisolasi di `core-db-mysql`
Jalankan di VPS `eskasongo` (ganti `PASSWORD_DB_KUAT` dengan password acak yang kuat):
```bash
docker exec -it core-db-mysql mysql -u root -p
```
Lalu jalankan SQL berikut:
```sql
CREATE DATABASE IF NOT EXISTS smk_latekaje CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'smk_latekaje'@'%' IDENTIFIED BY 'PASSWORD_DB_KUAT';
GRANT ALL PRIVILEGES ON smk_latekaje.* TO 'smk_latekaje'@'%';
FLUSH PRIVILEGES;
EXIT;
```

---

## Langkah 2 — Bersihkan Draft Lama & Clone Branch `latekaje-v4` (Model Flat SMKW9)
```bash
mkdir -p /home/eskasongo/docker/smk-latekaje-web
cd /home/eskasongo/docker/smk-latekaje-web

# Jika dulu sempat ada container draft (misal typo smk-latekaje.web), hentikan dulu:
docker compose down 2>/dev/null || true
docker rm -f smk-latekaje.web 2>/dev/null || true

# Clone source code LATEKAJE v4 ke ./src
git clone -b latekaje-v4 https://github.com/ltfnproject/latekaje.git src

# Salin template Docker Compose & .env produksi
cp src/deploy/docker-eskasongo/docker-compose.yml ./docker-compose.yml
cp src/deploy/docker-eskasongo/env.vps.example ./src/.env
chmod 600 ./src/.env
```

---

## Langkah 3 — Isi Konfigurasi `./src/.env`
```bash
nano ./src/.env
```
Wajib periksa & isi nilai berikut:
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://inv.tjkt.smkw9jepara.sch.id`
- `DB_HOST=core-db-mysql`, `DB_DATABASE=smk_latekaje`, `DB_USERNAME=smk_latekaje`, `DB_PASSWORD=PASSWORD_DB_KUAT`
- `SUPERADMIN_EMAIL` dan `SUPERADMIN_PASSWORD` (password kuat!)
- `SEED_DEMO=false` (agar tidak membuat data demo di produksi)
- `TRUSTED_PROXIES=172.16.0.0/12` dan `ALLOW_GENERIC_KIOSK=false`

---

## Langkah 4 — Siapkan Folder Writable & Nyalakan Container
```bash
mkdir -p src/bootstrap/cache \
  src/storage/app/private/returns \
  src/storage/app/public \
  src/storage/framework/cache/data \
  src/storage/framework/sessions \
  src/storage/framework/testing \
  src/storage/framework/views \
  src/storage/logs

docker compose up -d
```

---

## Langkah 5 — Atur Permission (Tanpa `chmod 777`), Install Vendor, & Inisialisasi Laravel
Jalankan Composer langsung di dalam container `smk-latekaje-web` agar seluruh ekstensi PHP 8.4 (`intl`, `gd`, `zip`, `pdo_mysql`) cocok 100%:
```bash
docker exec smk-latekaje-web chown -R application:application /var/www/html/storage /var/www/html/bootstrap/cache
docker exec smk-latekaje-web chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

docker exec smk-latekaje-web composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
docker exec smk-latekaje-web chown -R application:application /var/www/html/vendor /var/www/html/storage /var/www/html/bootstrap/cache

docker exec -u application smk-latekaje-web php artisan key:generate --force
docker exec -u application smk-latekaje-web php artisan migrate --force --seed
docker exec -u application smk-latekaje-web php artisan storage:link
docker exec -u application smk-latekaje-web php artisan optimize
```

---

## Langkah 6 — Pasang Proxy Host & SSL di Nginx Proxy Manager (`core-proxy-npm`)
Buka dashboard NPM (`https://npm.eskasongo.sch.id/`) → **Hosts → Proxy Hosts → Add Proxy Host**:
- **Domain Names**: `inv.tjkt.smkw9jepara.sch.id`
- **Scheme**: `http`
- **Forward Hostname / IP**: `smk-latekaje-web`
- **Forward Port**: `80`
- **Block Common Exploits**: ON
- **Websockets Support**: ON
- Tab **SSL**: Request a new SSL Certificate (Let's Encrypt) → aktifkan **Force SSL** & **HTTP/2 Support**.

---

## Langkah 7 — Checklist Verifikasi Pasca-Deploy
1. Cek container sehat & tidak expose port ke host:
   ```bash
   docker ps --filter name=smk-latekaje-web
   docker logs --tail 30 smk-latekaje-web
   ```
2. Buka `https://inv.tjkt.smkw9jepara.sch.id/admin/login` dari HP & PC:
   - Login sebagai Super Admin (`SUPERADMIN_EMAIL`) → cek menu **Data Unit/QR**, **Anggota**, **Log Aktivitas**, dan **Setting**.
   - Cek header keamanan: `curl -I https://inv.tjkt.smkw9jepara.sch.id/admin/login` (harus ada `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Permissions-Policy: camera=(self)`).
   - Uji akun siswa uji (NIS + tanggal lahir `DDMMYYYY`): harus dipaksa ganti password di `/admin/profile`, tidak bisa akses `/print-qr` (`403`) maupun `/admin/data-per-lokasi` (`403`), dan scanner kamera HP berjalan normal lewat HTTPS.

---

## Cara Update Cepat (Untuk Deploy Fase 2, 3, 4 Nanti)
```bash
cd /home/eskasongo/docker/smk-latekaje-web/src
git pull origin latekaje-v4

cd /home/eskasongo/docker/smk-latekaje-web
docker exec smk-latekaje-web composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
docker exec smk-latekaje-web chown -R application:application /var/www/html/vendor /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -u application smk-latekaje-web php artisan migrate --force
docker exec -u application smk-latekaje-web php artisan optimize
```
