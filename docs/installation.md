# Petunjuk Instalasi dan Konfigurasi

Struktur proyek setelah pengembangan ini:

```
yii2-project/   - Backend utama Yii2 (sudah ada sebelumnya, ditambah migrations/models/controllers/services)
frontend/       - Frontend React.js (baru)
services/node/  - Backend services Node.js (baru)
docs/           - Dokumentasi teknis (baru)
```

## 1. Database (PostgreSQL)

```bash
createdb simrs
# atau lewat psql:
psql -U postgres -c "CREATE DATABASE simrs;"
```

Koneksi default (lihat `yii2-project/config/db.php`, bisa dioverride lewat
environment variable `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
`DB_PASSWORD`):

```
host=localhost port=5432 dbname=simrs user=postgres password=workingspace
```

## 2. Backend Yii2

```bash
cd yii2-project
composer install        # vendor/ sudah tersedia di proyek ini
php yii migrate          # menjalankan schema + RBAC init + dummy data
php yii serve --port=8080
```

Setelah migrate, akun dummy yang tersedia (password sama untuk semua:
`password123`):

| Username | Role |
|---|---|
| admin | admin |
| pendaftaran1 | petugas_pendaftaran |
| dokter1, dokter2 | dokter |
| farmasi1 | petugas_farmasi |
| lab1 | petugas_laboratorium |
| kasir1 | kasir |

API berjalan di `http://localhost:8080/api/...` (lihat `docs/api.md`).

## 3. Backend Services (Node.js)

```bash
cd services/node
cp .env.example .env     # sesuaikan JWT_SECRET agar SAMA dengan yii2-project/config/params.php
npm install
npm start                 # listens on :4000
```

## 4. Frontend (React.js)

```bash
cd frontend
cp .env.example .env      # sesuaikan URL backend jika berbeda dari default
npm install
npm run dev                # http://localhost:5173
```

Build untuk produksi: `npm run build` menghasilkan folder `dist/`.

## 5. Pengujian API (Postman)

Import `docs/postman_collection.json` ke Postman. Jalankan request
"Login" terlebih dahulu - token JWT akan otomatis tersimpan ke variabel
collection (`{{token}}`) lewat test script, dan dipakai otomatis oleh
seluruh request lain di collection ini.

## 6. Menjalankan Ulang dari Awal (reset)

```bash
cd yii2-project
php yii migrate/down 3   # rollback 3 migration yang dibuat (schema, rbac, seed)
php yii migrate          # jalankan lagi dari awal dengan dummy data segar
```

## Variabel Lingkungan Penting

| Variabel | Dipakai di | Keterangan |
|---|---|---|
| `JWT_SECRET` | Yii2 (`params.php`) & Node (`.env`) | **Harus sama** di kedua backend agar satu login berlaku untuk keduanya. |
| `DB_HOST/PORT/NAME/USER/PASSWORD` | Yii2 & Node | Koneksi Postgres yang sama untuk kedua backend. |
| `YII_API_BASE_URL` | Node (`.env`) | URL Yii2 API, dipakai Node untuk meneruskan hasil lab eksternal. |
| `VITE_YII_API_URL` / `VITE_NODE_API_URL` | Frontend (`.env`) | URL kedua backend yang dipanggil React. |
