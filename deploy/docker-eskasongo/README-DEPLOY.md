# Panduan Deploy #1 — LATEKAJE v4 di VPS `eskasongo` (Cara B: Tanpa Build)

Panduan singkat ini menjalankan **LATEKAJE v4** memakai image siap pakai `webdevops/php-nginx:8.4-alpine` tanpa `Dockerfile`, terhubung ke `core-db-mysql` dan `core-proxy-npm` di jaringan eksternal `app-network`.

---

## Prasyarat di VPS `eskasongo`
1. Database `smk_latekaje` dan user non-root `smk_latekaje` sudah dibuat di container `core-db-mysql` (hanya punya hak akses ke `smk_latekaje.*`).
2. DNS A Record `inv.tjkt.smkw9jepara.sch.id` sudah diarahkan ke IP VPS `eskasongo` (`DNS Only` di Cloudflare).
3. Jika di folder `/home/eskasongo/docker/smk-latekaje-web/` masih ada draft lama dengan typo `container_name: smk-latekaje.web`, hentikan (`docker compose down`) dan ganti `docker-compose.yml` dengan template ini agar menjadi `smk-latekaje-web`.

---

## Langkah Deploy #1 (Sekitar 5 Menit)

### 1. Siapkan folder proyek (Model Flat SMKW9)
```bash
mkdir -p /home/eskasongo/docker/smk-latekaje-web
cd /home/eskasongo/docker/smk-latekaje-web
git clone -b latekaje-v4 https://github.com/ltfnproject/latekaje.git src
cp src/deploy/docker-eskasongo/docker-compose.yml ./docker-compose.yml
cp src/deploy/docker-eskasongo/env.vps.example ./src/.env
chmod 600 ./src/.env
```

### 2. Edit `./src/.env`
Isi minimal nilai berikut dengan kredensial produksi:
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`
- Pastikan `APP_ENV=production`, `APP_DEBUG=false`, `SEED_DEMO=false`, `ALLOW_GENERIC_KIOSK=false`.

### 3. Install dependensi PHP via container `composer:2` (tanpa build image)
```bash
docker run --rm -v "$(pwd)/src:/app" -w /app composer:2 \
  install --no-dev --optimize-autoloader --no-interaction --prefer-dist
```

### 4. Siapkan direktori writable & jalankan container
```bash
mkdir -p src/bootstrap/cache \
  src/storage/app/private/returns \
  src/storage/app/public \
  src/storage/framework/{cache/data,sessions,testing,views} \
  src/storage/logs

docker compose up -d
```

### 5. Atur kepemilikan file (JANGAN gunakan `chmod 777`) & inisialisasi Laravel
```bash
docker exec smk-latekaje-web chown -R application:application /var/www/html/storage /var/www/html/bootstrap/cache
docker exec smk-latekaje-web chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

docker exec -u application smk-latekaje-web php artisan key:generate --force
docker exec -u application smk-latekaje-web php artisan migrate --force --seed
docker exec -u application smk-latekaje-web php artisan storage:link
docker exec -u application smk-latekaje-web php artisan optimize
```

### 6. Tambahkan Proxy Host di Nginx Proxy Manager (`core-proxy-npm`)
- **Domain Names**: `inv.tjkt.smkw9jepara.sch.id`
- **Scheme**: `http`
- **Forward Hostname / IP**: `smk-latekaje-web`
- **Forward Port**: `80`
- **Websockets Support**: ON
- **SSL**: Request Let's Encrypt + Force SSL + HTTP/2 Support

---

## Cara Update Cepat (Deploy Berikutnya)
```bash
cd /home/eskasongo/docker/smk-latekaje-web/src && git pull origin latekaje-v4
cd /home/eskasongo/docker/smk-latekaje-web
docker run --rm -v "$(pwd)/src:/app" -w /app composer:2 install --no-dev --optimize-autoloader --no-interaction
docker exec -u application smk-latekaje-web php artisan migrate --force
docker exec -u application smk-latekaje-web php artisan optimize
```
