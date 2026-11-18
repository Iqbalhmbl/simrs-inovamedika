# Penanganan Skenario Permasalahan Teknis (Bagian D)

Setiap pendekatan di bawah telah **diverifikasi langsung terhadap PostgreSQL
16** yang berjalan nyata (bukan hanya ditulis secara teoritis) selama
pengembangan - lihat catatan "Verifikasi" di tiap bagian.

## D1. Duplikasi Pendaftaran

**Masalah**: dua petugas mendaftarkan pasien yang sama hampir bersamaan -
berisiko nomor antrean ganda atau kunjungan ganda.

**Solusi** (`services/RegistrationService.php`):
1. Pre-check aplikasi: menolak jika pasien sudah punya kunjungan aktif untuk
   dokter+tanggal yang sama (respons cepat untuk kasus umum).
2. Nomor antrean diambil dari tabel counter khusus `visit_queue_counters`
   lewat `INSERT ... ON CONFLICT (polyclinic_id, visit_date) DO UPDATE SET
   next_number = next_number + 1 RETURNING next_number`. Postgres mengunci
   baris counter tersebut selama statement ini berjalan, sehingga permintaan
   konkuren diserialisasi oleh database itu sendiri - tidak ada nomor yang
   hilang atau terduplikasi.
3. Dua `UNIQUE` constraint sebagai lapisan kedua:
   - `UNIQUE(polyclinic_id, visit_date, queue_number)` - menolak nomor
     antrean ganda di level database.
   - Partial unique index `(patient_id, doctor_id, visit_date) WHERE status
     != 'cancelled'` - menolak kunjungan aktif ganda untuk pasien yang sama.

**Verifikasi**: dijalankan 5 transaksi `psql` konkuren yang sama-sama
meng-increment counter yang sama dengan `pg_sleep` untuk memaksa overlap.
Hasil: setiap transaksi mendapat nomor urut berbeda (1-5), tidak ada yang
hilang atau terduplikasi.

## D2. Konkurensi Stok Obat

**Masalah**: dua transaksi farmasi memproses obat yang sama saat stok hanya
cukup untuk satu.

**Solusi** (`services/PharmacyService.php`):
1. `SELECT stock FROM medicines WHERE id = :id FOR UPDATE` di dalam
   transaksi - mengunci baris obat tersebut; transaksi kedua yang mencoba
   mengunci baris yang sama akan **menunggu** sampai transaksi pertama
   commit/rollback, lalu membaca ulang stok yang sudah ter-update.
2. `UPDATE medicines SET stock = stock - :qty WHERE id = :id AND stock >=
   :qty` - pengecekan kondisional sebagai lapisan kedua.
3. `CHECK (stock >= 0)` di level kolom sebagai penjaga terakhir.
4. Jika stok tidak cukup, transaksi dibatalkan (rollback) dan farmasi
   menerima pesan 409 yang jelas, bukan error generik.

**Verifikasi**: 3 transaksi `psql` konkuren mencoba mengurangi stok=1 secara
bersamaan dengan `FOR UPDATE` + `pg_sleep`. Hasil: tepat 1 transaksi berhasil
(`UPDATE 1`), 2 lainnya gagal kondisinya (`UPDATE 0`), stok akhir = 0 (tidak
pernah minus).

## D3. Pembayaran Ganda

**Masalah**: gangguan jaringan setelah kasir membayar; retry berpotensi
mencatat pembayaran dua kali.

**Solusi** (`services/BillingService.php::pay()`):
1. Klien (React) membuat **idempotency key** (UUID) sekali per klik "Bayar",
   disimpan di `useRef` di sisi klien, dan dikirim ulang dengan key yang
   **sama** jika terjadi retry pada percobaan yang sama (lihat
   `frontend/src/pages/BillingPage.jsx`).
2. Server mengecek dulu apakah `idempotency_key` tersebut sudah ada di
   `payments` - jika ya, langsung mengembalikan payment yang sudah ada
   (tidak membuat baris baru).
3. Kolom `payments.idempotency_key` adalah `UNIQUE`, jadi walau dua request
   dengan key sama lolos pre-check secara bersamaan, hanya satu `INSERT`
   yang akan berhasil; `IntegrityException` pada request yang kalah ditangkap
   dan server mengembalikan payment pemenang (bukan error).

**Verifikasi**: 3 `INSERT ... ON CONFLICT (idempotency_key) DO NOTHING`
dijalankan konkuren dengan key yang sama. Hasil: hanya 1 baris tercatat.

## D4. Perubahan Data Pemeriksaan Setelah Billing

**Masalah**: dokter mengubah diagnosis/tindakan setelah tagihan dibuat.

**Solusi**:
- Tindakan tidak pernah di-*hard delete*; "hapus" menandai
  `is_voided = true` (`VisitProcedureController::actionDelete`), sehingga
  riwayat tetap ada.
- Setiap perubahan pada `medical_records` dan `visit_procedures` dicatat ke
  tabel `audit_histories` (siapa, kapan, data lama vs baru) via
  `AuditService::record()` - dipanggil otomatis dari
  `MedicalRecord::afterSave()` dan dari controller untuk `VisitProcedure`.
- Setelah perubahan apa pun yang memengaruhi biaya, `BillingService::
  recalculate()` dipanggil ulang - invoice dihitung ulang dari data yang
  berlaku saat ini, dan `invoices.version` di-*bump* setiap kali dihitung
  ulang, dengan baris baru di `audit_histories` untuk invoice tersebut.
- Hasilnya: tagihan selalu konsisten dengan data pemeriksaan terbaru, DAN
  riwayat "kenapa berubah" selalu bisa ditelusuri lewat `audit_histories`.

## D5. Gangguan Integrasi

**Masalah**: salah satu layanan API (mis. integrasi lab eksternal) tidak
dapat diakses.

**Solusi** (`services/node/src/routes/labResults.js`):
- Node.js men-*timeout* pemanggilan ke Yii2 dalam 5 detik (`axios` timeout).
- Jika Yii2 tidak terjangkau, Node **tidak** menulis langsung ke database
  (itu akan menduplikasi logic bisnis) dan **tidak** diam-diam membuang data.
  Request dicatat ke tabel `integration_failures` (payload lengkap + pesan
  error) untuk rekonsiliasi manual, dan pemanggil menerima `502` dengan
  `details.retryable = true` sehingga sistem lab eksternal (yang pada
  umumnya sudah punya logic retry) bisa mengirim ulang.
- Jika Yii2 terjangkau tapi menolak (validasi gagal, 404, dst), respons
  Yii2 diteruskan apa adanya - tidak ada informasi yang hilang.

## D6. Performa Aplikasi

**Solusi yang diterapkan**:
- Index pada kolom yang sering dipakai untuk filter/join: `visits
  (visit_date, status)`, `patients (lower(name))` untuk pencarian case-
  insensitive, serta index pada semua foreign key yang sering di-query
  (`payments.invoice_id`, `prescription_items.prescription_id`,
  `visit_procedures.visit_id`).
- Query dashboard yang berat (agregasi lintas tabel) dipisah ke layanan
  Node.js, bukan dijalankan di request path Yii2 yang menangani transaksi -
  mengisolasi beban baca-berat dari beban tulis-transaksional.
- Snapshot harga (`tariff_at_time`, `price_at_time`) menghindari join
  tambahan ke master data saat menghitung ulang invoice.
- Paginasi (`ActiveDataProvider` dengan `pageSize`) diterapkan di semua
  endpoint listing, mencegah payload besar yang tidak perlu.
- Rencana lanjutan untuk skala lebih besar (didokumentasikan, belum
  diimplementasikan karena di luar cakupan 72 jam): read replica Postgres
  untuk Node.js dashboard, caching hasil dashboard per rentang tanggal
  (Redis), dan connection pooling (PgBouncer) di depan Postgres.
