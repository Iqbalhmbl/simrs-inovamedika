# Arsitektur SIMRS - Pelayanan Rawat Jalan Terintegrasi

## 1. Diagram Arsitektur

```
                         ┌─────────────────────────┐
                         │   React.js Frontend      │
                         │ (pendaftaran, pemeriksaan,│
                         │  farmasi, lab, billing,   │
                         │  dashboard)               │
                         └───────┬──────────┬────────┘
                                 │          │
              JWT in Authorization header (shared secret)
                                 │          │
              ┌──────────────────┘          └──────────────────┐
              ▼                                                ▼
┌─────────────────────────────┐              ┌──────────────────────────────┐
│   Yii2 Backend (PHP)        │              │  Node.js Services            │
│   - Business logic utama    │◄── REST ─────┤  - Dashboard aggregation API │
│   - Master data CRUD        │   (write     │  - External lab-integration  │
│   - Registrasi & antrean    │    relay)    │    push endpoint              │
│   - RM elektronik           │              │  - Shared JWT validation      │
│   - Tindakan medis          │              │  - Read-only DB access        │
│   - Farmasi & stok          │              │    (dashboard only)           │
│   - Billing & pembayaran    │              └──────────────┬───────────────┘
│   - RBAC (6 roles)          │                             │
└──────────────┬───────────────┘                             │
               │           writes                            │ reads
               ▼                                              ▼
                    ┌───────────────────────────┐
                    │   PostgreSQL              │
                    │   (single source of truth) │
                    └───────────────────────────┘
```

## 2. Pembagian Tanggung Jawab: Yii2 vs Node.js (wajib E.10)

Prinsip inti: **hanya ada satu pemilik untuk setiap aturan bisnis**, untuk
menghindari duplikasi logic dan inkonsistensi data.

| Area | Pemilik | Alasan |
|---|---|---|
| Semua write transaksional (pendaftaran, rekam medis, tindakan, resep, stok obat, billing, pembayaran) | **Yii2** | Butuh validasi, transaksi DB, audit trail, dan RBAC yang konsisten. Satu tempat = satu sumber kebenaran. |
| Master data CRUD | **Yii2** | Sama seperti di atas. |
| Autentikasi (login, penerbitan JWT) | **Yii2** | Identity & password hash dikelola di satu tempat. |
| Dashboard/laporan agregasi | **Node.js** | Query SELECT murni, tanpa aturan bisnis untuk diduplikasi. Dipisah agar query berat (agregasi, GROUP BY besar) tidak membebani request path transaksional Yii2. Node terhubung **read-only** secara konsep ke Postgres yang sama. |
| Integrasi pemeriksaan penunjang dari sistem eksternal | **Node.js (relay) → Yii2 (tulis)** | Node menerima, memvalidasi bentuk payload, autentikasi pemanggil, lalu **meneruskan** ke endpoint Yii2 (`POST /api/lab-results`) yang memegang aturan bisnis sebenarnya (menandai lab_request selesai, memicu kalkulasi ulang invoice). Node tidak pernah menulis langsung ke tabel transaksional ini. |

Konsekuensi desain ini: jika aturan bisnis farmasi berubah (misalnya aturan
baru pengurangan stok), perubahan hanya perlu dilakukan di satu tempat
(`PharmacyService` pada Yii2) - Node.js tidak memiliki salinan logic itu sama
sekali.

### Bagaimana Node.js berkomunikasi dengan Yii2 dan database

- **Ke database**: Node membuka koneksi pool terpisah (`pg`) ke Postgres yang
  sama, tapi hanya menjalankan `SELECT` agregasi untuk dashboard
  (`services/node/src/routes/dashboard.js`). Tidak ada `INSERT`/`UPDATE` ke
  tabel bisnis dari Node.
- **Ke Yii2**: untuk apa pun yang merupakan perubahan data bisnis (hasil lab
  eksternal), Node memanggil REST API Yii2 melalui `axios`
  (`services/node/src/utils/yiiClient.js`), membawa token JWT. Yii2 yang
  melakukan validasi, penulisan transaksional, dan audit.
- **Autentikasi bersama**: baik Yii2 maupun Node memvalidasi JWT HS256 dengan
  *secret* yang sama (`JWT_SECRET`). Sekali login di Yii2
  (`POST /api/auth/login`), token yang sama berlaku untuk kedua backend.

## 3. Alur Komunikasi React ↔ Yii ↔ Node ↔ PostgreSQL

1. User login lewat React → `POST {YII}/api/auth/login` → Yii2 memverifikasi
   password, menerbitkan JWT (klaim `sub`=user id, `role`=role Yii2).
2. React menyimpan token, menyertakan `Authorization: Bearer <token>` di
   setiap request berikutnya ke **kedua** backend.
3. Semua transaksi bisnis (pendaftaran, pemeriksaan, tindakan, resep,
   billing, pembayaran) → React memanggil Yii2 REST API langsung.
4. Dashboard → React memanggil Node.js REST API, yang mengagregasi dari
   Postgres secara langsung (read-only).
5. Sistem laboratorium eksternal → memanggil endpoint integrasi Node.js
   (`POST {NODE}/api/integrations/lab-results`), yang meneruskan ke Yii2.

## 4. Keputusan Teknis Penting

- **Database counter untuk nomor antrean** (`visit_queue_counters`) dipakai
  alih-alih `SELECT MAX(...) FOR UPDATE`, karena Postgres tidak mengizinkan
  `FOR UPDATE` dikombinasikan dengan fungsi agregat. Pendekatan
  `INSERT ... ON CONFLICT DO UPDATE ... RETURNING` sudah diuji langsung
  terhadap Postgres dengan 5 transaksi konkuren dan terbukti atomic (lihat
  `services/RegistrationService.php` dan `docs/technical-decisions.md`).
- **Snapshot harga/tarif**: `visit_procedures.tariff_at_time` dan
  `prescription_items.price_at_time` menyimpan harga saat transaksi dibuat,
  bukan merujuk ke master data secara real-time, supaya perubahan tarif di
  masa depan tidak mengubah nilai tagihan historis.
- **Audit trail (`audit_histories`)**: setiap perubahan pada rekam medis,
  tindakan (void), dan invoice dicatat (siapa, kapan, data lama/baru),
  memenuhi kebutuhan problem D4.
- **Error envelope konsisten**: baik Yii2 maupun Node.js mengembalikan format
  JSON yang sama (`{success, data}` / `{success, error:{code,message,details}}`),
  sehingga React/Postman tidak perlu menangani dua format berbeda.

Lihat `docs/technical-decisions.md` untuk detail penanganan 6 skenario
permasalahan teknis (bagian D proposal), masing-masing telah diverifikasi
langsung terhadap PostgreSQL yang sebenarnya (bukan hanya ditulis secara
teoritis).
