# SIMRS - Sistem Pelayanan Rawat Jalan Terintegrasi

Real Case Test - Senior Programmer, PT Inova Medika Solusindo.

## Struktur

- `./` (root folder ini) - Backend utama **Yii2** (migrations, models,
  controllers, services untuk business logic & REST API).
- `frontend/` - **React.js** frontend.
- `services/node/` - Backend services **Node.js** (dashboard aggregation API
  & integrasi pemeriksaan penunjang eksternal).
- `docs/` - Dokumentasi teknis lengkap:
  - `docs/architecture.md` - Diagram arsitektur, pembagian tanggung jawab Yii2 vs Node.js
  - `docs/technical-decisions.md` - Penanganan 6 skenario permasalahan teknis (D1-D6), masing-masing **diverifikasi langsung terhadap PostgreSQL** selama pengembangan
  - `docs/erd.md` - Entity Relationship Diagram
  - `docs/api.md` - Dokumentasi endpoint API
  - `docs/installation.md` - Petunjuk instalasi & konfigurasi
  - `docs/test-cases.md` - Test case fitur utama + hasil pengujian otomatis
  - `docs/postman_collection.json` - Koleksi Postman siap pakai

## Mulai Cepat

```bash
# 1. Database
createdb simrs

# 2. Backend Yii2
php yii migrate
php yii serve --port=8080

# 3. Backend Node.js
cd services/node && cp .env.example .env && npm install && npm start

# 4. Frontend
cd frontend && cp .env.example .env && npm install && npm run dev
```

Detail lengkap: lihat `docs/installation.md`.

## Cakupan Implementasi

Semua 9 modul pada Bagian B proposal diimplementasikan dengan alur nyata
(bukan mockup): master data, pendaftaran & antrean (dengan nomor antrean
atomic), pemeriksaan & rekam medis elektronik (dengan audit trail), tindakan
medis, farmasi & resep (dengan locking stok konkuren), pemeriksaan
penunjang (termasuk endpoint integrasi Node.js), billing & pembayaran
(idempotent), dashboard (Node.js aggregation API), serta RBAC untuk 6 role.

Keenam skenario permasalahan teknis pada Bagian D ditangani dan
**diverifikasi langsung terhadap PostgreSQL yang sebenarnya** (bukan hanya
secara teoritis) - lihat `docs/technical-decisions.md` untuk detail dan
bukti verifikasi tiap skenario.
